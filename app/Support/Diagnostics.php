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
        \WP_CLI::add_command('webactueel-translate release-validation', [self::class, 'wp_cli_release_validation']);
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
     * Print release validation gates for staging handoff.
     *
     * This command does not claim a staging pass by itself. It lists the
     * runtime checks that maintainers should record before release.
     *
     * @param array<int, string> $args Positional CLI args.
     * @param array<string, mixed> $assoc_args Associative CLI args.
     */
    public static function wp_cli_release_validation(array $args = [], array $assoc_args = []): void
    {
        unset($args, $assoc_args);

        $status = self::runtime_status();
        $automatedChecks = [
            'Security baseline: DOM/libxml status known' => (bool) $status['dom_extension'] || ! (bool) $status['dom_extension'],
            'Stability baseline: PHP >= 8.1' => version_compare((string) $status['php_version'], '8.1', '>='),
            'Privacy baseline: uninstall data retention flag exists' => get_option('wat_delete_data_on_uninstall') !== false,
            'Performance baseline: cache version exists' => get_option('wat_cache_version') !== false,
            'Settings baseline: settings option exists' => get_option('wat_settings') !== false,
        ];

        foreach ($automatedChecks as $label => $passed) {
            $passed ? \WP_CLI::success($label) : \WP_CLI::warning($label);
        }

        \WP_CLI::log('Required release validation gates:');
        foreach (self::release_validation_gates() as $category => $gates) {
            \WP_CLI::log('[' . $category . ']');
            foreach ($gates as $gate) {
                \WP_CLI::log(' - ' . $gate);
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public static function release_validation_gates(): array
    {
        return [
            'Security & trust boundaries' => [
                'REST/admin-post role matrix recorded for logged-out, subscriber, editor, translator and administrator.',
                'Import/export rejects malformed, oversized and wrong-type files with safe errors.',
                'AI keys absent from logs, exports, REST payloads and frontend markup.',
            ],
            'Stability & compatibility' => [
                'Activation, deactivation, reactivation, upgrade and rollback tested on clean staging.',
                'Server with and without DOM/ext-xml tested for safe fallback behavior.',
                'Plugin Check and PHP/JS syntax checks recorded for the release ZIP.',
            ],
            'WooCommerce/HPOS' => [
                'HPOS enabled staging run completed without order-storage notices.',
                'Cart, checkout, account, order-pay, thank-you and coupon flows tested.',
                'Safe mode verified to skip conversion-critical output buffering.',
            ],
            'Performance/CWV' => [
                'TTFB comparison recorded with frontend translation enabled and disabled.',
                'Cache variation by language verified with page cache/object cache enabled.',
                'No frontend assets enqueued unless switcher shortcode/block/floating mode is present.',
            ],
            'Accessibility/admin UX' => [
                'Keyboard path tested through admin tabs, visual editor and language switcher.',
                'Visible focus and screen-reader labels checked on admin and frontend switcher UI.',
                'Browser console clean on representative admin and frontend pages.',
            ],
            'Privacy & AI boundary' => [
                'Privacy policy helper, exporter and eraser tested with a real admin user.',
                'Provider timeout/failure tested without leaking secrets or publishing unreviewed AI output.',
                'DPA/provider review outcome recorded by the site owner before enabling AI.',
            ],
        ];
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
