<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Concerns;

use Webactueel\Translate\Frontend\LanguageRouter;
use Webactueel\Translate\Frontend\UrlMapping;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

trait RendersLanguageSwitcher
{
    /**
     * @param array<int, array<string, mixed>> $languages
     */
    private static function dropdown(array $classes, array $languages, string $current): string
    {
        $active = self::active_language($languages, $current);
        $activeCode = Input::key($active['code'] ?? $current);
        $activeNative = Input::scalar_string($active['native_name'] ?? '', strtoupper($activeCode));
        if ($activeNative === '') {
            $activeNative = strtoupper($activeCode);
        }
        $activeFlag = Input::scalar_string($active['flag'] ?? '');

        $menuId = 'wat-switcher-menu-' . wp_generate_uuid4();
        $out = '<nav class="' . esc_attr(implode(' ', $classes)) . '" aria-label="' . esc_attr__('Taal kiezen', 'webactueel-translate-language-dropdowns') . '">';
        $out .= '<button type="button" class="wat-switcher-toggle" aria-haspopup="true" aria-controls="' . esc_attr($menuId) . '" aria-expanded="false">' . self::label('flags_name', $activeCode, $activeNative, $activeFlag) . '<span class="wat-switcher-chevron" aria-hidden="true">▾</span></button>';
        $out .= '<ul id="' . esc_attr($menuId) . '" class="wat-switcher-menu" hidden>';
        foreach ($languages as $language) {
            $out .= self::item($language, $current, 'flags_name');
        }
        $out .= '</ul></nav>';
        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $languages
     */
    private static function list(array $classes, array $languages, string $current, string $layout): string
    {
        $out = '<nav class="' . esc_attr(implode(' ', $classes)) . '" aria-label="' . esc_attr__('Taal kiezen', 'webactueel-translate-language-dropdowns') . '"><ul>';
        foreach ($languages as $language) {
            $out .= self::item($language, $current, $layout);
        }
        $out .= '</ul></nav>';
        return $out;
    }

    /**
     * @param array<string, mixed> $language
     */
    private static function item(array $language, string $current, string $layout): string
    {
        $code = Input::key($language['code'] ?? '');
        if ($code === '') {
            return '';
        }

        $native = Input::scalar_string($language['native_name'] ?? '', strtoupper($code));
        if ($native === '') {
            $native = strtoupper($code);
        }
        $flag = Input::scalar_string($language['flag'] ?? '');
        $label = self::label($layout, $code, $native, $flag);
        $url = self::url_for($code);
        if ($url === '') {
            return '';
        }
        $active = $code === $current ? ' aria-current="page" class="is-active"' : '';
        $ariaLabel = $layout === 'flags' ? ' aria-label="' . esc_attr($native) . '"' : '';
        return '<li><a href="' . esc_url($url) . '" hreflang="' . esc_attr($code) . '" lang="' . esc_attr($code) . '"' . $active . $ariaLabel . ' data-wat-language="' . esc_attr($code) . '">' . $label . '</a></li>';
    }

    /**
     * @param array<int, array<string, mixed>> $languages
     * @return array<string, mixed>
     */
    private static function active_language(array $languages, string $current): array
    {
        foreach ($languages as $language) {
            if (Input::key($language['code'] ?? '') === $current) {
                return $language;
            }
        }
        return $languages[0] ?? [];
    }

    private static function label(string $layout, string $code, string $native, string $flag): string
    {
        $codeUpper = strtoupper($code);
        $flagHtml = self::flag_html($code, $flag);

        if ($layout === 'flags') {
            return $flagHtml ?: esc_html($codeUpper);
        }
        if ($layout === 'code') {
            return '<span class="wat-switcher-code">' . esc_html($codeUpper) . '</span>';
        }
        if ($layout === 'flags_name') {
            return $flagHtml . '<span class="wat-switcher-name">' . esc_html($native) . '</span>';
        }
        if ($layout === 'flag_code') {
            return $flagHtml . '<span class="wat-switcher-code">' . esc_html($codeUpper) . '</span>';
        }
        if ($layout === 'name_code') {
            return '<span class="wat-switcher-name">' . esc_html($native) . '</span><span class="wat-switcher-code">' . esc_html($codeUpper) . '</span>';
        }
        if ($layout === 'flags_name_code') {
            return $flagHtml . '<span class="wat-switcher-name">' . esc_html($native) . '</span><span class="wat-switcher-code">' . esc_html($codeUpper) . '</span>';
        }

        return '<span class="wat-switcher-name">' . esc_html($native) . '</span>';
    }

    private static function flag_html(string $code, string $flag): string
    {
        $classes = 'wat-switcher-flag-chip wat-switcher-flag-chip--' . sanitize_html_class($code);
        $fallback = $flag !== '' ? esc_html($flag) : '';
        return '<span class="' . esc_attr($classes) . '" aria-hidden="true">' . $fallback . '</span>';
    }

    public static function url_for(string $code): string
    {
        $parsed = wp_parse_url(LanguageRouter::request_uri()) ?: [];
        $path = Input::scalar_string($parsed['path'] ?? '/', '/');
        $query = [];
        if (! empty($parsed['query'])) {
            parse_str((string) $parsed['query'], $query);
        }
        return self::url_for_path($path, sanitize_key($code), LanguageRouter::public_query_args($query));
    }

    public static function url_for_path(string $path, string $code, array $query = [], string $fragment = ''): string
    {
        $code = sanitize_key($code);

        $contextUrl = UrlMapping::url_for_current_context($code, $query, $fragment);
        if ($contextUrl !== '') {
            $filteredUrl = apply_filters('wat_language_url', $contextUrl, $code, UrlMapping::current_context_path($code), $query);
            return is_string($filteredUrl) && $filteredUrl !== '' ? $filteredUrl : $contextUrl;
        }

        $mappedPath = UrlMapping::normalize_path($path);

        // Use the same canonical URL builder as the query-based switch route.
        // This guarantees that switching to the default language always removes
        // any existing language prefix for every page, post, product and term.
        $url = LanguageRouter::url_for_content_path($code, $mappedPath, $query, $fragment);
        $filteredUrl = apply_filters('wat_language_url', $url, $code, $mappedPath, $query);
        return is_string($filteredUrl) && $filteredUrl !== '' ? $filteredUrl : $url;
    }
}
