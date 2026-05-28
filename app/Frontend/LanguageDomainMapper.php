<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend;

use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
// Reviewed: custom prefixed tables and public wat_* hooks are intentional.

final class LanguageDomainMapper
{
    public static function map(): array
    {
        $settings = Settings::all();
        $raw = $settings['language_domains'] ?? '';
        $map = [];

        if (is_string($raw)) {
            foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
                $line = trim((string) $line);
                if ($line === '' || strpos($line, '|') === false) {
                    continue;
                }
                [$code, $url] = array_map('trim', explode('|', $line, 2));
                self::add_to_map($map, $code, $url);
            }
        } elseif (is_array($raw)) {
            foreach ($raw as $key => $value) {
                if (is_array($value)) {
                    self::add_to_map($map, Input::scalar_string($value['code'] ?? $key), Input::scalar_string($value['url'] ?? $value['domain'] ?? ''));
                    continue;
                }
                self::add_to_map($map, Input::scalar_string($key), Input::scalar_string($value));
            }
        }

        $filtered = apply_filters('wat_language_domain_map', $map);
        return is_array($filtered) ? self::sanitize_map($filtered) : $map;
    }

    public static function domain_for(string $code): string
    {
        $code = sanitize_key($code);
        $map = self::map();
        return isset($map[$code]) ? (string) $map[$code] : '';
    }

    public static function language_for_current_host(): string
    {
        $host = self::normalize_host(Input::server_text('HTTP_HOST'));
        if ($host === '') {
            return '';
        }

        foreach (self::map() as $code => $baseUrl) {
            $mappedHost = self::host_from_url((string) $baseUrl);
            if ($mappedHost !== '' && $mappedHost === $host && LanguageDetector::language_exists((string) $code)) {
                return (string) $code;
            }
        }

        return '';
    }

    public static function url_for(string $code, string $path, array $query = [], string $fragment = ''): string
    {
        $code = sanitize_key($code);
        $baseUrl = self::domain_for($code);
        if ($baseUrl === '') {
            return '';
        }

        $path = LanguageRouter::normalize_content_path($path);
        $parts = $path === '' ? [] : explode('/', $path);
        $encodedPath = implode('/', array_map('rawurlencode', array_filter($parts, static fn($part): bool => $part !== '')));
        $url = rtrim($baseUrl, '/') . ($encodedPath === '' ? '/' : '/' . $encodedPath . '/');

        if (! empty($query)) {
            $url = add_query_arg(LanguageRouter::public_query_args($query), $url);
        }
        if ($fragment !== '') {
            $url .= '#' . rawurlencode(ltrim($fragment, '#'));
        }

        return $url;
    }

    public static function allowed_redirect_hosts(array $hosts): array
    {
        foreach (self::map() as $baseUrl) {
            $host = self::host_from_url((string) $baseUrl);
            if ($host !== '') {
                $hosts[] = $host;
            }
        }
        return array_values(array_unique(array_filter($hosts)));
    }

    private static function add_to_map(array &$map, string $code, string $url): void
    {
        $code = sanitize_key($code);
        $url = self::sanitize_base_url($url);
        if ($code === '' || $url === '') {
            return;
        }
        $map[$code] = $url;
    }

    private static function sanitize_map(array $raw): array
    {
        $map = [];
        foreach ($raw as $code => $url) {
            if (is_array($url)) {
                self::add_to_map($map, Input::scalar_string($code), Input::scalar_string($url['url'] ?? $url['domain'] ?? ''));
                continue;
            }
            self::add_to_map($map, Input::scalar_string($code), Input::scalar_string($url));
        }
        return $map;
    }

    private static function sanitize_base_url(string $url): string
    {
        $url = esc_url_raw(trim($url), ['http', 'https']);
        if ($url === '') {
            return '';
        }

        $parts = wp_parse_url($url);
        if (! is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return '';
        }

        $port = isset($parts['port']) ? ':' . absint($parts['port']) : '';
        $path = isset($parts['path']) ? '/' . trim((string) $parts['path'], '/') : '';

        return rtrim($scheme . '://' . $host . $port . $path, '/');
    }

    private static function host_from_url(string $url): string
    {
        return self::normalize_host((string) wp_parse_url($url, PHP_URL_HOST));
    }

    private static function normalize_host(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?: $host;
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        return $host;
    }
}
