<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Small runtime diagnostics and release-readiness helpers for administrators.
 */
final class Diagnostics
{
    /**
     * Register optional WP-CLI diagnostics when WP-CLI is available.
     */
    public static function register_wp_cli(): void
    {
        if (! class_exists('\WP_CLI')) {
            return;
        }

        \WP_CLI::add_command('webactueel-translate status', [self::class, 'wp_cli_status']);
        \WP_CLI::add_command('webactueel-translate release-check', [self::class, 'wp_cli_release_check']);
    }

    /**
     * Print a concise status summary for release and support checks.
     *
     * @param array<int, string> $args Positional CLI args.
     * @param array<string, mixed> $assoc_args Associative CLI args.
     */
    public static function wp_cli_status(array $args = [], array $assoc_args = []): void
    {
        unset($args, $assoc_args);

        $status = self::runtime_status();
        foreach ($status as $label => $value) {
            \WP_CLI::log(sprintf('%s: %s', $label, is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value));
        }

        \WP_CLI::log('Recommended release gate: Plugin Check, PHPCS/WPCS, role tests, cache-per-language test, CSV import/export test and output-buffer TTFB comparison.');
    }


    /**
     * Print a release-readiness checklist for staging handoff.
     *
     * @param array<int, string> $args Positional CLI args.
     * @param array<string, mixed> $assoc_args Associative CLI args.
     */
    public static function wp_cli_release_check(array $args = [], array $assoc_args = []): void
    {
        unset($args, $assoc_args);

        $status = self::runtime_status();
        $checks = [
            'PHP >= 8.1' => version_compare((string) $status['php_version'], '8.1', '>='),
            'DOM/ext-xml available' => (bool) $status['dom_extension'] && (bool) $status['libxml_extension'],
            'Settings autoload safe' => get_option('wat_settings') !== false,
            'Cache version present' => get_option('wat_cache_version') !== false,
            'Uninstall data retention explicit' => get_option('wat_delete_data_on_uninstall') !== false,
        ];

        foreach ($checks as $label => $passed) {
            $passed ? \WP_CLI::success($label) : \WP_CLI::warning($label);
        }

        \WP_CLI::log('Manual gates still required: role matrix, REST nonce/capability failures, CSV/XLIFF import/export, Elementor, WooCommerce cart/checkout/order-pay/account, Plugin Check and WPCS.');
    }

    /**
     * @return array<string, string|bool|int>
     */
    public static function runtime_status(): array
    {
        return [
            'plugin_version' => defined('WAT_VERSION') ? WAT_VERSION : 'unknown',
            'php_version' => PHP_VERSION,
            'dom_extension' => class_exists('DOMDocument'),
            'libxml_extension' => extension_loaded('libxml'),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            'object_cache' => function_exists('wp_using_ext_object_cache') ? wp_using_ext_object_cache() : false,
            'delete_data_on_uninstall' => get_option('wat_delete_data_on_uninstall', '0') === '1',
        ];
    }
}
