<?php

declare(strict_types=1);

namespace Webactueel\Translate\Translation;

use Webactueel\Translate\Cache\TranslationCache;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned custom tables; table identifiers are prepared with %i placeholders.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

final class TranslationRepository
{
    use ValidatesLanguages;

    public function upsert_string(string $text, string $sourceType = '', int $sourceId = 0, string $context = '', string $sourceKey = ''): int
    {
        global $wpdb;
        if (StringNormalizer::should_skip($text)) {
            return 0;
        }
        $normalized = StringNormalizer::normalize($text);
        $hash = StringNormalizer::hash($normalized, $context ?: $sourceType);
        $now = current_time('mysql');
        $table = Tables::strings();
        $id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE hash = %s', $table, $hash));
        if ($id) {
            $wpdb->update($table, ['last_seen_at' => $now, 'updated_at' => $now], ['id' => $id]);
            return $id;
        }
        $wpdb->insert($table, [
            'hash' => $hash,
            'original_text' => $text,
            'normalized_text' => $normalized,
            'context' => sanitize_text_field($context),
            'source_type' => sanitize_key($sourceType),
            'source_id' => absint($sourceId),
            'source_key' => sanitize_text_field($sourceKey),
            'status' => 'new',
            'created_at' => $now,
            'updated_at' => $now,
            'last_seen_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    public function save_translation(int $stringId, string $languageCode, string $translatedText, string $status = 'published', string $origin = 'manual'): bool
    {
        global $wpdb;
        $languageCode = sanitize_key($languageCode);
        if (! $this->is_translatable_language($languageCode)) {
            return false;
        }
        $status = sanitize_key($status);
        $translatedText = trim(wp_kses_post($translatedText));
        if ($translatedText !== '' && ! in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review', 'outdated'], true)) {
            $status = 'published';
        }
        if ($translatedText === '' && in_array($status, ['published', 'reviewed'], true)) {
            $status = 'draft';
        }
        if (! in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review', 'outdated'], true)) {
            $status = 'draft';
        }
        $origin = sanitize_key($origin ?: 'manual');
        $now = current_time('mysql');
        $table = Tables::translations();
        $exists = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE string_id = %d AND language_code = %s', $table, $stringId, $languageCode));
        $data = [
            'string_id' => absint($stringId),
            'language_code' => $languageCode,
            'translated_text' => $translatedText,
            'status' => $status,
            'origin' => $origin,
            'updated_at' => $now,
        ];
        $ok = false;
        if ($exists) {
            $ok = $wpdb->update($table, $data, ['id' => $exists]) !== false;
        } else {
            $data['created_at'] = $now;
            $ok = $wpdb->insert($table, $data) !== false;
        }
        if ($ok) {
            TranslationCache::bump();
            do_action('wat_after_translation_saved', absint($stringId), $languageCode);
        }
        return $ok;
    }

    public function get_strings(array $args = []): array
    {
        global $wpdb;

        $page = max(1, Input::absint($args['page'] ?? 1, 1));
        $perPage = max(1, min(500, Input::absint($args['per_page'] ?? 20, 20)));
        $offset = ($page - 1) * $perPage;
        $filter = $this->string_query_filter($args);
        $whereSql = implode(' AND ', $filter['where']);

        $total = $this->count_strings_for_filter($filter, $whereSql);
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, {$filter['effective_status_sql']}, GROUP_CONCAT(CONCAT(t.language_code, ':', t.status, ':', t.origin) SEPARATOR ', ') AS translation_summary FROM %i s{$filter['join_filter']} LEFT JOIN %i t ON s.id = t.string_id WHERE {$whereSql} GROUP BY s.id ORDER BY s.last_seen_at DESC, s.id DESC LIMIT %d OFFSET %d",
                array_merge([Tables::strings()], $filter['join_params'], [Tables::translations()], $filter['where_params'], [$perPage, $offset])
            ),
            ARRAY_A
        ) ?: [];

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    private function count_strings_for_filter(array $filter, string $whereSql): int
    {
        global $wpdb;

        $countSql = "SELECT COUNT(DISTINCT s.id) FROM %i s{$filter['join_filter']} WHERE {$whereSql}";
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                $countSql,
                array_merge([Tables::strings()], $filter['join_params'], $filter['where_params'])
            )
        );
    }

    private function string_query_filter(array $args): array
    {
        $language = Input::key($args['language'] ?? '');
        $status = Input::key($args['status'] ?? '');
        $filter = $this->base_string_query_filter($language);

        $this->apply_string_search_filter($filter, $args);
        $this->apply_string_status_filter($filter, $status, $language);
        $this->apply_string_source_type_filter($filter, $args);

        return $filter;
    }

    private function base_string_query_filter(string $language): array
    {
        if ($language === '') {
            return [
                'where' => ['1=1'],
                'join_filter' => '',
                'join_params' => [],
                'where_params' => [],
                'effective_status_sql' => 's.status AS effective_status',
            ];
        }

        return [
            'where' => ['1=1'],
            'join_filter' => ' LEFT JOIN %i tf ON s.id = tf.string_id AND tf.language_code = %s',
            'join_params' => [Tables::translations(), $language],
            'where_params' => [],
            'effective_status_sql' => 'CASE WHEN tf.id IS NULL OR TRIM(COALESCE(tf.translated_text, "")) = "" THEN "new" ELSE COALESCE(tf.status, "new") END AS effective_status',
        ];
    }

    private function apply_string_search_filter(array &$filter, array $args): void
    {
        global $wpdb;

        if (empty($args['search'])) {
            return;
        }

        $filter['where'][] = '(s.original_text LIKE %s OR s.context LIKE %s OR s.source_key LIKE %s)';
        $needle = '%' . $wpdb->esc_like(Input::text($args['search'])) . '%';
        array_push($filter['where_params'], $needle, $needle, $needle);
    }

    private function apply_string_status_filter(array &$filter, string $status, string $language): void
    {
        if ($status === '') {
            return;
        }

        if ($language !== '') {
            $this->apply_language_status_filter($filter, $status);
            return;
        }

        if (in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review'], true)) {
            $filter['where'][] = 'EXISTS (SELECT 1 FROM %i tx WHERE tx.string_id = s.id AND tx.status = %s)';
            array_push($filter['where_params'], Tables::translations(), $status);
            return;
        }

        $filter['where'][] = 's.status = %s';
        $filter['where_params'][] = $status;
    }

    private function apply_language_status_filter(array &$filter, string $status): void
    {
        if (in_array($status, ['new', 'missing'], true)) {
            $filter['where'][] = '(tf.id IS NULL OR tf.translated_text = "")';
            return;
        }

        $filter['where'][] = 'tf.status = %s';
        $filter['where_params'][] = $status;
    }

    private function apply_string_source_type_filter(array &$filter, array $args): void
    {
        if (empty($args['source_type'])) {
            return;
        }

        $filter['where'][] = 's.source_type = %s';
        $filter['where_params'][] = Input::key($args['source_type']);
    }

    public function translate_text(string $text, string $languageCode): string
    {
        $languageCode = Input::key($languageCode);
        if ($text === '' || $languageCode === '') {
            return $text;
        }

        $normalized = StringNormalizer::normalize($text);
        if ($normalized === '') {
            return $text;
        }

        $map = $this->translation_map($languageCode);
        return isset($map[$normalized]) && is_string($map[$normalized]) && $map[$normalized] !== '' ? $map[$normalized] : $text;
    }

    public function get_translations_for_string(int $stringId): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT language_code, translated_text, status, origin, updated_at FROM %i WHERE string_id = %d ORDER BY language_code ASC',
                Tables::translations(),
                absint($stringId)
            ),
            ARRAY_A
        ) ?: [];
        $items = [];
        foreach ($rows as $row) {
            $code = Input::key($row['language_code'] ?? '');
            if ($code !== '') {
                $items[$code] = $row;
            }
        }
        return $items;
    }

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
        $stringsTable = Tables::strings();
        $translationsTable = Tables::translations();
        $source = $wpdb->get_row($wpdb->prepare('SELECT normalized_text FROM %i WHERE id = %d LIMIT 1', $stringsTable, absint($sourceStringId)), ARRAY_A);
        $normalized = Input::scalar_string($source['normalized_text'] ?? '');
        if ($normalized === '') {
            return 0;
        }
        $now = current_time('mysql');
        $sql = "INSERT INTO %i (string_id, language_code, translated_text, status, origin, created_at, updated_at)
            SELECT s.id, %s, %s, %s, 'memory', %s, %s
            FROM %i s
            LEFT JOIN %i existing ON existing.string_id = s.id AND existing.language_code = %s
            WHERE s.normalized_text = %s AND s.id <> %d AND existing.id IS NULL";
        $result = $wpdb->query($wpdb->prepare($sql, $translationsTable, $languageCode, $translatedText, $memoryStatus, $now, $now, $stringsTable, $translationsTable, $languageCode, $normalized, absint($sourceStringId)));
        if ($result && $result > 0) {
            TranslationCache::bump();
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
        $normalized = StringNormalizer::normalize($originalText);
        if ($normalized === '') {
            return [];
        }
        $stringsTable = Tables::strings();
        $translationsTable = Tables::translations();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT t.translated_text, t.status, t.origin, s.id AS source_string_id FROM %i s INNER JOIN %i t ON t.string_id = s.id WHERE s.normalized_text = %s AND t.language_code = %s AND t.status IN ('reviewed','published') AND TRIM(t.translated_text) <> '' ORDER BY t.updated_at DESC, t.id DESC LIMIT 1",
            $stringsTable,
            $translationsTable,
            $normalized,
            $languageCode
        ), ARRAY_A);
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
        $languageCode = sanitize_key($languageCode);
        if (! $this->is_translatable_language($languageCode)) {
            return [];
        }
        $cached = TranslationCache::get($languageCode);
        if (is_array($cached)) {
            return $cached;
        }

        $map = $this->build_translation_map_from_rows(
            $this->translation_map_rows($languageCode),
            (new GlossaryRepository())->all($languageCode)
        );
        $map = (array) apply_filters('wat_translation_map', $map, $languageCode);
        TranslationCache::set($languageCode, $map);
        return $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function translation_map_rows(string $languageCode): array
    {
        global $wpdb;

        $limit = (int) apply_filters('wat_translation_map_limit', 10000, $languageCode);
        $limit = max(100, min(50000, $limit));
        $sql = "SELECT s.normalized_text, t.translated_text
            FROM %i s
            INNER JOIN (
                SELECT string_id, MAX(id) AS latest_id
                FROM %i
                WHERE language_code = %s
                AND status IN ('published', 'reviewed')
                AND translated_text <> ''
                GROUP BY string_id
            ) latest ON latest.string_id = s.id
            INNER JOIN %i t ON t.id = latest.latest_id
            ORDER BY s.id ASC
            LIMIT %d";

        return $wpdb->get_results($wpdb->prepare($sql, Tables::strings(), Tables::translations(), $languageCode, Tables::translations(), $limit), ARRAY_A) ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $glossaryTerms
     * @return array<string, string>
     */
    private function build_translation_map_from_rows(array $rows, array $glossaryTerms): array
    {
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

        return $map;
    }
}
