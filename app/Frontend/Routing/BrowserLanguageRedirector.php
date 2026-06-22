<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Routing;

use Webactueel\Translate\Frontend\Concerns\CookieHelpers;
use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

final class BrowserLanguageRedirector
{
    use CookieHelpers;

    private const MAX_ACCEPT_LANGUAGE_LENGTH = 2048;
    private const MAX_ACCEPT_LANGUAGE_CANDIDATES = 20;

    public static function maybe_browser_redirect(): void
    {
        $settings = Settings::all();
        if (self::should_skip_browser_redirect($settings)) {
            return;
        }

        $browser = self::browser_language_from_header();
        if (! self::is_redirectable_browser_language($browser)) {
            return;
        }

        $targetUrl = self::browser_redirect_target_url($browser);
        if ($targetUrl === '') {
            return;
        }

        self::remember_language($browser);
        if (! headers_sent()) {
            wp_safe_redirect($targetUrl, 302);
            exit;
        }
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function should_skip_browser_redirect(array $settings): bool
    {
        if (empty($settings['browser_redirect'])) {
            return true;
        }
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return true;
        }
        if (Input::server_method() !== 'GET' || LanguageUrlBuilder::language_from_path() !== '') {
            return true;
        }
        if (self::has_browser_redirect_override()) {
            return true;
        }
        if (self::has_remembered_language_cookie()) {
            return true;
        }
        if (LanguagePathExclusions::is_excluded_request_path(Input::scalar_string($settings['exclude_paths'] ?? ''))) {
            return true;
        }
        if (is_feed() || is_robots() || (function_exists('is_sitemap') && is_sitemap())) {
            return true;
        }

        return ! empty($settings['safe_mode']) && function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page());
    }

    private static function has_browser_redirect_override(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only redirect guard.
        return Input::get_exists('wat_switch_lang') || Input::get_exists('wat_lang') || Input::get_exists('wat_language');
    }

    private static function has_remembered_language_cookie(): bool
    {
        $cookie = Input::cookie_key('wat_language');
        if ($cookie === '') {
            return false;
        }
        if (! LanguageDetector::language_exists($cookie)) {
            self::clear_language_cookie();
            return false;
        }

        return true;
    }

    private static function is_redirectable_browser_language(string $browser): bool
    {
        return $browser !== '' && ! LanguageDetector::is_default_language($browser) && LanguageDetector::language_exists($browser);
    }

    private static function browser_redirect_target_url(string $browser): string
    {
        $parsed = wp_parse_url(LanguageUrlBuilder::request_uri()) ?: [];
        $query = [];
        if (! empty($parsed['query'])) {
            parse_str((string) $parsed['query'], $query);
        }

        return LanguageUrlBuilder::url_for_content_path($browser, (string) ($parsed['path'] ?? '/'), PublicQuerySanitizer::public_query_args($query));
    }

    private static function browser_language_from_header(): string
    {
        $header = Input::server_text('HTTP_ACCEPT_LANGUAGE');
        if ($header === '') {
            return '';
        }

        $header = substr($header, 0, self::MAX_ACCEPT_LANGUAGE_LENGTH);
        return self::match_browser_language_candidate(self::browser_language_candidates($header));
    }

    /**
     * @return array<int, array{tag: string, base: string, q: float}>
     */
    private static function browser_language_candidates(string $header): array
    {
        $candidates = [];
        foreach (explode(',', $header) as $part) {
            if (count($candidates) >= self::MAX_ACCEPT_LANGUAGE_CANDIDATES) {
                break;
            }

            $candidate = self::browser_language_candidate((string) $part);
            if ($candidate) {
                $candidates[] = $candidate;
            }
        }

        usort($candidates, static fn(array $a, array $b): int => ($b['q'] <=> $a['q']));
        return $candidates;
    }

    /**
     * @return array{tag: string, base: string, q: float}|null
     */
    private static function browser_language_candidate(string $part): ?array
    {
        $pieces = array_map('trim', explode(';', $part));
        $raw = strtolower(str_replace('_', '-', sanitize_text_field($pieces[0] ?? '')));
        if ($raw === '' || strlen($raw) > 64 || preg_match('/^[a-z]{1,8}(?:-[a-z0-9]{1,8})*$/', $raw) !== 1) {
            return null;
        }

        $quality = self::browser_language_quality($pieces);
        if ($quality <= 0.0) {
            return null;
        }

        return ['tag' => $raw, 'base' => sanitize_key(substr($raw, 0, 2)), 'q' => $quality];
    }

    /** @param array<int, string> $pieces */
    private static function browser_language_quality(array $pieces): float
    {
        $quality = 1.0;
        foreach (array_slice($pieces, 1) as $piece) {
            if (stripos($piece, 'q=') === 0) {
                $quality = max(0.0, min(1.0, (float) substr($piece, 2)));
            }
        }

        return $quality;
    }

    /** @param array<int, array{tag: string, base: string, q: float}> $candidates */
    private static function match_browser_language_candidate(array $candidates): string
    {
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

    private static function remember_language(string $code): void
    {
        $code = sanitize_key($code);
        if (headers_sent()) {
            return;
        }

        if (LanguageDetector::is_default_language($code)) {
            self::clear_language_cookie();
            return;
        }

        setcookie('wat_language', $code, self::cookie_options(time() + MONTH_IN_SECONDS));
        $_COOKIE['wat_language'] = $code;
    }
}
