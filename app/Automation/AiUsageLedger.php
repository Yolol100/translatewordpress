<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation;

use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Formatting;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned reporting table.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifiers come from plugin-owned helpers.

final class AiUsageLedger
{
    /** @param array<string, mixed> $data */
    public static function record(array $data): void
    {
        global $wpdb;
        $sourceChars = self::string_length(Input::scalar_string($data['source_text'] ?? ''));
        $outputChars = self::string_length(Input::scalar_string($data['translated_text'] ?? ''));
        $wpdb->insert(Tables::ai_usage(), [
            'job_id' => absint($data['job_id'] ?? 0),
            'string_id' => absint($data['string_id'] ?? 0),
            'user_id' => function_exists('get_current_user_id') ? absint(get_current_user_id()) : 0,
            'provider' => sanitize_key(Input::scalar_string($data['provider'] ?? 'unknown')),
            'model' => sanitize_text_field(Input::scalar_string($data['model'] ?? '')),
            'source_language' => sanitize_key(Input::scalar_string($data['source_language'] ?? '')),
            'target_language' => sanitize_key(Input::scalar_string($data['target_language'] ?? '')),
            'source_chars' => $sourceChars,
            'output_chars' => $outputChars,
            'estimated_words' => self::estimate_words(Input::scalar_string($data['source_text'] ?? '')),
            'memory_reused' => ! empty($data['memory_reused']) ? 1 : 0,
            'glossary_terms' => absint($data['glossary_terms'] ?? 0),
            'created_at' => current_time('mysql'),
        ]);
    }

    /** @return array<string, mixed> */
    public static function summary(int $days = 30): array
    {
        global $wpdb;
        $days = max(1, min(365, absint($days)));
        $table = Tables::ai_usage();
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS calls, COALESCE(SUM(source_chars),0) AS source_chars, COALESCE(SUM(output_chars),0) AS output_chars, COALESCE(SUM(estimated_words),0) AS estimated_words, COALESCE(SUM(memory_reused),0) AS memory_reused, COALESCE(SUM(glossary_terms),0) AS glossary_terms FROM %i WHERE created_at >= %s",
            $table,
            $since
        ), ARRAY_A);
        $byLanguage = $wpdb->get_results($wpdb->prepare(
            "SELECT target_language, COUNT(*) AS calls, COALESCE(SUM(estimated_words),0) AS estimated_words, COALESCE(SUM(memory_reused),0) AS memory_reused FROM %i WHERE created_at >= %s GROUP BY target_language ORDER BY calls DESC LIMIT 20",
            $table,
            $since
        ), ARRAY_A);

        return [
            'days' => $days,
            'calls' => absint($row['calls'] ?? 0),
            'source_chars' => absint($row['source_chars'] ?? 0),
            'output_chars' => absint($row['output_chars'] ?? 0),
            'estimated_words' => absint($row['estimated_words'] ?? 0),
            'memory_reused' => absint($row['memory_reused'] ?? 0),
            'glossary_terms' => absint($row['glossary_terms'] ?? 0),
            'by_language' => array_map(static function (array $item): array {
                return [
                    'target_language' => sanitize_key((string) ($item['target_language'] ?? '')),
                    'calls' => absint($item['calls'] ?? 0),
                    'estimated_words' => absint($item['estimated_words'] ?? 0),
                    'memory_reused' => absint($item['memory_reused'] ?? 0),
                ];
            }, is_array($byLanguage) ? $byLanguage : []),
        ];
    }

    /**
     * Per-line-item usage rows for a period, newest first, for client billing exports.
     *
     * Each row is a single AI translation call with provider/model, language pair,
     * volume (chars/words), and memory/glossary flags so an agency can attribute and
     * re-bill AI spend per client site.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function export_rows(int $days = 30, string $targetLanguage = '', int $limit = 5000): array
    {
        global $wpdb;
        $days = max(1, min(365, absint($days)));
        $limit = max(1, min(50000, absint($limit)));
        $targetLanguage = sanitize_key($targetLanguage);
        $table = Tables::ai_usage();
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        $where = 'created_at >= %s';
        $params = [$table, $since];
        if ($targetLanguage !== '') {
            $where .= ' AND target_language = %s';
            $params[] = $targetLanguage;
        }
        $params[] = $limit;

        $sql = "SELECT created_at, job_id, string_id, provider, model, source_language, target_language, source_chars, output_chars, estimated_words, memory_reused, glossary_terms FROM %i WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Plugin-owned table name; WHERE is built from a fixed allow-list with placeholders.

        return is_array($rows) ? $rows : [];
    }

    /**
     * Build a billing-ready CSV string of usage line items plus a summary footer.
     */
    public static function export_csv(int $days = 30, string $targetLanguage = ''): string
    {
        $rows = self::export_rows($days, $targetLanguage);
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, self::usage_csv_columns(), ',', '"', '');
        $totals = self::write_usage_csv_rows($handle, $rows);
        self::write_usage_csv_summary($handle, $rows, $totals);

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private static function usage_csv_columns(): array
    {
        return ['date', 'job_id', 'string_id', 'provider', 'model', 'source_language', 'target_language', 'source_chars', 'output_chars', 'estimated_words', 'memory_reused', 'glossary_terms'];
    }

    private static function write_usage_csv_rows($handle, array $rows): array
    {
        $totals = ['words' => 0, 'source_chars' => 0, 'output_chars' => 0, 'memory_reused' => 0];
        foreach ($rows as $row) {
            fputcsv($handle, self::usage_csv_row($row), ',', '"', '');
            $totals['words'] += absint($row['estimated_words'] ?? 0);
            $totals['source_chars'] += absint($row['source_chars'] ?? 0);
            $totals['output_chars'] += absint($row['output_chars'] ?? 0);
            $totals['memory_reused'] += absint($row['memory_reused'] ?? 0);
        }

        return $totals;
    }

    private static function usage_csv_row(array $row): array
    {
        return [
            Formatting::csv_cell((string) ($row['created_at'] ?? '')),
            absint($row['job_id'] ?? 0),
            absint($row['string_id'] ?? 0),
            Formatting::csv_cell((string) ($row['provider'] ?? '')),
            Formatting::csv_cell((string) ($row['model'] ?? '')),
            Formatting::csv_cell((string) ($row['source_language'] ?? '')),
            Formatting::csv_cell((string) ($row['target_language'] ?? '')),
            absint($row['source_chars'] ?? 0),
            absint($row['output_chars'] ?? 0),
            absint($row['estimated_words'] ?? 0),
            absint($row['memory_reused'] ?? 0),
            absint($row['glossary_terms'] ?? 0),
        ];
    }

    private static function write_usage_csv_summary($handle, array $rows, array $totals): void
    {
        // Summary footer for quick invoicing without re-aggregating in a spreadsheet.
        fputcsv($handle, [], ',', '"', '');
        fputcsv($handle, ['TOTAL', count($rows), '', '', '', '', '', $totals['source_chars'], $totals['output_chars'], $totals['words'], $totals['memory_reused'], ''], ',', '"', '');
    }

    private static function estimate_words(string $text): int
    {
        $plain = trim(wp_strip_all_tags($text));
        if ($plain === '') {
            return 0;
        }
        $parts = preg_split('/\s+/u', $plain);
        return is_array($parts) ? count(array_filter($parts, static fn($part): bool => $part !== '')) : 0;
    }

    private static function string_length(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }
}
