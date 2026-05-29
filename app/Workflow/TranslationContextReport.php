<?php

declare(strict_types=1);

namespace Webactueel\Translate\Workflow;

use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic SQL only uses plugin-owned table identifiers and allow-listed clauses.

final class TranslationContextReport
{
    use ValidatesLanguages;

    private const MAX_LIMIT = 50;

    /** @return array<string, mixed> */
    public static function for_language(string $languageCode, int $limit = 20): array
    {
        $languageCode = Input::key($languageCode);
        $validator = new self();
        if (! $validator->is_translatable_language($languageCode)) {
            return [
                'language' => $languageCode,
                'valid' => false,
                'message' => __('Kies een actieve niet-standaardtaal om contextwaarschuwingen te controleren.', 'webactueel-translate-language-dropdowns'),
                'summary' => [],
                'conflicts' => [],
                'reused_sources' => [],
            ];
        }

        $limit = max(1, min(self::MAX_LIMIT, absint($limit ?: 20)));
        $conflicts = self::conflicting_translation_groups($languageCode, $limit);
        $reusedSources = self::reused_source_groups($languageCode, $limit);

        return [
            'language' => $languageCode,
            'valid' => true,
            'message' => empty($conflicts)
                ? __('Geen conflicterende hergebruikte vertalingen gevonden binnen de huidige selectie.', 'webactueel-translate-language-dropdowns')
                : __('Controleer hergebruikte bronteksten met meerdere vertaalvarianten voordat je publiceert.', 'webactueel-translate-language-dropdowns'),
            'summary' => [
                'conflict_groups' => count($conflicts),
                'reused_source_groups' => count($reusedSources),
                'limit' => $limit,
            ],
            'conflicts' => $conflicts,
            'reused_sources' => $reusedSources,
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function conflicting_translation_groups(string $languageCode, int $limit): array
    {
        global $wpdb;
        $stringsTable = Tables::sql_identifier(Tables::strings());
        $translationsTable = Tables::sql_identifier(Tables::translations());

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    MD5(s.normalized_text) AS normalized_hash,
                    MIN(LEFT(s.original_text, 240)) AS source_preview,
                    COUNT(DISTINCT s.id) AS string_count,
                    COUNT(DISTINCT CONCAT(COALESCE(s.source_type, ''), '|', COALESCE(s.context, ''), '|', COALESCE(s.source_key, ''))) AS context_count,
                    COUNT(DISTINCT TRIM(COALESCE(t.translated_text, ''))) AS translation_variants,
                    GROUP_CONCAT(DISTINCT t.status ORDER BY t.status SEPARATOR ',') AS statuses,
                    MAX(s.last_seen_at) AS last_seen_at
                FROM `{$stringsTable}` s
                INNER JOIN `{$translationsTable}` t ON t.string_id = s.id AND t.language_code = %s AND TRIM(COALESCE(t.translated_text, '')) <> ''
                WHERE TRIM(COALESCE(s.normalized_text, '')) <> ''
                GROUP BY MD5(s.normalized_text)
                HAVING context_count > 1 AND translation_variants > 1
                ORDER BY translation_variants DESC, context_count DESC, last_seen_at DESC
                LIMIT %d",
                $languageCode,
                $limit
            ),
            ARRAY_A
        );

        return self::normalize_rows(is_array($rows) ? $rows : []);
    }

    /** @return list<array<string, mixed>> */
    private static function reused_source_groups(string $languageCode, int $limit): array
    {
        global $wpdb;
        $stringsTable = Tables::sql_identifier(Tables::strings());
        $translationsTable = Tables::sql_identifier(Tables::translations());

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    MD5(s.normalized_text) AS normalized_hash,
                    MIN(LEFT(s.original_text, 240)) AS source_preview,
                    COUNT(DISTINCT s.id) AS string_count,
                    COUNT(DISTINCT CONCAT(COALESCE(s.source_type, ''), '|', COALESCE(s.context, ''), '|', COALESCE(s.source_key, ''))) AS context_count,
                    COUNT(DISTINCT TRIM(COALESCE(t.translated_text, ''))) AS translation_variants,
                    GROUP_CONCAT(DISTINCT s.source_type ORDER BY s.source_type SEPARATOR ',') AS source_types,
                    MAX(s.last_seen_at) AS last_seen_at
                FROM `{$stringsTable}` s
                LEFT JOIN `{$translationsTable}` t ON t.string_id = s.id AND t.language_code = %s AND TRIM(COALESCE(t.translated_text, '')) <> ''
                WHERE TRIM(COALESCE(s.normalized_text, '')) <> ''
                GROUP BY MD5(s.normalized_text)
                HAVING context_count > 1
                ORDER BY context_count DESC, string_count DESC, last_seen_at DESC
                LIMIT %d",
                $languageCode,
                $limit
            ),
            ARRAY_A
        );

        return self::normalize_rows(is_array($rows) ? $rows : []);
    }

    /** @param array<int, array<string, mixed>> $rows @return list<array<string, mixed>> */
    private static function normalize_rows(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'normalized_hash' => sanitize_text_field(Input::scalar_string($row['normalized_hash'] ?? '')),
                'source_preview' => sanitize_text_field(Input::scalar_string($row['source_preview'] ?? '')),
                'string_count' => absint($row['string_count'] ?? 0),
                'context_count' => absint($row['context_count'] ?? 0),
                'translation_variants' => absint($row['translation_variants'] ?? 0),
                'statuses' => sanitize_text_field(Input::scalar_string($row['statuses'] ?? '')),
                'source_types' => sanitize_text_field(Input::scalar_string($row['source_types'] ?? '')),
                'last_seen_at' => sanitize_text_field(Input::scalar_string($row['last_seen_at'] ?? '')),
            ];
        }

        return $items;
    }
}
