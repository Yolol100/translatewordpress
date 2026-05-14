<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Routing;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\LanguageDomainMapper;
use Webactueel\Translate\Frontend\UrlMapping;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

trait PathHelpers
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
     *
     * @return array<string, mixed>
     */

    private static function path_without_site_base(string $path): string
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
}
