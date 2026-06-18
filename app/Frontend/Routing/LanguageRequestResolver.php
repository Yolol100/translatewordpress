<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Routing;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\UrlMapping;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

final class LanguageRequestResolver
{
    /**
     * @param array<string, mixed> $query_vars
     * @return array{query_vars: array<string, mixed>, language: string, path: string}
     */
    public static function filter_request(array $query_vars): array
    {
        $language = self::filtered_request_language($query_vars);
        if ($language === '') {
            return ['query_vars' => $query_vars, 'language' => '', 'path' => ''];
        }

        $stripped = self::filtered_request_path($query_vars);
        $query_vars = self::apply_language_query_vars($query_vars, $language, $stripped);
        if ($stripped === '') {
            return [
                'query_vars' => self::front_page_language_query_vars($query_vars),
                'language' => $language,
                'path' => $stripped,
            ];
        }

        $query_vars = self::strip_language_query_var_values($query_vars);
        if (self::needs_real_path_query_vars($query_vars)) {
            $query_vars = array_merge($query_vars, self::query_vars_for_real_path($stripped, $language));
        }

        return [
            'query_vars' => self::strip_remaining_language_prefixes($query_vars),
            'language' => $language,
            'path' => $stripped,
        ];
    }

    /**
     * @param object $wp
     * @return array{language: string, path: string}|null
     */
    public static function capture_request($wp): ?array
    {
        if (! is_object($wp) || ! isset($wp->query_vars) || ! is_array($wp->query_vars)) {
            return null;
        }

        $language = self::language_from_query_vars($wp->query_vars);
        if ($language === '') {
            $language = LanguageUrlBuilder::language_from_path();
        }
        if ($language === '') {
            return null;
        }

        $path = isset($wp->query_vars['wat_path'])
            ? trim(Input::text($wp->query_vars['wat_path']), '/')
            : LanguageUrlBuilder::strip_language_prefix(LanguageUrlBuilder::request_path());

        return ['language' => $language, 'path' => $path];
    }

    public static function path_exists(string $path, string $language = ''): bool
    {
        $path = trim($path, '/');
        if ($path === '') {
            return true;
        }

        if ($language !== '' && UrlMapping::query_vars_for_mapped_path($language, $path)) {
            return true;
        }
        if (UrlMapping::query_vars_for_existing_term_path($path)) {
            return true;
        }

        $postTypes = get_post_types(['public' => true], 'names');
        return get_page_by_path($path, OBJECT, $postTypes ?: 'page') instanceof \WP_Post;
    }

    public static function language_from_query_vars(array $query_vars): string
    {
        $language = Input::key($query_vars['wat_language'] ?? '');
        return $language !== '' && LanguageDetector::language_exists($language) ? $language : '';
    }

    private static function filtered_request_language(array $query_vars): string
    {
        $language = self::language_from_query_vars($query_vars);
        return $language !== '' ? $language : LanguageUrlBuilder::language_from_path();
    }

    private static function filtered_request_path(array $query_vars): string
    {
        $stripped = isset($query_vars['wat_path']) ? trim(Input::text($query_vars['wat_path']), '/') : '';
        return $stripped !== '' ? $stripped : LanguageUrlBuilder::strip_language_prefix(LanguageUrlBuilder::request_path());
    }

    private static function apply_language_query_vars(array $query_vars, string $language, string $stripped): array
    {
        $query_vars['wat_language'] = $language;
        $query_vars['wat_path'] = $stripped;
        unset($query_vars['error']);

        return $query_vars;
    }

    private static function front_page_language_query_vars(array $query_vars): array
    {
        foreach (['pagename', 'name', 'page_id', 'p', 'post_type', 'attachment', 'attachment_id', 'category_name'] as $key) {
            unset($query_vars[$key]);
        }

        $frontPageVars = self::front_page_query_vars();
        return $frontPageVars ? array_merge($query_vars, $frontPageVars) : $query_vars;
    }

    private static function strip_language_query_var_values(array $query_vars): array
    {
        foreach (['pagename', 'category_name', 'name', 'attachment'] as $key) {
            if (! empty($query_vars[$key]) && is_string($query_vars[$key])) {
                $query_vars[$key] = LanguageUrlBuilder::strip_language_prefix($query_vars[$key]);
            }
        }

        return $query_vars;
    }

    private static function needs_real_path_query_vars(array $query_vars): bool
    {
        return empty($query_vars['pagename']) && empty($query_vars['page_id']) && empty($query_vars['p']) && empty($query_vars['name']);
    }

    private static function strip_remaining_language_prefixes(array $query_vars): array
    {
        foreach (['pagename', 'category_name', 'name', 'attachment'] as $key) {
            if (! empty($query_vars[$key]) && is_string($query_vars[$key]) && LanguageUrlBuilder::language_from_path('/' . $query_vars[$key] . '/') !== '') {
                $query_vars[$key] = LanguageUrlBuilder::strip_language_prefix($query_vars[$key]);
            }
        }

        return $query_vars;
    }

    private static function front_page_query_vars(): array
    {
        if (get_option('show_on_front') !== 'page') {
            return [];
        }

        $frontId = (int) get_option('page_on_front');
        if ($frontId <= 0) {
            return [];
        }

        $path = LanguageUrlBuilder::front_page_path();
        $vars = ['page_id' => $frontId];
        if ($path !== '') {
            $vars['pagename'] = $path;
        }

        return $vars;
    }

    private static function query_vars_for_real_path(string $path, string $language = ''): array
    {
        $path = trim($path, '/');
        if ($path === '') {
            return [];
        }

        if ($language !== '') {
            $mapped = UrlMapping::query_vars_for_mapped_path($language, $path);
            if ($mapped) {
                return $mapped;
            }
        }

        $postTypes = get_post_types(['public' => true], 'names');
        $post = get_page_by_path($path, OBJECT, $postTypes ?: 'page');
        if ($post instanceof \WP_Post) {
            if ($post->post_type === 'page') {
                return ['page_id' => (int) $post->ID, 'pagename' => $path];
            }
            return ['p' => (int) $post->ID, 'post_type' => $post->post_type, 'name' => $post->post_name];
        }

        $termVars = UrlMapping::query_vars_for_existing_term_path($path);
        if ($termVars) {
            return $termVars;
        }

        return ['pagename' => $path];
    }
}
