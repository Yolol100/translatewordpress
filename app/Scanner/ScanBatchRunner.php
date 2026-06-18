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
        $filtered = apply_filters('wat_scanner_post_types', $postTypes, $type);
        if (! is_array($filtered)) {
            $filtered = $postTypes;
        }

        $clean = [];
        foreach ($filtered as $postType) {
            if (! is_scalar($postType)) {
                continue;
            }
            $postType = sanitize_key((string) $postType);
            if ($postType !== '' && post_type_exists($postType)) {
                $clean[] = $postType;
            }
        }

        return array_values(array_unique($clean));
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

        if (! self::acquire_job_lock($jobId)) {
            return $job;
        }

        try {
            $batchSize = max(1, min(100, $batchSize));
            $type = sanitize_key((string) $job['type']);
            $lastCursor = absint($job['cursor_value'] ?? 0);
            $ids = $this->fetch_post_ids(self::post_types_for($type), $lastCursor, $batchSize);

            if (! $ids) {
                return $this->complete_job_with_site_structures($jobId, $job, $type);
            }

            $summary = $this->scan_posts_for_job($ids, $type, $lastCursor, $batchSize, $jobId);
            $this->jobs->update($jobId, $this->scan_update_payload($job, $summary));

            $job = $this->jobs->get($jobId);
            if (! empty($summary['is_done'])) {
                do_action('wat_after_scan_job', $jobId, $job);
            }
            return $job;
        } finally {
            self::release_job_lock($jobId);
        }
    }

    private static function is_terminal_status(string $status): bool
    {
        return in_array($status, ['completed', 'failed', 'stopped', 'paused'], true);
    }


    private static function acquire_job_lock(int $jobId): bool
    {
        $lockName = 'wat_scan_job_lock_' . absint($jobId);
        $lockAge = (int) get_option($lockName, 0);
        if ($lockAge > 0 && $lockAge < (time() - 300)) {
            delete_option($lockName);
        }

        return add_option($lockName, (string) time(), '', false);
    }

    private static function release_job_lock(int $jobId): void
    {
        delete_option('wat_scan_job_lock_' . absint($jobId));
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

    private function complete_job_with_site_structures(int $jobId, array $job, string $type): array
    {
        $siteSummary = in_array($type, ['full', 'woocommerce'], true) ? $this->scan_site_structures($type) : ['found' => 0, 'processed' => 0, 'errors' => 0];
        $this->jobs->update($jobId, [
            'status' => 'completed',
            'processed_items' => absint($job['processed_items'] ?? 0) + $siteSummary['processed'],
            'found_strings' => absint($job['found_strings'] ?? 0) + $siteSummary['found'],
            'errors_count' => absint($job['errors_count'] ?? 0) + $siteSummary['errors'],
            'message' => __('Scan voltooid.', 'webactueel-translate-language-dropdowns'),
            'completed_at' => current_time('mysql'),
        ]);
        $job = $this->jobs->get($jobId);
        do_action('wat_after_scan_job', $jobId, $job);
        return $job;
    }

    /** @return array{found:int,processed:int,errors:int} */
    private function scan_site_structures(string $type): array
    {
        $summary = ['found' => 0, 'processed' => 0, 'errors' => 0];
        foreach ([
            [$this, 'scan_navigation_menus'],
            [$this, 'scan_active_widgets'],
            fn(): int => $this->scan_terms($type),
        ] as $callback) {
            try {
                $summary['found'] += (int) $callback();
                $summary['processed']++;
            } catch (\Throwable $e) {
                $summary['errors']++;
                do_action('wat_log', 'error', 'Site-structuur scan fout', ['error' => $e->getMessage()]);
            }
        }
        return $summary;
    }

    private function scan_navigation_menus(): int
    {
        $menus = wp_get_nav_menus();
        if (! is_array($menus)) {
            return 0;
        }

        $count = 0;
        foreach ($menus as $menu) {
            if (! $menu instanceof \WP_Term) {
                continue;
            }
            $menuId = absint($menu->term_id);
            $count += $this->scan_text($menu->name, 'nav_menu', $menuId, 'menu_name', 'menu:' . $menuId . ':name');
            $items = wp_get_nav_menu_items($menuId, ['post_status' => 'publish']);
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                $itemId = absint($item->ID ?? 0);
                if ($itemId < 1) {
                    continue;
                }
                $count += $this->scan_text((string) ($item->title ?? ''), 'nav_menu', $itemId, 'menu_item_title', 'menu:' . $menuId . ':item:' . $itemId . ':title');
                $count += $this->scan_text((string) ($item->attr_title ?? ''), 'nav_menu', $itemId, 'menu_item_attr_title', 'menu:' . $menuId . ':item:' . $itemId . ':attr_title');
                $count += $this->scan_text((string) ($item->description ?? ''), 'nav_menu', $itemId, 'menu_item_description', 'menu:' . $menuId . ':item:' . $itemId . ':description');
            }
        }
        return $count;
    }

    private function scan_active_widgets(): int
    {
        $sidebars = get_option('sidebars_widgets', []);
        if (! is_array($sidebars)) {
            return 0;
        }

        $count = 0;
        $widgetOptions = [];
        foreach ($sidebars as $sidebarId => $widgetIds) {
            if ($sidebarId === 'wp_inactive_widgets' || ! is_array($widgetIds)) {
                continue;
            }
            foreach ($widgetIds as $widgetId) {
                if (! is_string($widgetId) || ! preg_match('/^(.+)-(\d+)$/', $widgetId, $matches)) {
                    continue;
                }
                $base = sanitize_key($matches[1]);
                $number = absint($matches[2]);
                if ($base === '' || $number < 1) {
                    continue;
                }
                if (! array_key_exists($base, $widgetOptions)) {
                    $option = get_option('widget_' . $base, []);
                    $widgetOptions[$base] = is_array($option) ? $option : [];
                }
                $instance = $widgetOptions[$base][$number] ?? null;
                if (is_array($instance)) {
                    $count += $this->scan_mixed($instance, 'widget', $number, 'widget_' . $base, 'widget:' . $base . ':' . $number);
                }
            }
        }
        return $count;
    }

    private function scan_terms(string $type): int
    {
        $taxonomies = $this->taxonomies_for_scan($type);
        if ($taxonomies === []) {
            return 0;
        }

        $terms = get_terms([
            'taxonomy' => $taxonomies,
            'hide_empty' => false,
            'fields' => 'all',
        ]);
        if (is_wp_error($terms) || ! is_array($terms)) {
            return 0;
        }

        $count = 0;
        foreach ($terms as $term) {
            if (! $term instanceof \WP_Term) {
                continue;
            }
            $termId = absint($term->term_id);
            $sourceType = 'term_' . sanitize_key($term->taxonomy);
            $count += $this->scan_text($term->name, $sourceType, $termId, 'term_name', 'term:' . $term->taxonomy . ':' . $termId . ':name');
            $count += $this->scan_html($term->description, $sourceType, $termId, 'term_description', 'term:' . $term->taxonomy . ':' . $termId . ':description', false);
        }
        return $count;
    }

    /** @return list<string> */
    private function taxonomies_for_scan(string $type): array
    {
        if ($type === 'woocommerce') {
            return array_values(array_filter(['product_cat', 'product_tag'], static fn(string $taxonomy): bool => taxonomy_exists($taxonomy)));
        }

        $taxonomies = get_taxonomies(['public' => true], 'names');
        if (! is_array($taxonomies)) {
            return [];
        }

        $clean = [];
        foreach ($taxonomies as $taxonomy) {
            $taxonomy = sanitize_key((string) $taxonomy);
            if ($taxonomy !== '' && taxonomy_exists($taxonomy) && $taxonomy !== 'nav_menu') {
                $clean[] = $taxonomy;
            }
        }
        return array_values(array_unique($clean));
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
