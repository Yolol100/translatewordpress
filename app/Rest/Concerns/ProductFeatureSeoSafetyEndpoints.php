<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Performance\PerformanceMonitor;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

trait ProductFeatureSeoSafetyEndpoints
{
    /**
     * Lightweight SEO readiness report for multilingual publishing.
     *
     * @return array<string, mixed>
     */
    public function seo_health(): array
    {
        $settings = Settings::all();
        $checks = [];
        $checks[] = [
            'key' => 'hreflang',
            'status' => ! empty($settings['hreflang_enabled']) ? 'pass' : 'warn',
            'label' => __('Hreflang-output', 'webactueel-translate-language-dropdowns'),
            'detail' => ! empty($settings['hreflang_enabled']) ? __('Ingeschakeld.', 'webactueel-translate-language-dropdowns') : __('Uitgeschakeld; controleer meertalige indexatie handmatig.', 'webactueel-translate-language-dropdowns'),
        ];
        $checks[] = [
            'key' => 'canonical',
            'status' => ! empty($settings['canonical_enabled']) ? 'pass' : 'warn',
            'label' => __('Per-taal canonicals', 'webactueel-translate-language-dropdowns'),
            'detail' => ! empty($settings['canonical_enabled']) ? __('Ingeschakeld.', 'webactueel-translate-language-dropdowns') : __('Uitgeschakeld; canonical-conflicten zijn handmatig te controleren.', 'webactueel-translate-language-dropdowns'),
        ];
        $checks[] = [
            'key' => 'sitemap',
            'status' => ! empty($settings['multilingual_sitemap_enabled']) ? 'pass' : 'info',
            'label' => __('Meertalige sitemap', 'webactueel-translate-language-dropdowns'),
            'detail' => ! empty($settings['multilingual_sitemap_enabled']) ? esc_url_raw(home_url('/?wat_language_sitemap=1')) : __('Sitemap-output staat uit.', 'webactueel-translate-language-dropdowns'),
        ];
        $checks[] = [
            'key' => 'seo_plugin',
            'status' => (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) ? 'pass' : 'info',
            'label' => __('SEO-plugin integratie', 'webactueel-translate-language-dropdowns'),
            'detail' => defined('WPSEO_VERSION') ? 'Yoast SEO' : (defined('RANK_MATH_VERSION') ? 'Rank Math' : __('Geen ondersteunde SEO-plugin gedetecteerd.', 'webactueel-translate-language-dropdowns')),
        ];

        $summary = ['pass' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
        foreach ($checks as $check) {
            $status = isset($check['status']) && is_string($check['status']) ? $check['status'] : 'info';
            if (isset($summary[$status])) {
                ++$summary[$status];
            }
        }

        return ['ok' => $summary['fail'] === 0, 'summary' => $summary, 'checks' => $checks];
    }

    /**
     * Explain WooCommerce safety state and recommended manual checks.
     *
     * @return array<string, mixed>
     */
    public function woocommerce_safe_mode(): array
    {
        $settings = Settings::all();
        $woocommerceActive = class_exists('WooCommerce');
        $excludedPaths = isset($settings['exclude_paths']) && is_scalar($settings['exclude_paths']) ? (string) $settings['exclude_paths'] : '';
        $checks = [
            [
                'key' => 'woocommerce_active',
                'status' => $woocommerceActive ? 'pass' : 'info',
                'label' => __('WooCommerce actief', 'webactueel-translate-language-dropdowns'),
                'detail' => $woocommerceActive ? __('WooCommerce is actief.', 'webactueel-translate-language-dropdowns') : __('WooCommerce is niet actief.', 'webactueel-translate-language-dropdowns'),
            ],
            [
                'key' => 'safe_mode',
                'status' => ! empty($settings['safe_mode']) ? 'pass' : 'warn',
                'label' => __('Veilige frontendmodus', 'webactueel-translate-language-dropdowns'),
                'detail' => ! empty($settings['safe_mode']) ? __('Ingeschakeld.', 'webactueel-translate-language-dropdowns') : __('Uitgeschakeld; test checkout, cart, account en order-pay handmatig.', 'webactueel-translate-language-dropdowns'),
            ],
            [
                'key' => 'excluded_paths',
                'status' => str_contains($excludedPaths, '/checkout/') && str_contains($excludedPaths, '/cart/') ? 'pass' : 'warn',
                'label' => __('Checkout/cart uitgesloten', 'webactueel-translate-language-dropdowns'),
                'detail' => $excludedPaths,
            ],
        ];

        return [
            'checks' => $checks,
            'manual_tests' => [
                __('Cart met coupon en verzendkosten.', 'webactueel-translate-language-dropdowns'),
                __('Checkout met gastbestelling en bestaande klant.', 'webactueel-translate-language-dropdowns'),
                __('Order-pay, accountpagina en order-e-mails.', 'webactueel-translate-language-dropdowns'),
            ],
        ];
    }

    public function performance_snapshot(): array
    {
        return ['snapshot' => PerformanceMonitor::snapshot()];
    }
}
