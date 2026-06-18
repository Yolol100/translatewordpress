<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Routing;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\LanguageDomainMapper;
use Webactueel\Translate\Frontend\UrlMapping;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

final class LanguageUrlBuilder
{
    public static function language_from_path(?string $path = null): string
    {
        $path = $path ?? self::request_path();
        $path = self::path_without_site_base($path);
        if ($path === '') {
            return '';
        }

        $first = sanitize_key(strtok($path, '/') ?: '');
        return $first !== '' && LanguageDetector::language_exists($first) ? $first : '';
    }

    public static function strip_language_prefix(string $path): string
    {
        $path = self::path_without_site_base($path);
        if ($path === '') {
            return '';
        }

        $parts = explode('/', $path);
        if (! empty($parts[0]) && LanguageDetector::language_exists((string) $parts[0])) {
            array_shift($parts);
        }

        return implode('/', array_filter($parts, static fn($part): bool => $part !== ''));
    }

    public static function normalize_content_path(string $path): string
    {
        $path = trim(self::strip_language_prefix($path), '/');
        return self::is_front_page_path($path) ? '' : $path;
    }

    public static function is_front_page_path(string $path): bool
    {
        $path = trim(self::strip_language_prefix($path), '/');
        if ($path === '') {
            return true;
        }

        $frontPath = self::front_page_path();
        return $frontPath !== '' && $path === $frontPath;
    }

    public static function front_page_path(): string
    {
        if (get_option('show_on_front') !== 'page') {
            return '';
        }

        $frontId = (int) get_option('page_on_front');
        if ($frontId <= 0) {
            return '';
        }

        $uri = get_page_uri($frontId);
        if (! is_string($uri) || $uri === '') {
            $permalink = get_permalink($frontId);
            $uri = is_string($permalink) ? (string) wp_parse_url($permalink, PHP_URL_PATH) : '';
        }

        $uri = trim((string) $uri, '/');
        $parts = [];
        foreach (explode('/', $uri) as $part) {
            $part = sanitize_title(rawurldecode((string) $part));
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }

    /**
     * Resolve virtual language roots such as /en/ to the configured static
     * WordPress front page. Without this, WordPress treats the language root as
     * the generic home query and can show the posts/archive index instead of the
     * actual homepage.
     */
    public static function path_without_site_base(string $path): string
    {
        $pathOnly = wp_parse_url($path, PHP_URL_PATH);
        $path = trim(is_string($pathOnly) ? $pathOnly : $path, '/');
        if ($path === '') {
            return '';
        }

        $homePath = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
        if ($homePath !== '' && ($path === $homePath || str_starts_with($path, $homePath . '/'))) {
            $path = trim(substr($path, strlen($homePath)), '/');
        }

        return $path;
    }

    public static function clean_language_url_for_current_request(string $code): string
    {
        $code = sanitize_key($code);
        $parsed = wp_parse_url(self::request_uri()) ?: [];

        $query = [];
        if (! empty($parsed['query'])) {
            parse_str((string) $parsed['query'], $query);
        }
        $query = PublicQuerySanitizer::public_query_args($query);

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

    /**
     * @param array<string, mixed> $query
     */
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
            $url = add_query_arg(PublicQuerySanitizer::public_query_args($query), $url);
        }
        if ($fragment !== '') {
            $url .= '#' . rawurlencode(ltrim($fragment, '#'));
        }

        return $url;
    }

    public static function request_uri(): string
    {
        $uri = Input::server_raw('REQUEST_URI', '/');
        return $uri !== '' ? $uri : '/';
    }

    public static function request_path(): string
    {
        $path = wp_parse_url(self::request_uri(), PHP_URL_PATH);
        return is_string($path) ? $path : '/';
    }
}
