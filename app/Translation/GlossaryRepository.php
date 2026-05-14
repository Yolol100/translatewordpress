<?php

declare(strict_types=1);

namespace Webactueel\Translate\Translation;

use Webactueel\Translate\Cache\CacheInvalidator;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This plugin uses its own custom translation tables; queries are scoped and cache invalidation is handled by the plugin.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: custom prefixed tables and public wat_* hooks are intentional.

final class GlossaryRepository
{
    use ValidatesLanguages;
    public function all(string $languageCode = ''): array
    {
        global $wpdb;
        $table = esc_sql(Tables::glossary());
        $languageCode = sanitize_key($languageCode);
        if ($languageCode !== '') {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$table}` WHERE language_code = %s ORDER BY source_term ASC, id DESC", $languageCode), ARRAY_A) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else {
            $rows = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY language_code ASC, source_term ASC, id DESC", ARRAY_A) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return array_map([$this, 'normalize_row'], $rows);
    }

    public function save(array $input): array
    {
        global $wpdb;
        $id = Input::absint($input['id'] ?? 0);
        $source = $this->normalize_term(Input::scalar_string($input['source_term'] ?? ''));
        $target = $this->normalize_term(Input::scalar_string($input['target_term'] ?? ''));
        $language = Input::key($input['language_code'] ?? '');
        if ($source === '' || $target === '' || $language === '') {
            return ['error' => __('Bronterm, vertaling en taal zijn verplicht.', 'webactueel-translate-language-dropdowns')];
        }
        if (! $this->is_translatable_language($language)) {
            return ['error' => __('Kies een actieve niet-standaardtaal voor woordenlijst-items.', 'webactueel-translate-language-dropdowns')];
        }
        $caseSensitive = ! empty($input['case_sensitive']) ? 1 : 0;
        $duplicateId = $this->find_duplicate_id($source, $language, (bool) $caseSensitive, $id);
        if ($duplicateId > 0) {
            return ['error' => __('Deze bronterm bestaat al voor deze taal en hoofdlettergevoeligheid.', 'webactueel-translate-language-dropdowns')];
        }

        $now = current_time('mysql');
        $data = [
            'source_term' => $source,
            'target_term' => $target,
            'language_code' => $language,
            'case_sensitive' => $caseSensitive,
            'updated_at' => $now,
        ];
        $table = Tables::glossary();
        if ($id > 0) {
            $ok = $wpdb->update($table, $data, ['id' => $id]) !== false;
        } else {
            $data['created_at'] = $now;
            $ok = $wpdb->insert($table, $data) !== false;
            $id = (int) $wpdb->insert_id;
        }
        if (! $ok) {
            return ['error' => __('Woordenlijst opslaan mislukt.', 'webactueel-translate-language-dropdowns')];
        }
        CacheInvalidator::bump();
        return ['saved' => true, 'item' => $this->get($id)];
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        if ($id <= 0) {
            return false;
        }
        $deleted = $wpdb->delete(Tables::glossary(), ['id' => $id]);
        if ($deleted && $deleted > 0) {
            CacheInvalidator::bump();
            return true;
        }
        return false;
    }

    public function matches(string $text, string $languageCode): array
    {
        $languageCode = sanitize_key($languageCode);
        if ($text === '' || $languageCode === '') {
            return [];
        }
        $matches = [];
        foreach ($this->all($languageCode) as $row) {
            $term = Input::scalar_string($row['source_term'] ?? '');
            if ($term === '') {
                continue;
            }
            $found = ! empty($row['case_sensitive'])
                ? strpos($text, $term) !== false
                : stripos($text, $term) !== false;
            if ($found) {
                $matches[] = $row;
            }
        }
        return $matches;
    }

    private function normalize_term(string $term): string
    {
        $term = preg_replace('/\s+/u', ' ', trim(wp_strip_all_tags($term)));
        return sanitize_text_field(is_string($term) ? $term : '');
    }

    private function find_duplicate_id(string $source, string $language, bool $caseSensitive, int $excludeId = 0): int
    {
        global $wpdb;
        $table = esc_sql(Tables::glossary());
        $where = '`language_code` = %s AND `case_sensitive` = %d';
        $params = [$language, $caseSensitive ? 1 : 0];
        if ($caseSensitive) {
            $where .= ' AND `source_term` = %s';
            $params[] = $source;
        } else {
            $where .= ' AND LOWER(`source_term`) = LOWER(%s)';
            $params[] = $source;
        }
        if ($excludeId > 0) {
            $where .= ' AND `id` <> %d';
            $params[] = $excludeId;
        }
        $sql = "SELECT id FROM `{$table}` WHERE {$where} LIMIT 1"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from plugin-owned helper; WHERE clause is whitelist-built above.
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared from whitelist-built SQL with placeholders and sanitized table name.
    }


    private function get(int $id): array
    {
        global $wpdb;
        $table = esc_sql(Tables::glossary());
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $this->normalize_row(is_array($row) ? $row : []);
    }

    private function normalize_row(array $row): array
    {
        return [
            'id' => absint($row['id'] ?? 0),
            'source_term' => Input::scalar_string($row['source_term'] ?? ''),
            'target_term' => Input::scalar_string($row['target_term'] ?? ''),
            'language_code' => Input::key($row['language_code'] ?? ''),
            'case_sensitive' => ! empty($row['case_sensitive']),
            'updated_at' => Input::scalar_string($row['updated_at'] ?? ''),
        ];
    }
}
