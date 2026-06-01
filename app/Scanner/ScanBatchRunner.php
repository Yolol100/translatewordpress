<?php

declare(strict_types=1);

namespace Webactueel\Translate\Scanner;

use Webactueel\Translate\Translation\TranslationRepository;
use Webactueel\Translate\Scanner\Concerns\ScansPostContent;
use Webactueel\Translate\Scanner\Concerns\ScannerValueHelpers;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom posts_where cursor clause uses WordPress-owned posts table.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

final class ScanBatchRunner
{
    use ScansPostContent;
    use ScannerValueHelpers;

    private TranslationRepository $repository;
    private ScanJobManager $jobs;

    public function __construct()
    {
        $this->repository = new TranslationRepository();
        $this->jobs = new ScanJobManager();
    }

    public static function post_types_for(string $type): array
    {
        if ($type === 'pages') {
            return ['page'];
        }
        if ($type === 'posts') {
            return ['post'];
        }
        if ($type === 'woocommerce' && post_type_exists('product')) {
            return ['product'];
        }
        $postTypes = get_post_types(['public' => true], 'names');
        return array_values((array) apply_filters('wat_scanner_post_types', $postTypes, $type));
    }

    public function run(int $jobId, int $batchSize = 25): array
    {
        $job = $this->jobs->get($jobId);
        if (! $job) {
            return ['error' => __('Scan job niet gevonden.', 'webactueel-translate-language-dropdowns')];
        }
        if (self::is_terminal_status((string) $job['status'])) {
            return $job;
        }

        $batchSize = max(1, min(100, $batchSize));
        $type = sanitize_key((string) $job['type']);
        $lastCursor = absint($job['cursor_value'] ?? 0);
        $ids = $this->fetch_post_ids(self::post_types_for($type), $lastCursor, $batchSize);

        if (! $ids) {
            return $this->complete_empty_job($jobId);
        }

        $summary = $this->scan_posts_for_job($ids, $type, $lastCursor, $batchSize, $jobId);
        $this->jobs->update($jobId, $this->scan_update_payload($job, $summary));

        $job = $this->jobs->get($jobId);
        if (! empty($summary['is_done'])) {
            do_action('wat_after_scan_job', $jobId, $job);
        }
        return $job;
    }

    private static function is_terminal_status(string $status): bool
    {
        return in_array($status, ['completed', 'failed', 'stopped', 'paused'], true);
    }

    /** @param array<int, string> $postTypes @return array<int, int> */
    private function fetch_post_ids(array $postTypes, int $lastCursor, int $batchSize): array
    {
        $cursorFilter = static function (string $where, \WP_Query $query): string {
            $cursor = absint($query->get('wat_cursor'));
            if ($cursor > 0) {
                global $wpdb;
                $where .= $wpdb->prepare(" AND {$wpdb->posts}.ID > %d", $cursor);
            }
            return $where;
        };

        add_filter('posts_where', $cursorFilter, 10, 2);
        try {
            $ids = get_posts([
                'post_type' => $postTypes,
                'post_status' => 'publish',
                'posts_per_page' => $batchSize,
                'orderby' => 'ID',
                'order' => 'ASC',
                'fields' => 'ids',
                'no_found_rows' => true,
                'suppress_filters' => false,
                'ignore_sticky_posts' => true,
                'wat_cursor' => $lastCursor,
            ]);
        } finally {
            remove_filter('posts_where', $cursorFilter, 10);
        }

        return array_values(array_filter(array_map('absint', $ids)));
    }

    private function complete_empty_job(int $jobId): array
    {
        $this->jobs->update($jobId, [
            'status' => 'completed',
            'message' => __('Scan voltooid.', 'webactueel-translate-language-dropdowns'),
            'completed_at' => current_time('mysql'),
        ]);
        $job = $this->jobs->get($jobId);
        do_action('wat_after_scan_job', $jobId, $job);
        return $job;
    }

    /**
     * @param array<int, int> $ids
     * @return array{found:int,processed:int,errors:int,last_id:int,timed_out:bool,is_done:bool}
     */
    private function scan_posts_for_job(array $ids, string $type, int $lastCursor, int $batchSize, int $jobId): array
    {
        $summary = [
            'found' => 0,
            'processed' => 0,
            'errors' => 0,
            'last_id' => $lastCursor,
            'timed_out' => false,
            'is_done' => false,
        ];
        $budget = self::time_budget($jobId);
        $startedAt = microtime(true);

        foreach ($ids as $postId) {
            if ((microtime(true) - $startedAt) >= $budget) {
                $summary['timed_out'] = true;
                break;
            }

            $item = $this->scan_one_post($postId, $type);
            $summary['last_id'] = max($summary['last_id'], $postId);
            $summary['processed'] += $item['processed'];
            $summary['found'] += $item['found'];
            $summary['errors'] += $item['errors'];
        }

        $summary['is_done'] = ! $summary['timed_out'] && count($ids) < $batchSize;
        return $summary;
    }

    /** @return array{found:int,processed:int,errors:int} */
    private function scan_one_post(int $postId, string $type): array
    {
        try {
            $post = get_post($postId);
            if (! $post) {
                return ['found' => 0, 'processed' => 0, 'errors' => 0];
            }

            return [
                'found' => $this->scan_post($post, $type),
                'processed' => 1,
                'errors' => 0,
            ];
        } catch (\Throwable $e) {
            do_action('wat_log', 'error', 'Scan item fout', ['post_id' => $postId, 'error' => $e->getMessage()]);
            return ['found' => 0, 'processed' => 0, 'errors' => 1];
        }
    }

    private static function time_budget(int $jobId): int
    {
        // Server-side wall-clock guard: stop processing before max_execution_time so a
        // large client-requested batch cannot fatal mid-write and orphan the cursor.
        $maxExecution = (int) ini_get('max_execution_time');
        $budget = $maxExecution > 0 ? max(5, (int) floor($maxExecution * 0.7)) : 20;
        return (int) apply_filters('wat_scan_batch_time_budget', $budget, $jobId);
    }

    /**
     * @param array<string, mixed> $job
     * @param array{found:int,processed:int,errors:int,last_id:int,timed_out:bool,is_done:bool} $summary
     * @return array<string, mixed>
     */
    private function scan_update_payload(array $job, array $summary): array
    {
        return [
            'status' => $summary['is_done'] ? 'completed' : 'running',
            'cursor_value' => $summary['last_id'],
            'processed_items' => absint($job['processed_items'] ?? 0) + $summary['processed'],
            'found_strings' => absint($job['found_strings'] ?? 0) + $summary['found'],
            'errors_count' => absint($job['errors_count'] ?? 0) + $summary['errors'],
            'message' => self::scan_message($summary),
            'started_at' => $job['started_at'] ?: current_time('mysql'),
            'completed_at' => $summary['is_done'] ? current_time('mysql') : null,
        ];
    }

    /** @param array{found:int,processed:int,errors:int,last_id:int,timed_out:bool,is_done:bool} $summary */
    private static function scan_message(array $summary): string
    {
        if ($summary['is_done']) {
            return __('Scan voltooid.', 'webactueel-translate-language-dropdowns');
        }

        if ($summary['timed_out']) {
            // translators: 1: processed item count, 2: found string count.
            return sprintf(__('Batch ingekort wegens tijdslimiet: %1$d items, %2$d strings. Hervat automatisch.', 'webactueel-translate-language-dropdowns'), $summary['processed'], $summary['found']);
        }

        // translators: 1: processed item count, 2: found string count.
        return sprintf(__('Batch verwerkt: %1$d items, %2$d strings.', 'webactueel-translate-language-dropdowns'), $summary['processed'], $summary['found']);
    }
}
