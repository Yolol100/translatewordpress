<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Routing;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

trait LanguageRedirects
{
    use SwitchRequestRouting;
    use PublicQueryArgs;

    public static function disable_canonical_redirect($redirect_url, $requested_url)
    {
        if (self::language_from_path(Input::scalar_string(wp_parse_url(esc_url_raw(Input::scalar_string($requested_url)), PHP_URL_PATH))) !== '') {
            return false;
        }
        return $redirect_url;
    }

    public static function prevent_language_404(bool $is_404): bool
    {
        if (! $is_404 || self::current_request_language() === '') {
            return $is_404;
        }
        $path = self::current_base_path();
        if ($path === '') {
            return false;
        }
        return self::path_exists($path) ? false : $is_404;
    }

    public static function is_excluded_request_path(string $patterns = ''): bool
    {
        $uri = self::request_uri();
        $blocked = ['/wp-admin/', '/wp-login.php', '/wp-json/', '/xmlrpc.php', '/wp-cron.php', '/wp-comments-post.php', '/wc-api/', 'wc-ajax=', 'elementor-preview=', 'preview=true', 'customize.php'];
        foreach ($blocked as $part) {
            if (stripos($uri, $part) !== false) {
                return true;
            }
        }

        $patternsList = preg_split('/\r\n|\r|\n/', $patterns) ?: [];
        $patternsList = apply_filters('wat_excluded_paths', $patternsList);
        $patternsList = is_array($patternsList) ? $patternsList : [];
        foreach ($patternsList as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern !== '' && stripos($uri, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function maybe_browser_redirect(): void
    {
        $settings = Settings::all();
        if (empty($settings['browser_redirect'])) {
            return;
        }
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        $method = Input::server_method();
        if ($method !== 'GET') {
            return;
        }
        if (self::language_from_path() !== '') {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only redirect guard.
        if (Input::get_exists('wat_switch_lang') || Input::get_exists('wat_lang') || Input::get_exists('wat_language')) {
            return;
        }
        $cookie = Input::cookie_key('wat_language');
        if ($cookie !== '') {
            if (! LanguageDetector::language_exists($cookie)) {
                self::clear_language_cookie();
            }
            return;
        }

        if (self::is_excluded_request_path(Input::scalar_string($settings['exclude_paths'] ?? ''))) {
            return;
        }

        $uri = self::request_uri();
        if (is_feed() || is_robots() || (function_exists('is_sitemap') && is_sitemap())) {
            return;
        }
        if (! empty($settings['safe_mode']) && function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page())) {
            return;
        }

        $browser = self::browser_language_from_header();
        if ($browser === '' || LanguageDetector::is_default_language($browser) || ! LanguageDetector::language_exists($browser)) {
            return;
        }

        $parsed = wp_parse_url($uri) ?: [];
        $query = [];
        if (! empty($parsed['query'])) {
            parse_str((string) $parsed['query'], $query);
        }
        $path = (string) ($parsed['path'] ?? '/');
        $targetUrl = self::url_for_content_path($browser, $path, self::public_query_args($query));
        self::remember_language($browser);

        if (! headers_sent()) {
            wp_safe_redirect($targetUrl, 302);
            exit;
        }
    }

    private static function browser_language_from_header(): string
    {
        $header = Input::server_text('HTTP_ACCEPT_LANGUAGE');
        if ($header === '') {
            return '';
        }

        $candidates = [];
        foreach (explode(',', $header) as $part) {
            $pieces = array_map('trim', explode(';', (string) $part));
            $raw = strtolower(str_replace('_', '-', sanitize_text_field($pieces[0] ?? '')));
            if ($raw === '') {
                continue;
            }
            $quality = 1.0;
            foreach (array_slice($pieces, 1) as $piece) {
                if (stripos($piece, 'q=') === 0) {
                    $quality = max(0.0, min(1.0, (float) substr($piece, 2)));
                }
            }
            $candidates[] = ['tag' => $raw, 'base' => sanitize_key(substr($raw, 0, 2)), 'q' => $quality];
        }

        usort($candidates, static fn(array $a, array $b): int => ($b['q'] <=> $a['q']));
        $languages = LanguageDetector::active_languages();
        foreach ($candidates as $candidate) {
            foreach ($languages as $language) {
                $code = Input::key($language['code'] ?? '');
                $locale = strtolower(str_replace('_', '-', Input::scalar_string($language['locale'] ?? '')));
                if ($code !== '' && ($candidate['base'] === $code || $candidate['tag'] === $locale)) {
                    return $code;
                }
            }
        }

        return '';
    }
}
