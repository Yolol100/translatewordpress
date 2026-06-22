<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend;

use Webactueel\Translate\Frontend\Concerns\CookieHelpers;
use Webactueel\Translate\Frontend\Routing\BrowserLanguageRedirector;
use Webactueel\Translate\Frontend\Routing\LanguagePathExclusions;
use Webactueel\Translate\Frontend\Routing\LanguageRequestResolver;
use Webactueel\Translate\Frontend\Routing\LanguageRewriteRules;
use Webactueel\Translate\Frontend\Routing\LanguageUrlBuilder;
use Webactueel\Translate\Frontend\Routing\PublicQuerySanitizer;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

final class LanguageRouter
{
    use CookieHelpers;

    private static string $requestLanguage = '';
    private static string $requestPath = '';

    public static function register_rewrite_rules(): void
    {
        LanguageRewriteRules::register_rewrite_rules();
    }

    public static function query_vars(array $vars): array
    {
        return LanguageRewriteRules::query_vars($vars);
    }

    public static function maybe_flush_rewrite_rules(): void
    {
        LanguageRewriteRules::maybe_flush_rewrite_rules();
    }

    public static function schedule_rewrite_flush(): void
    {
        LanguageRewriteRules::schedule_rewrite_flush();
    }

    public static function filter_request(array $query_vars): array
    {
        $result = LanguageRequestResolver::filter_request($query_vars);
        if ($result['language'] !== '') {
            self::$requestLanguage = $result['language'];
            self::$requestPath = $result['path'];
        }

        return $result['query_vars'];
    }

    public static function language_from_path(?string $path = null): string
    {
        return LanguageUrlBuilder::language_from_path($path);
    }

    public static function strip_language_prefix(string $path): string
    {
        return LanguageUrlBuilder::strip_language_prefix($path);
    }

    public static function normalize_content_path(string $path): string
    {
        return LanguageUrlBuilder::normalize_content_path($path);
    }

    public static function is_front_page_path(string $path): bool
    {
        return LanguageUrlBuilder::is_front_page_path($path);
    }

    public static function front_page_path(): string
    {
        return LanguageUrlBuilder::front_page_path();
    }

    public static function capture_request($wp): void
    {
        $state = LanguageRequestResolver::capture_request($wp);
        if ($state === null) {
            return;
        }

        self::$requestLanguage = $state['language'];
        self::$requestPath = $state['path'];
    }

    public static function current_request_language(): string
    {
        if (self::$requestLanguage !== '' && LanguageDetector::language_exists(self::$requestLanguage)) {
            return self::$requestLanguage;
        }

        return self::language_from_path();
    }

    public static function current_base_path(): string
    {
        if (self::$requestPath !== '') {
            return self::$requestPath;
        }

        return self::strip_language_prefix(self::request_path());
    }

    public static function request_uri(): string
    {
        return LanguageUrlBuilder::request_uri();
    }

    public static function request_path(): string
    {
        return LanguageUrlBuilder::request_path();
    }

    public static function body_class(array $classes): array
    {
        $language = LanguageDetector::current_language();
        if ($language !== '') {
            $classes[] = 'wat-lang-' . sanitize_html_class($language);
        }

        return array_values(array_unique($classes));
    }

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

        return LanguageRequestResolver::path_exists($path, self::current_request_language()) ? false : $is_404;
    }

    public static function is_excluded_request_path(string $patterns = ''): bool
    {
        return LanguagePathExclusions::is_excluded_request_path($patterns);
    }

    public static function maybe_browser_redirect(): void
    {
        BrowserLanguageRedirector::maybe_browser_redirect();
    }

    public static function handle_switch_request(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public language switch is a read-only navigation action; no privileged state is changed.
        if (! Input::get_exists('wat_switch_lang')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public language switch is a read-only navigation action; no privileged state is changed.
        $targetLanguage = Input::get_key('wat_switch_lang');
        if ($targetLanguage === '' || ! LanguageDetector::language_exists($targetLanguage)) {
            return;
        }

        $targetUrl = self::clean_language_url_for_current_request($targetLanguage);
        if ($targetUrl === '') {
            return;
        }

        self::remember_language($targetLanguage);

        if (! headers_sent()) {
            wp_safe_redirect($targetUrl, 302);
            exit;
        }
    }

    public static function clean_language_url_for_current_request(string $code): string
    {
        return LanguageUrlBuilder::clean_language_url_for_current_request($code);
    }

    public static function url_for_content_path(string $code, string $path, array $query = [], string $fragment = ''): string
    {
        return LanguageUrlBuilder::url_for_content_path($code, $path, $query, $fragment);
    }

    /**
     * Keep only public query arguments when building language URLs.
     *
     * @param array<string, mixed> $query Raw query args from parse_str() or callers.
     * @return array<string, mixed>
     */
    public static function public_query_args(array $query): array
    {
        return PublicQuerySanitizer::public_query_args($query);
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
