<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

use Webactueel\Translate\Database\Tables;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned custom tables; table identifiers are prepared with %i placeholders.


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
        $encodedContext = wp_json_encode($context);
        $wpdb->insert(Tables::logs(), [
            'level' => $level,
            'message' => sanitize_text_field($message),
            'context' => is_string($encodedContext) ? $encodedContext : '{}',
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
        $logs_table = Tables::logs();
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id DESC LIMIT %d', $logs_table, $limit), ARRAY_A) ?: [];
    }
}
