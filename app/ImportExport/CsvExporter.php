<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport;

use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Formatting;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic parts are escaped plugin-owned table names.

final class CsvExporter
{
    private function normalize_languages(array $languages): array
    {
        $languages = array_map('sanitize_key', $languages);
        return array_values(array_unique(array_filter($languages)));
    }

    public function rows(array $languages = [], string $mode = 'all'): array
    {
        global $wpdb;
        $languages = $this->normalize_languages($languages);
        if (! $languages) {
            $languages = array_map('strval', (array) $wpdb->get_col(
                $wpdb->prepare('SELECT code FROM %i WHERE is_active = 1 AND is_default = 0 ORDER BY native_name ASC', Tables::languages())
            ));
        }
        $mode = sanitize_key($mode ?: 'all');
        $missingOnly = in_array($mode, ['missing', 'new'], true);

        if ($languages) {
            $placeholders = implode(',', array_fill(0, count($languages), '%s'));
            $where = $missingOnly ? ' WHERE (t.id IS NULL OR t.translated_text = "")' : '';
            $sql = 'SELECT s.hash, s.source_type, s.source_id, s.context, s.original_text, l.code as language_code, COALESCE(t.translated_text, "") as translated_text, COALESCE(t.status, "new") as status FROM %i s INNER JOIN %i l ON l.code IN (' . $placeholders . ') LEFT JOIN %i t ON s.id = t.string_id AND t.language_code = l.code' . $where . ' ORDER BY s.id DESC, l.code ASC LIMIT 10000';
            $params = array_merge([Tables::strings(), Tables::languages(), Tables::translations()], $languages);
            return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
        }

        $where = $missingOnly ? ' WHERE (t.id IS NULL OR t.translated_text = "")' : '';
        $sql = 'SELECT s.hash, s.source_type, s.source_id, s.context, s.original_text, COALESCE(t.language_code, "") as language_code, COALESCE(t.translated_text, "") as translated_text, COALESCE(t.status, s.status) as status FROM %i s LEFT JOIN %i t ON s.id = t.string_id' . $where . ' ORDER BY s.id DESC LIMIT 5000';
        return $wpdb->get_results($wpdb->prepare($sql, Tables::strings(), Tables::translations()), ARRAY_A) ?: [];
    }

    public function csv_string(array $languages = [], string $mode = 'all'): string
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://temp is used to generate CSV output in memory.
        $handle = fopen('php://temp', 'r+');
        if (! is_resource($handle)) {
            return '';
        }

        fputcsv($handle, ['hash', 'source_type', 'source_id', 'context', 'original_text', 'language_code', 'translated_text', 'status'], ',', '"', '');
        foreach ($this->rows($languages, $mode) as $row) {
            foreach ($row as $field => $value) {
                if (is_scalar($value)) {
                    $row[$field] = Formatting::csv_cell((string) $value);
                }
            }
            fputcsv($handle, $row, ',', '"', '');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://temp CSV stream.
        fclose($handle);
        return $csv;
    }
}
