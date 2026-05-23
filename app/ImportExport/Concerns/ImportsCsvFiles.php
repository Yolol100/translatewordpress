<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport\Concerns;

use Webactueel\Translate\Cache\CacheInvalidator;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Translation\TranslationRepository;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This plugin uses its own custom translation tables; queries are scoped and cache invalidation is handled by the plugin.

trait ImportsCsvFiles
{
    use ValidatesLanguages;
    public function import_file(string $path, string $delimiter = ',', array $languages = []): array
    {
        global $wpdb;
        if (! is_readable($path)) {
            return ['imported' => 0, 'errors' => [__('CSV kon niet gelezen worden.', 'webactueel-translate-language-dropdowns')]];
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV stream handling needs native PHP streams.
        $handle = fopen($path, 'r');
        if (! $handle) {
            return ['imported' => 0, 'errors' => [__('CSV kon niet geopend worden.', 'webactueel-translate-language-dropdowns')]];
        }
        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        $required = ['hash', 'source_type', 'source_id', 'context', 'original_text', 'language_code', 'translated_text', 'status'];
        if (is_array($header)) {
            $header = array_map(static function ($h): string {
                $h = trim((string) $h);
                return preg_replace('/^\xEF\xBB\xBF/', '', $h) ?: $h;
            }, $header);
        }
        if (! is_array($header) || array_diff($required, $header)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing native CSV stream.
            fclose($handle);
            return ['imported' => 0, 'errors' => [__('CSV header mist verplichte kolommen.', 'webactueel-translate-language-dropdowns')]];
        }
        $repo = new TranslationRepository();
        $settings = Settings::all();
        $maxRows = isset($settings['csv_import_max_rows']) ? Input::absint($settings['csv_import_max_rows'], 10000) : 10000;
        $maxRows = (int) apply_filters('wat_csv_import_max_rows', max(1, min(50000, $maxRows)));
        $maxRows = max(1, min(50000, $maxRows));
        $languages = array_values(array_unique(array_filter(array_map('sanitize_key', $languages))));
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $seen = [];
        $line = 1;
        $truncated = false;
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $line++;
            if (($line - 1) > $maxRows) {
                $truncated = true;
                $errors[] = sprintf(__('CSV import gestopt na %d regels. Verdeel grotere imports in kleinere bestanden.', 'webactueel-translate-language-dropdowns'), $maxRows);
                break;
            }
            if (count($row) !== count($header)) {
                $errors[] = sprintf(__('Regel %d: ongeldig aantal kolommen.', 'webactueel-translate-language-dropdowns'), $line);
                continue;
            }

            $data = array_combine($header, $row);
            if (! is_array($data)) {
                $errors[] = sprintf(__('Regel %d: ongeldig aantal kolommen.', 'webactueel-translate-language-dropdowns'), $line);
                continue;
            }
            $hash = Input::text($data['hash'] ?? '');
            $lang = Input::key($data['language_code'] ?? '');
            $translated = trim(wp_kses_post(Input::scalar_string($data['translated_text'] ?? '')));
            $rowKey = $hash . ':' . $lang;
            if (isset($seen[$rowKey])) {
                $skipped++;
                $errors[] = sprintf(__('Regel %d: dubbele hash/language combinatie in import overgeslagen.', 'webactueel-translate-language-dropdowns'), $line);
                continue;
            }
            $seen[$rowKey] = true;
            if ($hash === '' || strlen($hash) < 16 || $lang === '') {
                $skipped++;
                $errors[] = sprintf(__('Regel %d: hash of language_code ontbreekt of is ongeldig.', 'webactueel-translate-language-dropdowns'), $line);
                continue;
            }
            if ($translated === '') {
                $skipped++;
                $errors[] = sprintf(__('Regel %d: translated_text ontbreekt.', 'webactueel-translate-language-dropdowns'), $line);
                continue;
            }
            if ($languages && ! in_array($lang, $languages, true)) {
                $skipped++;
                continue;
            }
            if (! $this->is_translatable_language($lang)) {
                $skipped++;
                $errors[] = sprintf(__('Regel %1$d: taal %2$s is geen actieve vertaaltaal.', 'webactueel-translate-language-dropdowns'), $line, $lang);
                continue;
            }
            $stringId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM `" . Tables::sql_identifier(Tables::strings()) . "` WHERE hash = %s", $hash));
            if (! $stringId) {
                $stringId = $repo->upsert_string(
                    wp_kses_post(Input::scalar_string($data['original_text'] ?? '')),
                    Input::key($data['source_type'] ?? ''),
                    Input::absint($data['source_id'] ?? 0),
                    Input::text($data['context'] ?? '')
                );
            }
            $status = Input::key($data['status'] ?? '');
            if ($status !== '' && ! in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review'], true)) {
                $skipped++;
                $errors[] = sprintf(__('Regel %d: status is ongeldig.', 'webactueel-translate-language-dropdowns'), $line);
                continue;
            }
            if ($status === '') {
                $status = 'published';
            }
            if ($stringId && $repo->save_translation($stringId, $lang, $translated, $status, 'csv')) {
                $imported++;
            }
        }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing native CSV stream.
        fclose($handle);
        CacheInvalidator::bump();
        do_action('wat_after_csv_import', $imported, $errors);
        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'max_rows' => $maxRows,
            'truncated' => $truncated,
            'errors' => array_slice($errors, 0, 50),
        ];
    }

}
