<?php

declare(strict_types=1);

namespace Webactueel\Translate\Scanner;

use Webactueel\Translate\Translation\TranslationRepository;
use Webactueel\Translate\Scanner\Concerns\ScansPostContent;
use Webactueel\Translate\Scanner\Concerns\ScannerValueHelpers;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This plugin uses its own custom translation tables; queries are scoped and cache invalidation is handled by the plugin.

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: custom prefixed tables and public wat_* hooks are intentional.

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
        if (in_array($job['status'], ['completed', 'failed', 'stopped', 'paused'], true)) {
            return $job;
        }

        $batchSize = max(1, min(100, $batchSize));
        $type = sanitize_key((string) $job['type']);
        $postTypes = self::post_types_for($type);
        $lastCursor = absint($job['cursor_value'] ?? 0);

        $cursorFilter = static function (string $where, \WP_Query $query): string {
            $cursor = absint($query->get('wat_cursor'));
            if ($cursor > 0) {
                global $wpdb;
                $where .= $wpdb->prepare(" AND {$wpdb->posts}.ID > %d", $cursor);
            }
            return $where;
        };
        add_filter('posts_where', $cursorFilter, 10, 2);

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
        remove_filter('posts_where', $cursorFilter, 10);

        $ids = array_values(array_filter(array_map('absint', $ids)));
        if (! $ids) {
            $this->jobs->update($jobId, ['status' => 'completed', 'message' => __('Scan voltooid.', 'webactueel-translate-language-dropdowns'), 'completed_at' => current_time('mysql')]);
            $job = $this->jobs->get($jobId);
            do_action('wat_after_scan_job', $jobId, $job);
            return $job;
        }

        $found = 0;
        $processed = 0;
        $errors = 0;
        $lastId = $lastCursor;
        foreach ($ids as $postId) {
            try {
                $lastId = max($lastId, (int) $postId);
                $post = get_post($postId);
                if (! $post) {
                    continue;
                }
                $processed++;
                $found += $this->scan_post($post, $type);
            } catch (\Throwable $e) {
                $errors++;
                do_action('wat_log', 'error', 'Scan item fout', ['post_id' => $postId, 'error' => $e->getMessage()]);
            }
        }

        $isDone = count($ids) < $batchSize;
        $this->jobs->update($jobId, [
            'status' => $isDone ? 'completed' : 'running',
            'cursor_value' => $lastId,
            'processed_items' => absint($job['processed_items'] ?? 0) + $processed,
            'found_strings' => absint($job['found_strings'] ?? 0) + $found,
            'errors_count' => absint($job['errors_count'] ?? 0) + $errors,
            'message' => $isDone ? __('Scan voltooid.', 'webactueel-translate-language-dropdowns') : sprintf(__('Batch verwerkt: %d items, %d strings.', 'webactueel-translate-language-dropdowns'), $processed, $found),
            'started_at' => $job['started_at'] ?: current_time('mysql'),
            'completed_at' => $isDone ? current_time('mysql') : null,
        ]);
        $job = $this->jobs->get($jobId);
        if ($isDone) {
            do_action('wat_after_scan_job', $jobId, $job);
        }
        return $job;
    }
}
