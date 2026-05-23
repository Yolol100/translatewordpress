<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Routing;

use Webactueel\Translate\Frontend\Concerns\CookieHelpers;
use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\LanguageDomainMapper;
use Webactueel\Translate\Frontend\UrlMapping;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

trait SwitchRequestRouting
{
    use CookieHelpers;
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
        self::remember_language($targetLanguage);

        if (! headers_sent()) {
            wp_safe_redirect($targetUrl, 302);
            exit;
        }
    }

    public static function clean_language_url_for_current_request(string $code): string
    {
        $code = sanitize_key($code);
        $parsed = wp_parse_url(self::request_uri()) ?: [];

        $query = [];
        if (! empty($parsed['query'])) {
            parse_str((string) $parsed['query'], $query);
        }
        $query = self::public_query_args($query);

        // Prefer the resolved WordPress object when available. This makes the
        // switcher independent of the current URL shape: /en/contact/ returns
        // /contact/ for the default language, and mapped URLs such as
        // /en/about-us/ can return the source page URL instead of only stripping
        // the prefix.
        $contextUrl = UrlMapping::url_for_current_context($code, $query);
        if ($contextUrl !== '') {
            return $contextUrl;
        }

        $path = (string) ($parsed['path'] ?? '/');
        return self::url_for_content_path($code, $path, $query);
    }

    public static function url_for_content_path(string $code, string $path, array $query = [], string $fragment = ''): string
    {
        $code = sanitize_key($code);
        $basePath = self::normalize_content_path($path);
        $parts = $basePath === '' ? [] : explode('/', $basePath);

        if (! LanguageDetector::is_default_language($code)) {
            array_unshift($parts, $code);
        }

        $contentPath = implode('/', array_filter($parts, static fn($part): bool => $part !== ''));
        $domainUrl = LanguageDomainMapper::url_for($code, $contentPath, $query, $fragment);
        if ($domainUrl !== '') {
            return $domainUrl;
        }

        $encodedPath = implode('/', array_map('rawurlencode', array_filter($parts, static fn($part): bool => $part !== '')));
        $url = home_url($encodedPath === '' ? '/' : '/' . $encodedPath . '/');
        if (! empty($query)) {
            $url = add_query_arg(self::public_query_args($query), $url);
        }
        if ($fragment !== '') {
            $url .= '#' . rawurlencode(ltrim($fragment, '#'));
        }
        return $url;
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
