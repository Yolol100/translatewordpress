<?php

declare(strict_types=1);

namespace Webactueel\Translate\Scanner;

use Webactueel\Translate\Database\Tables;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic parts are escaped plugin-owned table names.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

final class ScanJobManager
{
    public function create(string $type, array $options = []): array
    {
        global $wpdb;
        $type = sanitize_key($type ?: 'full');
        $now = current_time('mysql');
        $postTypes = ScanBatchRunner::post_types_for($type);
        $total = 0;
        if ($postTypes) {
            foreach ($postTypes as $postType) {
                $countObj = wp_count_posts($postType);
                $total += isset($countObj->publish) ? (int) $countObj->publish : 0;
            }
        }
        $key = wp_generate_password(32, false, false);
        $wpdb->insert(Tables::jobs(), [
            'job_key' => $key,
            'type' => $type,
            'status' => 'queued',
            'cursor_value' => 0,
            'total_items' => $total,
            'processed_items' => 0,
            'found_strings' => 0,
            'skipped_items' => 0,
            'errors_count' => 0,
            'options_json' => self::encode_options($options),
            'message' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        do_action('wat_before_scan_job', $wpdb->insert_id, $type);
        return $this->get((int) $wpdb->insert_id);
    }

    public function get(int $id): array
    {
        global $wpdb;
        $jobs_table = Tables::jobs();
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', $jobs_table, $id), ARRAY_A);
        return is_array($row) ? $row : [];
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        return $wpdb->update(Tables::jobs(), $data, ['id' => $id]) !== false;
    }

    public function set_status(int $id, string $status): array
    {
        $data = ['status' => sanitize_key($status)];
        if (in_array($status, ['completed', 'failed', 'stopped'], true)) {
            $data['completed_at'] = current_time('mysql');
        }
        if ($status === 'running') {
            $data['started_at'] = current_time('mysql');
        }
        $this->update($id, $data);
        return $this->get($id);
    }

    /** @param array<string, mixed> $options */
    private static function encode_options(array $options): string
    {
        $encoded = wp_json_encode($options);
        return is_string($encoded) ? $encoded : '{}';
    }
}
