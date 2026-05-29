<?php

declare(strict_types=1);

namespace Webactueel\Translate\Translation\Concerns;

use Webactueel\Translate\Cache\CacheInvalidator;
use Webactueel\Translate\Cache\TranslationCache;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Translation\GlossaryRepository;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned custom tables; table identifiers are normalized through Tables::sql_identifier().

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.

trait TranslationMemoryAndMap
{
    public function apply_translation_memory(int $sourceStringId, string $languageCode, string $translatedText, string $status = 'reviewed'): int
    {
        global $wpdb;
        $languageCode = sanitize_key($languageCode);
        if (! $this->is_translatable_language($languageCode)) {
            return 0;
        }
        $translatedText = trim(wp_kses_post($translatedText));
        if ($sourceStringId <= 0 || $languageCode === '' || $translatedText === '') {
            return 0;
        }
        $status = in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review'], true) ? $status : 'reviewed';
        $memoryStatus = in_array($status, ['published', 'reviewed'], true) ? 'reviewed' : $status;
        $stringsTable = Tables::sql_identifier(Tables::strings());
        $translationsTable = Tables::sql_identifier(Tables::translations());
        $source = $wpdb->get_row($wpdb->prepare("SELECT normalized_text FROM `{$stringsTable}` WHERE id = %d LIMIT 1", absint($sourceStringId)), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name is escaped via esc_sql().
        $normalized = Input::scalar_string($source['normalized_text'] ?? '');
        if ($normalized === '') {
            return 0;
        }
        $now = current_time('mysql');
        $sql = "INSERT INTO `{$translationsTable}` (string_id, language_code, translated_text, status, origin, created_at, updated_at)
            SELECT s.id, %s, %s, %s, 'memory', %s, %s
            FROM `{$stringsTable}` s
            LEFT JOIN `{$translationsTable}` existing ON existing.string_id = s.id AND existing.language_code = %s
            WHERE s.normalized_text = %s AND s.id <> %d AND existing.id IS NULL";
        $result = $wpdb->query($wpdb->prepare($sql, $languageCode, $translatedText, $memoryStatus, $now, $now, $languageCode, $normalized, absint($sourceStringId))); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if ($result && $result > 0) {
            CacheInvalidator::bump();
            return (int) $result;
        }
        return 0;
    }



    /** @return array<string, mixed> */
    public function find_translation_memory_match(string $originalText, string $languageCode): array
    {
        global $wpdb;
        $languageCode = sanitize_key($languageCode);
        if (! $this->is_translatable_language($languageCode)) {
            return [];
        }
        $normalized = \Webactueel\Translate\Translation\StringNormalizer::normalize($originalText);
        if ($normalized === '') {
            return [];
        }
        $stringsTable = Tables::sql_identifier(Tables::strings());
        $translationsTable = Tables::sql_identifier(Tables::translations());
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT t.translated_text, t.status, t.origin, s.id AS source_string_id FROM `{$stringsTable}` s INNER JOIN `{$translationsTable}` t ON t.string_id = s.id WHERE s.normalized_text = %s AND t.language_code = %s AND t.status IN ('reviewed','published') AND TRIM(t.translated_text) <> '' ORDER BY t.updated_at DESC, t.id DESC LIMIT 1",
            $normalized,
            $languageCode
        ), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if (! is_array($row)) {
            return [];
        }
        return [
            'translated_text' => Input::scalar_string($row['translated_text'] ?? ''),
            'status' => Input::key($row['status'] ?? 'reviewed'),
            'origin' => Input::key($row['origin'] ?? 'memory'),
            'source_string_id' => absint($row['source_string_id'] ?? 0),
            'score' => 100,
        ];
    }


    /**
     * @param array<int, array<string, mixed>> $terms
     */
    private function apply_glossary_terms_to_text(string $text, array $terms): string
    {
        foreach ($terms as $term) {
            $source = Input::scalar_string($term['source_term'] ?? '');
            $target = Input::scalar_string($term['target_term'] ?? '');
            if ($source === '' || $target === '') {
                continue;
            }
            $text = ! empty($term['case_sensitive']) ? str_replace($source, $target, $text) : str_ireplace($source, $target, $text);
        }

        return $text;
    }

    public function translation_map(string $languageCode): array
    {
        global $wpdb;
        $languageCode = sanitize_key($languageCode);
        if (! $this->is_translatable_language($languageCode)) {
            return [];
        }
        $cached = TranslationCache::get($languageCode);
        if (is_array($cached)) {
            return $cached;
        }
        $stringsTable = Tables::sql_identifier(Tables::strings());
        $translationsTable = Tables::sql_identifier(Tables::translations());
        $limit = (int) apply_filters('wat_translation_map_limit', 10000, $languageCode);
        $limit = max(100, min(50000, $limit));
        $sql = "SELECT s.normalized_text, t.translated_text
            FROM `{$stringsTable}` s
            INNER JOIN (
                SELECT string_id, MAX(id) AS latest_id
                FROM `{$translationsTable}`
                WHERE language_code = %s
                AND status IN ('published', 'reviewed')
                AND translated_text <> ''
                GROUP BY string_id
            ) latest ON latest.string_id = s.id
            INNER JOIN `{$translationsTable}` t ON t.id = latest.latest_id
            ORDER BY s.id ASC
            LIMIT %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $languageCode, $limit), ARRAY_A) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic parts are plugin-owned table names.
        $glossaryTerms = (new GlossaryRepository())->all($languageCode);
        $map = [];
        $ambiguous = [];
        foreach ($rows as $row) {
            $normalized = Input::scalar_string($row['normalized_text'] ?? '');
            $translated = Input::scalar_string($row['translated_text'] ?? '');
            if ($normalized === '' || $translated === '' || isset($ambiguous[$normalized])) {
                continue;
            }
            $translated = $this->apply_glossary_terms_to_text($translated, $glossaryTerms);
            if (! isset($map[$normalized])) {
                $map[$normalized] = $translated;
                continue;
            }
            if ($map[$normalized] !== $translated) {
                unset($map[$normalized]);
                $ambiguous[$normalized] = true;
            }
        }
        $map = (array) apply_filters('wat_translation_map', $map, $languageCode);
        TranslationCache::set($languageCode, $map);
        return $map;
    }
}
