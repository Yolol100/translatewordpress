<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport\Concerns;

use Webactueel\Translate\Cache\TranslationCache;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Translation\StringNormalizer;
use Webactueel\Translate\Translation\TranslationRepository;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.

trait ImportsCsvFiles
{
    use ValidatesLanguages;
    use SharedImportHelpers;

    public function import_file(string $path, string $delimiter = ',', array $languages = []): array
    {
        if (! is_readable($path)) {
            return ['imported' => 0, 'errors' => [__('CSV kon niet gelezen worden.', 'webactueel-translate-language-dropdowns')]];
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV stream handling needs native PHP streams.
        $handle = fopen($path, 'r');
        if (! $handle) {
            return ['imported' => 0, 'errors' => [__('CSV kon niet geopend worden.', 'webactueel-translate-language-dropdowns')]];
        }

        $header = $this->read_csv_header($handle, $delimiter);
        if ($this->csv_header_missing_required_columns($header)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing native CSV stream.
            fclose($handle);
            return ['imported' => 0, 'errors' => [__('CSV header mist verplichte kolommen.', 'webactueel-translate-language-dropdowns')]];
        }

        $duplicates = array_unique(array_diff_assoc($header, array_unique($header)));
        if ($duplicates) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing native CSV stream.
            fclose($handle);
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => [sprintf(
                    /* translators: %s: comma-separated duplicate CSV column names. */
                    __('CSV header bevat dubbele kolommen: %s', 'webactueel-translate-language-dropdowns'),
                    implode(', ', $duplicates)
                )],
            ];
        }

        $repo = new TranslationRepository();
        $maxRows = $this->import_row_limit('wat_csv_import_max_rows');
        $languages = $this->normalize_import_languages($languages);
        $result = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
            'truncated' => false,
        ];
        $seen = [];
        $line = 1;

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $line++;
            if (($line - 1) > $maxRows) {
                $result['truncated'] = true;
                $result['errors'][] = sprintf(__('CSV import gestopt na %d regels. Verdeel grotere imports in kleinere bestanden.', 'webactueel-translate-language-dropdowns'), $maxRows);
                break;
            }

            $rowResult = $this->import_csv_row($row, $header, $line, $languages, $repo, $seen);
            $result['imported'] += $rowResult['imported'];
            $result['skipped'] += $rowResult['skipped'];
            $result['errors'] = array_merge($result['errors'], $rowResult['errors']);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing native CSV stream.
        fclose($handle);

        TranslationCache::bump();
        do_action('wat_after_csv_import', $result['imported'], $result['errors']);
        return [
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'max_rows' => $maxRows,
            'truncated' => $result['truncated'],
            'errors' => array_slice($result['errors'], 0, 50),
        ];
    }

    /** @return array<int, string> */
    private function read_csv_header($handle, string $delimiter): array
    {
        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        if (! is_array($header)) {
            return [];
        }

        return array_map(static function ($h): string {
            $h = trim((string) $h);
            return preg_replace('/^\xEF\xBB\xBF/', '', $h) ?: $h;
        }, $header);
    }

    /** @param array<int, string> $header */
    private function csv_header_missing_required_columns(array $header): bool
    {
        $required = ['hash', 'source_type', 'source_id', 'context', 'original_text', 'language_code', 'translated_text', 'status'];
        return $header === [] || array_diff($required, $header) !== [];
    }

    /**
     * @param array<int, string> $row
     * @param array<int, string> $header
     * @param array<int, string> $languages
     * @param array<string, bool> $seen
     * @return array{imported:int,skipped:int,errors:array<int, string>}
     */
    private function import_csv_row(array $row, array $header, int $line, array $languages, TranslationRepository $repo, array &$seen): array
    {
        if (count($row) !== count($header)) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => [sprintf(__('Regel %d: ongeldig aantal kolommen.', 'webactueel-translate-language-dropdowns'), $line)],
            ];
        }

        $data = array_combine($header, $row);
        $hash = Input::text($data['hash'] ?? '');
        $lang = Input::key($data['language_code'] ?? '');
        $original = trim(wp_kses_post(Input::scalar_string($data['original_text'] ?? '')));
        $translated = trim(wp_kses_post(Input::scalar_string($data['translated_text'] ?? '')));

        if ($lang === '') {
            return $this->csv_row_skip_error($line, __('Regel %d: language_code ontbreekt.', 'webactueel-translate-language-dropdowns'));
        }
        if ($original === '') {
            return $this->csv_row_skip_error($line, __('Regel %d: original_text ontbreekt.', 'webactueel-translate-language-dropdowns'));
        }
        if ($translated === '') {
            return ['imported' => 0, 'skipped' => 1, 'errors' => []];
        }

        $rowKey = $this->csv_row_import_key($data, $hash, $lang);
        if ($rowKey !== '' && isset($seen[$rowKey])) {
            return $this->csv_row_skip_error($line, __('Regel %d: dubbele importregel voor dezelfde doelstring/taal overgeslagen.', 'webactueel-translate-language-dropdowns'));
        }
        if ($rowKey !== '') {
            $seen[$rowKey] = true;
        }
        if ($languages && ! in_array($lang, $languages, true)) {
            return ['imported' => 0, 'skipped' => 1, 'errors' => []];
        }
        if (! $this->is_translatable_language($lang)) {
            return [
                'imported' => 0,
                'skipped' => 1,
                'errors' => [sprintf(
                    /* translators: 1: CSV row number, 2: language code. */
                    __('Regel %1$d: taal %2$s is geen actieve vertaaltaal.', 'webactueel-translate-language-dropdowns'),
                    $line,
                    $lang
                )],
            ];
        }

        $stringId = $this->csv_row_string_id($data, $hash, $repo);
        $status = Input::key($data['status'] ?? '');
        if (in_array($status, ['new', 'missing'], true)) {
            $status = '';
        }
        if ($status !== '' && ! in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review', 'outdated'], true)) {
            return $this->csv_row_skip_error($line, __('Regel %d: status is ongeldig.', 'webactueel-translate-language-dropdowns'));
        }
        if ($status === '') {
            $status = 'published';
        }
        $status = $this->apply_import_review_policy($status);

        if ($stringId && $repo->save_translation($stringId, $lang, $translated, $status, 'csv')) {
            return ['imported' => 1, 'skipped' => 0, 'errors' => []];
        }

        return [
            'imported' => 0,
            'skipped' => 1,
            'errors' => [sprintf(__('Regel %d: vertaling kon niet worden opgeslagen.', 'webactueel-translate-language-dropdowns'), $line)],
        ];
    }

    private function apply_import_review_policy(string $status): string
    {
        $settings = Settings::all();
        if (! current_user_can('manage_options') && ! empty($settings['translator_review_required']) && in_array($status, ['published', 'reviewed'], true)) {
            return 'needs_review';
        }

        return $status;
    }

    /** @return array{imported:int,skipped:int,errors:array<int, string>} */
    private function csv_row_skip_error(int $line, string $message): array
    {
        return [
            'imported' => 0,
            'skipped' => 1,
            'errors' => [sprintf($message, $line)],
        ];
    }

    /** @param array<string, mixed> $data */
    private function csv_row_import_key(array $data, string $hash, string $lang): string
    {
        if ($lang === '') {
            return '';
        }

        if (StringNormalizer::is_hash($hash)) {
            return 'hash:' . strtolower($hash) . ':' . $lang;
        }

        $original = trim(wp_kses_post(Input::scalar_string($data['original_text'] ?? '')));
        if ($original === '') {
            return '';
        }

        return 'fallback:' . hash('sha256', StringNormalizer::normalize($original)) . ':'
            . Input::key($data['source_type'] ?? '') . ':'
            . Input::absint($data['source_id'] ?? 0) . ':'
            . hash('sha256', Input::text($data['context'] ?? '')) . ':'
            . $lang;
    }

    /** @param array<string, mixed> $data */
    private function csv_row_string_id(array $data, string $hash, TranslationRepository $repo): int
    {
        if (StringNormalizer::is_hash($hash)) {
            $stringId = $this->find_string_id_by_hash($hash);
            if ($stringId) {
                return $stringId;
            }
        }

        $original = trim(wp_kses_post(Input::scalar_string($data['original_text'] ?? '')));
        if ($original === '') {
            return 0;
        }

        return $repo->upsert_string(
            $original,
            Input::key($data['source_type'] ?? ''),
            Input::absint($data['source_id'] ?? 0),
            Input::text($data['context'] ?? '')
        );
    }
}
