<?php

declare(strict_types=1);

namespace Webactueel\Translate\Translation\Concerns;

use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This plugin uses its own custom translation tables; queries are scoped and cache invalidation is handled by the plugin.

trait TranslationQueries
{
    public function get_strings(array $args = []): array
    {
        global $wpdb;
        $page = max(1, Input::absint($args['page'] ?? 1, 1));
        $perPage = max(1, min(500, Input::absint($args['per_page'] ?? 20, 20)));
        $offset = ($page - 1) * $perPage;
        $language = Input::key($args['language'] ?? '');
        $status = Input::key($args['status'] ?? '');
        $where = ['1=1'];
        $params = [];
        $joinFilter = '';
        $effectiveStatusSql = 's.status AS effective_status';

        if ($language !== '') {
            $translationsFilterTable = Tables::sql_identifier(Tables::translations());
            $joinFilter = " LEFT JOIN `{$translationsFilterTable}` tf ON s.id = tf.string_id AND tf.language_code = %s";
            $effectiveStatusSql = 'CASE WHEN tf.id IS NULL OR TRIM(COALESCE(tf.translated_text, "")) = "" THEN "new" ELSE COALESCE(tf.status, "new") END AS effective_status';
            $params[] = $language;
        }

        if (! empty($args['search'])) {
            $where[] = '(s.original_text LIKE %s OR s.context LIKE %s OR s.source_key LIKE %s)';
            $needle = '%' . $wpdb->esc_like(Input::text($args['search'])) . '%';
            $params[] = $needle;
            $params[] = $needle;
            $params[] = $needle;
        }

        if ($status !== '') {
            if ($language !== '') {
                if (in_array($status, ['new', 'missing'], true)) {
                    $where[] = '(tf.id IS NULL OR tf.translated_text = "")';
                } else {
                    $where[] = 'tf.status = %s';
                    $params[] = $status;
                }
            } elseif (in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review'], true)) {
                $where[] = 'EXISTS (SELECT 1 FROM `' . Tables::sql_identifier(Tables::translations()) . '` tx WHERE tx.string_id = s.id AND tx.status = %s)';
                $params[] = $status;
            } else {
                $where[] = 's.status = %s';
                $params[] = $status;
            }
        }

        if (! empty($args['source_type'])) {
            $where[] = 's.source_type = %s';
            $params[] = Input::key($args['source_type']);
        }

        $whereSql = implode(' AND ', $where);
        $stringsTable = Tables::sql_identifier(Tables::strings());
        $translationsTable = Tables::sql_identifier(Tables::translations());
        $countSql = "SELECT COUNT(DISTINCT s.id) FROM `{$stringsTable}` s{$joinFilter} WHERE {$whereSql}";
        $total = $params ? (int) $wpdb->get_var($wpdb->prepare($countSql, $params)) : (int) $wpdb->get_var($countSql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic parts are escaped plugin-owned table names and a whitelist-built WHERE clause.

        $sql = "SELECT s.*, {$effectiveStatusSql}, GROUP_CONCAT(CONCAT(t.language_code, ':', t.status, ':', t.origin) SEPARATOR ', ') AS translation_summary FROM `{$stringsTable}` s{$joinFilter} LEFT JOIN `{$translationsTable}` t ON s.id = t.string_id WHERE {$whereSql} GROUP BY s.id ORDER BY s.last_seen_at DESC, s.id DESC LIMIT %d OFFSET %d";
        $queryParams = array_merge($params, [$perPage, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($sql, $queryParams), ARRAY_A) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic parts are escaped plugin-owned table names and a whitelist-built WHERE clause.
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }


    public function translate_text(string $text, string $languageCode): string
    {
        $languageCode = Input::key($languageCode);
        if ($text === '' || $languageCode === '') {
            return $text;
        }

        $normalized = \Webactueel\Translate\Translation\StringNormalizer::normalize($text);
        if ($normalized === '') {
            return $text;
        }

        $map = $this->translation_map($languageCode);
        return isset($map[$normalized]) && is_string($map[$normalized]) && $map[$normalized] !== '' ? $map[$normalized] : $text;
    }

    public function get_translations_for_string(int $stringId): array
    {
        global $wpdb;
        $translationsTable = Tables::sql_identifier(Tables::translations());
        $sql = "SELECT language_code, translated_text, status, origin, updated_at FROM `{$translationsTable}` WHERE string_id = %d ORDER BY language_code ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, absint($stringId)), ARRAY_A) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is generated from the plugin-owned translations table helper.
        $items = [];
        foreach ($rows as $row) {
            $code = Input::key($row['language_code'] ?? '');
            if ($code !== '') {
                $items[$code] = $row;
            }
        }
        return $items;
    }
}
