<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Frontend\Concerns\UrlMappingQueryVars;
use Webactueel\Translate\Frontend\Concerns\UrlMappingLookups;

if (! defined('ABSPATH')) {
    exit;
}

final class UrlMapping
{
    use UrlMappingQueryVars;
    use UrlMappingLookups;

    public const META_KEY = '_wat_language_paths';
    public const META_PREFIX = '_wat_language_path_';

    public static function normalize_path(string $path): string
    {
        $path = trim(wp_unslash($path));
        $parsed = wp_parse_url($path);
        if (is_array($parsed) && (isset($parsed['scheme']) || isset($parsed['host']))) {
            $path = Input::scalar_string($parsed['path'] ?? '');
        } else {
            $urlPath = wp_parse_url($path, PHP_URL_PATH);
            if (is_string($urlPath) && $urlPath !== '') {
                $path = $urlPath;
            }
        }
        $path = LanguageRouter::normalize_content_path($path);
        $path = trim($path, "/ \t\n\r\0\x0B");
        $parts = [];
        foreach (explode('/', $path) as $part) {
            $part = sanitize_title(rawurldecode((string) $part));
            if ($part !== '') {
                $parts[] = $part;
            }
        }
        return implode('/', $parts);
    }

    public static function mapped_path_for_post(int $postId, string $language): string
    {
        $language = sanitize_key($language);
        if ($postId <= 0 || $language === '') {
            return '';
        }
        $direct = get_post_meta($postId, self::META_PREFIX . $language, true);
        if (is_string($direct) && trim($direct) !== '') {
            return self::normalize_path($direct);
        }
        $all = get_post_meta($postId, self::META_KEY, true);
        if (is_array($all) && isset($all[$language])) {
            return self::normalize_path(Input::scalar_string($all[$language]));
        }
        return '';
    }

    public static function mapped_path_for_term(int $termId, string $language): string
    {
        $language = sanitize_key($language);
        if ($termId <= 0 || $language === '') {
            return '';
        }
        $direct = get_term_meta($termId, self::META_PREFIX . $language, true);
        if (is_string($direct) && trim($direct) !== '') {
            return self::normalize_path($direct);
        }
        $all = get_term_meta($termId, self::META_KEY, true);
        if (is_array($all) && isset($all[$language])) {
            return self::normalize_path(Input::scalar_string($all[$language]));
        }
        return '';
    }

    public static function current_context_path(string $language): string
    {
        $language = sanitize_key($language);
        if ($language === '') {
            return '';
        }

        if (is_front_page()) {
            return '';
        }

        if (is_singular()) {
            $postId = (int) get_queried_object_id();
            $mapped = self::mapped_path_for_post($postId, $language);
            return $mapped !== '' ? $mapped : self::post_path($postId);
        }

        $term = get_queried_object();
        if ($term instanceof \WP_Term) {
            $mapped = self::mapped_path_for_term((int) $term->term_id, $language);
            return $mapped !== '' ? $mapped : self::term_path($term);
        }

        return '';
    }

    public static function current_context_path_for_post(int $postId, string $language): string
    {
        $language = sanitize_key($language);
        if ($postId <= 0 || $language === '') {
            return '';
        }

        $mapped = self::mapped_path_for_post($postId, $language);
        return $mapped !== '' ? $mapped : self::post_path($postId);
    }

    public static function current_context_path_for_term(int $termId, string $language): string
    {
        $language = sanitize_key($language);
        if ($termId <= 0 || $language === '') {
            return '';
        }

        $term = get_term($termId);
        if (! $term instanceof \WP_Term) {
            return '';
        }

        $mapped = self::mapped_path_for_term($termId, $language);
        return $mapped !== '' ? $mapped : self::term_path($term);
    }

    public static function url_for_current_context(string $language, array $query = [], string $fragment = ''): string
    {
        $path = self::current_context_path($language);

        // The front page intentionally has an empty content path. It is still a
        // valid switch target: default language => home_url('/'), non-default
        // language => /{code}/. Returning an empty string here made callers fall
        // back to the raw request path, which could leave /en/home/ or similar
        // stale prefixes in place.
        if ($path === '' && (is_front_page() || LanguageRouter::is_front_page_path(LanguageRouter::request_path()))) {
            return self::url_for_path($language, '', $query, $fragment);
        }

        if ($path === '') {
            return '';
        }
        return self::url_for_path($language, $path, $query, $fragment);
    }

    public static function url_for_path(string $language, string $path, array $query = [], string $fragment = ''): string
    {
        $language = sanitize_key($language);
        $path = self::normalize_path($path);
        $parts = $path === '' ? [] : explode('/', $path);

        $domainUrl = LanguageDomainMapper::url_for($language, $path, $query, $fragment);
        if ($domainUrl !== '') {
            return $domainUrl;
        }

        if (! LanguageDetector::is_default_language($language)) {
            array_unshift($parts, $language);
        }

        $encoded = implode('/', array_map('rawurlencode', array_filter($parts, static fn($part): bool => $part !== '')));
        $url = home_url($encoded === '' ? '/' : '/' . $encoded . '/');
        if (! empty($query)) {
            $url = add_query_arg(LanguageRouter::public_query_args($query), $url);
        }
        if ($fragment !== '') {
            $url .= '#' . rawurlencode(ltrim($fragment, '#'));
        }
        return $url;
    }
}
