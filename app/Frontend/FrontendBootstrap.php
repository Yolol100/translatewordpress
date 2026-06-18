<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend;

use Webactueel\Translate\Compatibility\CompatibilityRegistry;
use Webactueel\Translate\Seo\HreflangManager;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Translation\TranslationCoverageReporter;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

// Reviewed: custom prefixed tables and public wat_* hooks are intentional.

final class FrontendBootstrap
{
    private ?OutputBufferTranslator $translator = null;

    public function register(): void
    {
        add_action('init', [LanguageRouter::class, 'register_rewrite_rules'], 0);
        add_action('init', [LanguageRouter::class, 'maybe_flush_rewrite_rules'], 20);
        add_filter('query_vars', [LanguageRouter::class, 'query_vars']);
        add_filter('request', [LanguageRouter::class, 'filter_request'], 0);
        add_action('parse_request', [LanguageRouter::class, 'capture_request'], 0);
        add_filter('pre_handle_404', [LanguageRouter::class, 'prevent_language_404']);
        add_filter('redirect_canonical', [LanguageRouter::class, 'disable_canonical_redirect'], 10, 2);
        add_filter('body_class', [LanguageRouter::class, 'body_class']);
        add_filter('allowed_redirect_hosts', [LanguageDomainMapper::class, 'allowed_redirect_hosts']);
        add_action('template_redirect', [LanguageRouter::class, 'handle_switch_request'], -100);
        add_action('template_redirect', [LanguageRouter::class, 'maybe_browser_redirect'], -90);
        add_action('template_redirect', [$this, 'maybe_redirect_unpublished_language'], -80);
        add_action('template_redirect', [$this, 'maybe_start_buffer'], 0);
        add_shortcode('webactueel_translate_switcher', [$this, 'switcher_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);
        add_action('wp_head', [$this, 'hreflang'], 5);
        add_action('wp_footer', [$this, 'maybe_render_floating_switcher'], 20);
    }

    public function maybe_redirect_unpublished_language(): void
    {
        $settings = Settings::all();
        if (empty($settings['conditional_publish_enabled']) || ! $this->is_safe_redirect_request($settings)) {
            return;
        }

        $language = LanguageDetector::current_language();
        if ($language === '' || LanguageDetector::is_default_language($language) || TranslationCoverageReporter::language_is_fully_published($language)) {
            return;
        }

        $targetUrl = LanguageRouter::clean_language_url_for_current_request(LanguageDetector::default_language());
        if ($targetUrl !== '' && ! headers_sent()) {
            wp_safe_redirect($targetUrl, 302);
            exit;
        }
    }

    private function is_safe_redirect_request(array $settings): bool
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return false;
        }
        if ((defined('REST_REQUEST') && REST_REQUEST) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
            return false;
        }
        if (Input::server_method() !== 'GET') {
            return false;
        }
        if (LanguageRouter::is_excluded_request_path(Input::scalar_string($settings['exclude_paths'] ?? ''))) {
            return false;
        }
        if (is_feed() || is_robots() || (function_exists('is_sitemap') && is_sitemap())) {
            return false;
        }
        return true;
    }

    /**
     * Start DOM output translation only for safe frontend page requests.
     *
     * This must remain conservative: broad output buffering can affect forms, checkout,
     * feeds, REST responses, cached HTML, and builder previews if the guards are relaxed.
     */
    public function maybe_start_buffer(): void
    {
        $settings = Settings::all();
        if (empty($settings['frontend_enabled']) || CompatibilityRegistry::should_disable_frontend_replacement() || ! $this->is_safe_frontend_request($settings)) {
            return;
        }
        if (! class_exists('DOMDocument')) {
            do_action('wat_log', 'warning', 'Frontend translation skipped because PHP DOM/ext-xml is unavailable.');
            return;
        }
        $language = LanguageDetector::current_language();
        if ($language === '' || LanguageDetector::is_default_language($language)) {
            return;
        }
        $this->translator = new OutputBufferTranslator($language, $settings);
        $this->translator->start();
    }

    private function is_safe_frontend_request(array $settings): bool
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return false;
        }
        if ((defined('REST_REQUEST') && REST_REQUEST) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
            return false;
        }
        $method = Input::server_method();
        if ($method !== 'GET') {
            return false;
        }
        $uri = LanguageRouter::request_uri();
        if (LanguageRouter::is_excluded_request_path(Input::scalar_string($settings['exclude_paths'] ?? ''))) {
            return false;
        }
        if (is_feed() || is_robots() || (function_exists('is_sitemap') && is_sitemap())) {
            return false;
        }
        if (! empty($settings['safe_mode']) && function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page())) {
            return false;
        }
        if (! empty($settings['frontend_strict_request_guard']) && $this->is_strictly_excluded_frontend_request($uri)) {
            return false;
        }
        return (bool) apply_filters('wat_should_translate_request', true, $uri, $settings);
    }

    private function is_strictly_excluded_frontend_request(string $uri): bool
    {
        $path = Input::scalar_string(wp_parse_url($uri, PHP_URL_PATH));
        $path = '/' . ltrim($path, '/');

        if (function_exists('is_preview') && is_preview()) {
            return true;
        }
        if (function_exists('is_embed') && is_embed()) {
            return true;
        }
        if (function_exists('is_trackback') && is_trackback()) {
            return true;
        }
        if (preg_match('#/(?:wp-json|wp-admin|wp-login\.php|xmlrpc\.php|wp-cron\.php)(?:/|$)#i', $path) === 1) {
            return true;
        }
        if (preg_match('/\.(?:xml|json|txt|csv|pdf|zip|webmanifest|ico)(?:$|[?#])/i', $uri) === 1) {
            return true;
        }

        foreach (['preview', 'preview_id', 'preview_nonce', 'customize_changeset_uuid', 'elementor-preview', 'fl_builder', 'et_fb', 'vc_editable', 'bricks', 'oxygen_iframe', 'ct_builder', 'tb-preview'] as $key) {
            if (Input::get_exists($key)) {
                return true;
            }
        }

        return false;
    }

    public function enqueue_frontend(): void
    {
        self::register_switcher_assets();

        $settings = Settings::all();
        if (
            ! empty($settings['switcher_floating'])
            || $this->current_content_has_switcher()
            || $this->active_widgets_have_switcher()
            || (bool) apply_filters('wat_should_enqueue_switcher_assets', false, $settings)
        ) {
            self::enqueue_switcher_assets();
        }
    }

    public static function register_switcher_assets(): void
    {
        if (! wp_style_is('webactueel-translate-language-dropdowns-design-system', 'registered')) {
            wp_register_style('webactueel-translate-language-dropdowns-design-system', WAT_PLUGIN_URL . 'build/shared/design-system.css', [], WAT_VERSION);
        }
        if (! wp_style_is('webactueel-translate-language-dropdowns-switcher', 'registered')) {
            wp_register_style('webactueel-translate-language-dropdowns-switcher', WAT_PLUGIN_URL . 'build/frontend/switcher.css', ['webactueel-translate-language-dropdowns-design-system'], WAT_VERSION);
        }
        if (! wp_script_is('webactueel-translate-language-dropdowns-switcher', 'registered')) {
            wp_register_script('webactueel-translate-language-dropdowns-switcher', WAT_PLUGIN_URL . 'build/frontend/switcher.js', [], WAT_VERSION, ['in_footer' => true, 'strategy' => 'defer']);
        }
    }

    public static function enqueue_switcher_assets(bool $include_script = true): void
    {
        self::register_switcher_assets();
        wp_enqueue_style('webactueel-translate-language-dropdowns-switcher');
        if ($include_script) {
            wp_enqueue_script('webactueel-translate-language-dropdowns-switcher');
        }
    }

    private function current_content_has_switcher(): bool
    {
        if (! is_singular()) {
            return false;
        }

        $post = get_post();
        if (! $post instanceof \WP_Post) {
            return false;
        }

        return $this->content_has_switcher((string) $post->post_content);
    }

    private function active_widgets_have_switcher(): bool
    {
        $sidebars = get_option('sidebars_widgets', []);
        if (! is_array($sidebars)) {
            return false;
        }

        $textWidgets = get_option('widget_text', []);
        $blockWidgets = get_option('widget_block', []);

        foreach ($sidebars as $sidebarId => $widgetIds) {
            if ($sidebarId === 'wp_inactive_widgets' || ! is_array($widgetIds)) {
                continue;
            }
            foreach ($widgetIds as $widgetId) {
                if (! is_string($widgetId)) {
                    continue;
                }
                if (strpos($widgetId, 'text-') === 0 && is_array($textWidgets)) {
                    $number = absint(substr($widgetId, 5));
                    if (! empty($textWidgets[$number]['text']) && $this->content_has_switcher(Input::scalar_string($textWidgets[$number]['text']))) {
                        return true;
                    }
                }
                if (strpos($widgetId, 'block-') === 0 && is_array($blockWidgets)) {
                    $number = absint(substr($widgetId, 6));
                    if (! empty($blockWidgets[$number]['content']) && $this->content_has_switcher(Input::scalar_string($blockWidgets[$number]['content']))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function content_has_switcher(string $content): bool
    {
        if ($content === '') {
            return false;
        }

        if (has_shortcode($content, 'webactueel_translate_switcher')) {
            return true;
        }

        return function_exists('has_block') && has_block('webactueel/translate-language-switcher', $content);
    }

    public function switcher_shortcode($atts = []): string
    {
        self::enqueue_switcher_assets();
        $atts = shortcode_atts([
            'layout' => '',
            'style' => '',
            'floating' => '',
            'position' => '',
        ], is_array($atts) ? $atts : [], 'webactueel_translate_switcher');

        return LanguageSwitcher::render([
            'switcher_layout' => Input::key($atts['layout']),
            'switcher_style' => Input::key($atts['style']),
            // Shortcodes should follow the saved layout/style, but should not become fixed/floating
            // unless the shortcode explicitly asks for it. The global floating switcher is rendered separately.
            'switcher_floating' => $atts['floating'] === '' ? false : (bool) filter_var($atts['floating'], FILTER_VALIDATE_BOOLEAN),
            'switcher_position' => Input::key($atts['position']),
        ], true);
    }

    public function maybe_render_floating_switcher(): void
    {
        $settings = Settings::all();
        if (empty($settings['switcher_floating']) || LanguageSwitcher::has_rendered('floating')) {
            return;
        }
        self::enqueue_switcher_assets();
        echo LanguageSwitcher::render(['switcher_floating' => true], false); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function hreflang(): void
    {
        foreach (HreflangManager::tags() as $tag) {
            if (! is_array($tag)) {
                continue;
            }

            $hreflang = Input::text($tag['hreflang'] ?? '');
            $href = esc_url_raw(Input::scalar_string($tag['href'] ?? ''));
            if ($hreflang === '' || $href === '') {
                continue;
            }

            echo '<link rel="alternate" hreflang="' . esc_attr($hreflang) . '" href="' . esc_url($href) . '" />' . "\n";
        }
    }
}
