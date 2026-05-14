<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend;

use Webactueel\Translate\Compatibility\CompatibilityRegistry;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: custom prefixed tables and public wat_* hooks are intentional.

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
        add_action('template_redirect', [$this, 'maybe_start_buffer'], 0);
        add_shortcode('webactueel_translate_switcher', [$this, 'switcher_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);
        add_action('wp_head', [$this, 'hreflang'], 5);
        add_action('wp_footer', [$this, 'maybe_render_floating_switcher'], 20);
    }

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
        return (bool) apply_filters('wat_should_translate_request', true, $uri, $settings);
    }

    public function enqueue_frontend(): void
    {
        wp_register_style('webactueel-translate-language-dropdowns-design-system', WAT_PLUGIN_URL . 'build/shared/design-system.css', [], WAT_VERSION);
        wp_register_style('webactueel-translate-language-dropdowns-switcher', WAT_PLUGIN_URL . 'build/frontend/switcher.css', ['webactueel-translate-language-dropdowns-design-system'], WAT_VERSION);
        wp_register_script('webactueel-translate-language-dropdowns-switcher', WAT_PLUGIN_URL . 'build/frontend/switcher.js', [], WAT_VERSION, true);

        $settings = Settings::all();
        if (! empty($settings['switcher_floating']) || $this->current_content_has_switcher_shortcode()) {
            wp_enqueue_style('webactueel-translate-language-dropdowns-switcher');
            wp_enqueue_script('webactueel-translate-language-dropdowns-switcher');
        }
    }

    private function current_content_has_switcher_shortcode(): bool
    {
        if (! is_singular()) {
            return false;
        }

        $post = get_post();
        if (! $post instanceof \WP_Post) {
            return false;
        }

        return has_shortcode((string) $post->post_content, 'webactueel_translate_switcher');
    }

    public function switcher_shortcode($atts = []): string
    {
        wp_enqueue_style('webactueel-translate-language-dropdowns-switcher');
        wp_enqueue_script('webactueel-translate-language-dropdowns-switcher');
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
        wp_enqueue_style('webactueel-translate-language-dropdowns-switcher');
        wp_enqueue_script('webactueel-translate-language-dropdowns-switcher');
        echo LanguageSwitcher::render(['switcher_floating' => true], false); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function hreflang(): void
    {
        HreflangRenderer::render();
    }
}
