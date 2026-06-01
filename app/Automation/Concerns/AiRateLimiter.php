<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation\Concerns;

use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

trait AiRateLimiter
{
    /**
     * Apply a small per-user throttle before external AI calls are made.
     *
     * @return true|WP_Error
     */
    private static function check_rate_limit()
    {
        $limit = (int) apply_filters('wat_ai_rate_limit_per_minute', 20);
        if ($limit <= 0) {
            return true;
        }

        $optionName = self::rate_limit_option_name();
        $count = self::increment_rate_limit_counter($optionName);
        if ($count < 1) {
            // Counter write failed; fail open so a transient DB hiccup cannot block translation.
            return true;
        }

        if ($count > $limit) {
            return new WP_Error(
                'wat_ai_rate_limited',
                __('AI-vertaling is tijdelijk beperkt. Probeer het over een minuut opnieuw.', 'webactueel-translate-language-dropdowns'),
                ['status' => 429]
            );
        }

        if ($count === 1) {
            self::cleanup_expired_rate_limit_options($optionName);
        }

        return true;
    }

    private static function rate_limit_option_name(): string
    {
        $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $bucket = $userId > 0 ? 'user_' . $userId : 'anonymous';
        $window = (string) floor(time() / MINUTE_IN_SECONDS);

        return 'wat_ai_rate_' . md5($bucket . '|' . $window);
    }

    private static function increment_rate_limit_counter(string $optionName): int
    {
        global $wpdb;

        // Shared-hosting-safe atomic counter. A single InnoDB UPSERT on the options row
        // is atomic, so concurrent translate/batch calls cannot all read the same
        // pre-increment value and blow past the per-minute provider cap.
        $affected = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE option_value = LAST_INSERT_ID(CAST(option_value AS UNSIGNED) + 1)",
            $optionName,
            '1',
            'no'
        )); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ($affected === false) {
            return 0;
        }

        $count = self::rate_limit_count_from_affected_rows((int) $affected, $optionName);
        wp_cache_delete($optionName, 'options');

        return $count;
    }

    private static function rate_limit_count_from_affected_rows(int $affected, string $optionName): int
    {
        global $wpdb;

        // ON DUPLICATE KEY UPDATE affected-rows convention: 1 = fresh INSERT (first hit
        // this window, count = 1); 2 = existing row updated, where LAST_INSERT_ID() was
        // set to the incremented count and is exposed via $wpdb->insert_id.
        if ($affected === 1) {
            return 1;
        }

        $count = (int) $wpdb->insert_id;
        if ($count >= 1) {
            return $count;
        }

        // Fallback: re-read the stored value if LAST_INSERT_ID was unavailable.
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT CAST(option_value AS UNSIGNED) FROM {$wpdb->options} WHERE option_name = %s",
            $optionName
        )); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return max(1, $count);
    }

    private static function cleanup_expired_rate_limit_options(string $currentOptionName): void
    {
        global $wpdb;

        // Best-effort cleanup of expired windows so the options table does not grow unbounded.
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name <> %s",
            $wpdb->esc_like('wat_ai_rate_') . '%',
            $currentOptionName
        )); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
