<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport;

use Webactueel\Translate\Database\Tables;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This plugin uses its own custom translation tables; queries are scoped and cache invalidation is handled by the plugin.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: custom prefixed tables and public wat_* hooks are intentional.

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
                'SELECT code FROM `' . esc_sql(Tables::languages()) . '` WHERE is_active = 1 AND is_default = 0 ORDER BY native_name ASC'
            )); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        $mode = sanitize_key($mode ?: 'all');
        $missingOnly = in_array($mode, ['missing', 'new'], true);

        if ($languages) {
            $placeholders = implode(',', array_fill(0, count($languages), '%s'));
            $where = $missingOnly ? ' WHERE (t.id IS NULL OR t.translated_text = "")' : '';
            $stringsTable = esc_sql(Tables::strings());
            $languagesTable = esc_sql(Tables::languages());
            $translationsTable = esc_sql(Tables::translations());
            $sql = 'SELECT s.hash, s.source_type, s.source_id, s.context, s.original_text, l.code as language_code, COALESCE(t.translated_text, "") as translated_text, COALESCE(t.status, "new") as status FROM `' . $stringsTable . '` s INNER JOIN `' . $languagesTable . '` l ON l.code IN (' . $placeholders . ') LEFT JOIN `' . $translationsTable . '` t ON s.id = t.string_id AND t.language_code = l.code' . $where . ' ORDER BY s.id DESC, l.code ASC LIMIT 10000';
            return $wpdb->get_results($wpdb->prepare($sql, $languages), ARRAY_A) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        $where = $missingOnly ? ' WHERE (t.id IS NULL OR t.translated_text = "")' : '';
        $stringsTable = esc_sql(Tables::strings());
        $translationsTable = esc_sql(Tables::translations());
        $sql = 'SELECT s.hash, s.source_type, s.source_id, s.context, s.original_text, COALESCE(t.language_code, "") as language_code, COALESCE(t.translated_text, "") as translated_text, COALESCE(t.status, s.status) as status FROM `' . $stringsTable . '` s LEFT JOIN `' . $translationsTable . '` t ON s.id = t.string_id' . $where . ' ORDER BY s.id DESC LIMIT 5000';
        return $wpdb->get_results($sql, ARRAY_A) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    private function escape_csv_cell(string $value): string
    {
        // Guard against spreadsheet formula injection, including values where
        // a formula character is hidden behind leading whitespace or tabs.
        $trimmed = ltrim($value, " \t\r\n");
        if ($trimmed !== '' && preg_match('/^[=+\-@]/', $trimmed)) {
            return "'" . $value;
        }
        if ($value !== '' && preg_match('/^[\t\r\n]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    public function csv_string(array $languages = [], string $mode = 'all'): string
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://temp is used to generate CSV output in memory.
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['hash', 'source_type', 'source_id', 'context', 'original_text', 'language_code', 'translated_text', 'status'], ',', '"', '');
        foreach ($this->rows($languages, $mode) as $row) {
            foreach ($row as $field => $value) {
                if (is_scalar($value)) {
                    $row[$field] = $this->escape_csv_cell((string) $value);
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
