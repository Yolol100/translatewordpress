<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport\Concerns;

use Webactueel\Translate\Cache\TranslationCache;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
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
        $translated = trim(wp_kses_post(Input::scalar_string($data['translated_text'] ?? '')));
        $rowKey = $hash . ':' . $lang;

        if (isset($seen[$rowKey])) {
            return $this->csv_row_skip_error($line, __('Regel %d: dubbele hash/language combinatie in import overgeslagen.', 'webactueel-translate-language-dropdowns'));
        }
        $seen[$rowKey] = true;

        if ($hash === '' || strlen($hash) < 16 || $lang === '') {
            return $this->csv_row_skip_error($line, __('Regel %d: hash of language_code ontbreekt of is ongeldig.', 'webactueel-translate-language-dropdowns'));
        }
        if ($translated === '') {
            return $this->csv_row_skip_error($line, __('Regel %d: translated_text ontbreekt.', 'webactueel-translate-language-dropdowns'));
        }
        if ($languages && ! in_array($lang, $languages, true)) {
            return ['imported' => 0, 'skipped' => 1, 'errors' => []];
        }
        if (! $this->is_translatable_language($lang)) {
            return [
                'imported' => 0,
                'skipped' => 1,
                'errors' => [sprintf(__('Regel %1$d: taal %2$s is geen actieve vertaaltaal.', 'webactueel-translate-language-dropdowns'), $line, $lang)],
            ];
        }

        $stringId = $this->csv_row_string_id($data, $hash, $repo);
        $status = Input::key($data['status'] ?? '');
        if ($status !== '' && ! in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review'], true)) {
            return $this->csv_row_skip_error($line, __('Regel %d: status is ongeldig.', 'webactueel-translate-language-dropdowns'));
        }
        if ($status === '') {
            $status = 'published';
        }

        if ($stringId && $repo->save_translation($stringId, $lang, $translated, $status, 'csv')) {
            return ['imported' => 1, 'skipped' => 0, 'errors' => []];
        }

        return ['imported' => 0, 'skipped' => 0, 'errors' => []];
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
    private function csv_row_string_id(array $data, string $hash, TranslationRepository $repo): int
    {
        $stringId = $this->find_string_id_by_hash($hash);
        if ($stringId) {
            return $stringId;
        }

        return $repo->upsert_string(
            wp_kses_post(Input::scalar_string($data['original_text'] ?? '')),
            Input::key($data['source_type'] ?? ''),
            Input::absint($data['source_id'] ?? 0),
            Input::text($data['context'] ?? '')
        );
    }
}
