<?php

declare(strict_types=1);

namespace Webactueel\Translate\WooCommerce;

use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Translation\TranslationCoverageReporter;

if (! defined('ABSPATH')) {
    exit;
}

final class WooCommerceCoverageReporter
{
    /** @return array<string, mixed> */
    public function report(): array
    {
        $settings = Settings::all();
        $woocommerceActive = class_exists('WooCommerce');
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
                'label' => __('Checkout-safe-mode', 'webactueel-translate-language-dropdowns'),
                'detail' => ! empty($settings['safe_mode']) ? __('Cart, checkout, account en order-pay blijven beschermd tegen brede outputvervanging.', 'webactueel-translate-language-dropdowns') : __('Safe mode staat uit; test conversieflows handmatig.', 'webactueel-translate-language-dropdowns'),
            ],
            [
                'key' => 'deep_translation',
                'status' => ! empty($settings['woocommerce_deep_translation_enabled']) ? 'pass' : 'info',
                'label' => __('WooCommerce productfilters', 'webactueel-translate-language-dropdowns'),
                'detail' => ! empty($settings['woocommerce_deep_translation_enabled']) ? __('Productnamen, beschrijvingen, attributen en termen worden via WooCommerce filters aangeboden.', 'webactueel-translate-language-dropdowns') : __('WooCommerce productfilters staan uit.', 'webactueel-translate-language-dropdowns'),
            ],
            [
                'key' => 'translation_coverage',
                'status' => ! empty(TranslationCoverageReporter::summary()['has_untranslated_languages']) ? 'warn' : 'pass',
                'label' => __('Gemengde-taalrisico', 'webactueel-translate-language-dropdowns'),
                'detail' => ! empty(TranslationCoverageReporter::summary()['has_untranslated_languages']) ? __('Er zijn nog ontbrekende vertalingen; controleer product-, cart- en accountteksten op gemengde taal.', 'webactueel-translate-language-dropdowns') : __('Geen ontbrekende vertalingen in de globale dekking gevonden.', 'webactueel-translate-language-dropdowns'),
            ],
        ];

        return [
            'ok' => ! $this->has_status($checks, 'fail'),
            'summary' => $this->summary($checks),
            'checks' => $checks,
            'manual_tests' => [
                __('Productpagina met variaties en attributen.', 'webactueel-translate-language-dropdowns'),
                __('Cart met coupon, btw en verzendkosten.', 'webactueel-translate-language-dropdowns'),
                __('Checkout als gast en als ingelogde klant.', 'webactueel-translate-language-dropdowns'),
                __('Account, order-pay, thank-you en e-mailtemplates.', 'webactueel-translate-language-dropdowns'),
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $checks */
    private function has_status(array $checks, string $status): bool
    {
        foreach ($checks as $check) {
            if (($check['status'] ?? '') === $status) {
                return true;
            }
        }
        return false;
    }

    /** @param array<int, array<string, mixed>> $checks */
    private function summary(array $checks): array
    {
        $summary = ['pass' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
        foreach ($checks as $check) {
            $status = is_string($check['status'] ?? null) ? $check['status'] : 'info';
            if (array_key_exists($status, $summary)) {
                ++$summary[$status];
            }
        }
        return $summary;
    }
}
