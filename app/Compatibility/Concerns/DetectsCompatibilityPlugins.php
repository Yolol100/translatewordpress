<?php

declare(strict_types=1);

namespace Webactueel\Translate\Compatibility\Concerns;

if (! defined('ABSPATH')) {
    exit;
}

trait DetectsCompatibilityPlugins
{
    private static function plugin_checks(): array
    {
        return [
            'WPML' => defined('ICL_SITEPRESS_VERSION') || self::plugin_active('sitepress-multilingual-cms'),
            'Polylang' => defined('POLYLANG_VERSION') || self::plugin_active('polylang'),
            'TranslatePress' => defined('TRP_PLUGIN_VERSION') || self::plugin_active('translatepress'),
            'Weglot' => defined('WEGLOT_VERSION') || self::plugin_active('weglot'),
            'GTranslate' => defined('GTRANSLATE_VERSION') || class_exists('GTranslate') || self::plugin_active('gtranslate'),
            'MultilingualPress' => defined('MULTILINGUALPRESS_VERSION') || self::plugin_active('multilingualpress'),
            'WooCommerce' => class_exists('WooCommerce') || self::plugin_active('woocommerce'),
            'Elementor' => did_action('elementor/loaded') || defined('ELEMENTOR_VERSION') || self::plugin_active('elementor'),
            'Elementor Pro' => defined('ELEMENTOR_PRO_VERSION') || self::plugin_active('elementor-pro'),
            'Advanced Custom Fields' => function_exists('get_fields') || class_exists('ACF') || self::plugin_active('advanced-custom-fields'),
            'Yoast SEO' => defined('WPSEO_VERSION') || self::plugin_active('wordpress-seo'),
            'Rank Math' => defined('RANK_MATH_VERSION') || self::plugin_active('seo-by-rank-math'),
            'SEOPress' => defined('SEOPRESS_VERSION') || self::plugin_active('wp-seopress'),
            'WP Rocket' => defined('WP_ROCKET_VERSION') || self::plugin_active('wp-rocket'),
            'LiteSpeed Cache' => defined('LSCWP_V') || self::plugin_active('litespeed-cache'),
            'W3 Total Cache' => defined('W3TC_VERSION') || self::plugin_active('w3-total-cache'),
            'Autoptimize' => defined('AUTOPTIMIZE_PLUGIN_VERSION') || self::plugin_active('autoptimize'),
            'WP Super Cache' => defined('WPCACHEHOME') || self::plugin_active('wp-super-cache'),
            'UltraCache' => self::plugin_active('ultracache') || self::plugin_active('ultra-cache'),
            'Asset CleanUp' => self::plugin_active('wp-asset-clean-up') || self::plugin_active('asset-cleanup'),
            'Imagify' => defined('IMAGIFY_VERSION') || self::plugin_active('imagify'),
            'CleanTalk' => defined('APBCT_VERSION') || self::plugin_active('cleantalk-spam-protect'),
            'WP Mail SMTP' => defined('WPMS_PLUGIN_VER') || self::plugin_active('wp-mail-smtp'),
            'Contact Form 7' => defined('WPCF7_VERSION') || self::plugin_active('contact-form-7'),
            'Gravity Forms' => class_exists('GFForms') || self::plugin_active('gravityforms'),
            'WPForms' => defined('WPFORMS_VERSION') || self::plugin_active('wpforms'),
            'Fluent Forms' => defined('FLUENTFORM_VERSION') || self::plugin_active('fluentform'),
            'Ninja Forms' => defined('NINJA_FORMS_VERSION') || self::plugin_active('ninja-forms'),
            'Complianz' => defined('cmplz_plugin') || defined('CMPLZ_VERSION') || self::plugin_active('complianz-gdpr'),
            'CookieYes' => defined('CLI_PLUGIN_VERSION') || defined('COOKIEYES_PLUGIN_VERSION') || self::plugin_active('cookie-law-info'),
            'Borlabs Cookie' => defined('BORLABS_COOKIE_VERSION') || self::plugin_active('borlabs-cookie'),
            'Slider Revolution' => defined('RS_REVISION') || self::plugin_active('revslider'),
            'Smart Slider' => defined('NEXTEND_SMARTSLIDER_3_VERSION') || self::plugin_active('smart-slider-3'),
            'LearnDash' => defined('LEARNDASH_VERSION') || self::plugin_active('sfwd-lms'),
            'Tutor LMS' => defined('TUTOR_VERSION') || self::plugin_active('tutor'),
            'MemberPress' => defined('MEPR_VERSION') || self::plugin_active('memberpress'),
            'Jeg Kit' => self::plugin_active('jeg-elementor-kit') || self::plugin_active('jeg-kit'),
        ];
    }

    private static function plugin_active(string $needle): bool
    {
        $plugins = (array) get_option('active_plugins', []);
        if (is_multisite()) {
            $plugins = array_merge($plugins, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        }
        foreach ($plugins as $plugin) {
            if (stripos((string) $plugin, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function type_for(string $name): string
    {
        if (in_array($name, ['WP Rocket','LiteSpeed Cache','W3 Total Cache','Autoptimize','WP Super Cache','UltraCache','Asset CleanUp','Imagify'], true)) {
            return 'cache/performance';
        }
        if (in_array($name, ['Yoast SEO','Rank Math','SEOPress'], true)) {
            return 'seo';
        }
        if (in_array($name, ['Contact Form 7','Gravity Forms','WPForms','Fluent Forms','Ninja Forms'], true)) {
            return 'form';
        }
        if (in_array($name, ['CleanTalk'], true)) {
            return 'security';
        }
        if (in_array($name, ['Complianz','CookieYes','Borlabs Cookie'], true)) {
            return 'cookie/consent';
        }
        if (in_array($name, ['Elementor','Elementor Pro','Jeg Kit'], true)) {
            return 'builder';
        }
        if ($name === 'WooCommerce') {
            return 'woocommerce';
        }
        if (in_array($name, ['LearnDash','Tutor LMS','MemberPress'], true)) {
            return 'lms/membership';
        }
        return 'integration';
    }

    private static function status_for(string $name, string $type): string
    {
        if ($type === 'multilingual') {
            return __('Mogelijk conflict: frontend replacement en hreflang blijven beperkt zonder override.', 'webactueel-translate-language-dropdowns');
        }
        if ($type === 'woocommerce') {
            return __('Compatibel in veilige modus: cart, checkout, account, order-pay en wc-ajax worden overgeslagen.', 'webactueel-translate-language-dropdowns');
        }
        if ($type === 'form') {
            return __('Compatibel: form submissions, hidden fields, nonces en AJAX responses worden niet vertaald.', 'webactueel-translate-language-dropdowns');
        }
        if ($type === 'cache/performance') {
            return __('Compatibel in veilige modus: non-HTML responses worden overgeslagen en cache purge faalt nooit blokkerend.', 'webactueel-translate-language-dropdowns');
        }
        if ($type === 'seo') {
            return __('Compatibel: hreflang en meta output blijven conflict-aware.', 'webactueel-translate-language-dropdowns');
        }
        return __('Compatibel in veilige modus.', 'webactueel-translate-language-dropdowns');
    }

    private static function risk_for(string $type): string
    {
        if ($type === 'multilingual') {
            return 'hoog';
        }
        if (in_array($type, ['woocommerce','form','security'], true)) {
            return 'medium';
        }
        return 'laag';
    }

    private static function recommendation_for(string $type): string
    {
        if ($type === 'multilingual') {
            return __('Laat compatibility override uit, tenzij je op staging hebt getest.', 'webactueel-translate-language-dropdowns');
        }
        if ($type === 'woocommerce') {
            return __('Laat veilige modus aan voor checkout, cart en account.', 'webactueel-translate-language-dropdowns');
        }
        return __('Handmatig testen op staging aanbevolen.', 'webactueel-translate-language-dropdowns');
    }

}
