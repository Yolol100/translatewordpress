<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

use Webactueel\Translate\Database\Tables;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This plugin uses its own custom translation tables; queries are scoped and cache invalidation is handled by the plugin.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: custom prefixed tables and public wat_* hooks are intentional.

final class Logger
{
    public static function write(string $level, string $message, array $context = []): void
    {
        global $wpdb;
        $allowed = ['info', 'warning', 'error', 'debug'];
        $level = in_array($level, $allowed, true) ? $level : 'info';
        $settings = Settings::all();
        if (in_array($level, ['info', 'debug'], true) && empty($settings['debug_logging'])) {
            return;
        }
        $context = self::scrub_context($context);
        $wpdb->insert(Tables::logs(), [
            'level' => $level,
            'message' => sanitize_text_field($message),
            'context' => wp_json_encode($context),
            'created_at' => current_time('mysql'),
        ]);
    }

    private static function scrub_context(array $context): array
    {
        $blocked = ['password', 'pass', 'pwd', 'nonce', '_wpnonce', 'cookie', 'authorization', 'token', 'secret', 'key'];
        $clean = [];

        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            foreach ($blocked as $blockedKey) {
                if (strpos($normalizedKey, $blockedKey) !== false) {
                    $clean[$key] = '[redacted]';
                    continue 2;
                }
            }

            if (is_array($value)) {
                $clean[$key] = self::scrub_context($value);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            } else {
                $clean[$key] = '[non-scalar]';
            }
        }

        return $clean;
    }

    public static function latest(int $limit = 50): array
    {
        global $wpdb;
        $limit = max(1, min(200, $limit));
        $logs_table = Tables::sql_identifier(Tables::logs());
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$logs_table}` ORDER BY id DESC LIMIT %d", $limit), ARRAY_A) ?: [];
    }
}
