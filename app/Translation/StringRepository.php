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

final class StringRepository
{
    use ValidatesLanguages;

    public function upsert(string $text, string $sourceType = '', int $sourceId = 0, string $context = '', string $sourceKey = ''): int
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

        if (in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review', 'outdated'], true)) {
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
}
