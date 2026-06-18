<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation;

use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic SQL only uses plugin-owned table identifiers and allow-listed clauses.

final class TranslationCandidateSelector
{
    /** @param array<string, mixed> $options */
    public function count_candidates(array $options): int
    {
        global $wpdb;
        [$joinSql, $whereSql, $params] = $this->candidate_sql_parts($options, 0);
        $stringsTable = Tables::strings();
        $sql = "SELECT COUNT(DISTINCT s.id) FROM %i s {$joinSql} WHERE {$whereSql}";
        array_unshift($params, $stringsTable);
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /** @param array<string, mixed> $options @return array<int, array<string, mixed>> */
    public function candidate_strings(array $options, int $cursor, int $batchSize): array
    {
        global $wpdb;
        [$joinSql, $whereSql, $params] = $this->candidate_sql_parts($options, $cursor);
        $stringsTable = Tables::strings();
        $sql = "SELECT s.id, s.original_text FROM %i s {$joinSql} WHERE {$whereSql} ORDER BY s.id ASC LIMIT %d";
        array_unshift($params, $stringsTable);
        $params[] = $batchSize;
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, mixed> $options @return array{0:string,1:string,2:array<int, mixed>} */
    private function candidate_sql_parts(array $options, int $cursor): array
    {
        $translationsTable = Tables::translations();
        $targetLanguage = Input::key($options['target_language'] ?? '');
        $status = Input::key($options['status'] ?? 'new');
        $params = [$translationsTable, $targetLanguage, max(0, $cursor), TranslationJobLimits::MAX_AI_TEXT_LENGTH];
        $where = ['s.id > %d', 'TRIM(s.original_text) <> ""', 'CHAR_LENGTH(s.original_text) <= %d'];
        $joinSql = "LEFT JOIN %i t ON t.string_id = s.id AND t.language_code = %s";

        if (in_array($status, ['new', 'missing'], true)) {
            $where[] = '(t.id IS NULL OR TRIM(COALESCE(t.translated_text, "")) = "")';
        } else {
            $where[] = 't.status = %s';
            $params[] = $status;
        }

        return [$joinSql, implode(' AND ', $where), $params];
    }
}
