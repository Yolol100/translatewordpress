<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Routing;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\UrlMapping;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

trait RewriteRequestFiltering
{
    private static function front_page_query_vars(): array
    {
        if (get_option('show_on_front') !== 'page') {
            return [];
        }

        $frontId = (int) get_option('page_on_front');
        if ($frontId <= 0) {
            return [];
        }

        $path = self::front_page_path();
        $vars = ['page_id' => $frontId];
        if ($path !== '') {
            $vars['pagename'] = $path;
        }

        return $vars;
    }

    public static function filter_request(array $query_vars): array
    {
        $language = self::language_from_query_vars($query_vars);
        if ($language === '') {
            $language = self::language_from_path();
        }
        if ($language === '') {
            return $query_vars;
        }

        $stripped = '';
        if (isset($query_vars['wat_path'])) {
            $stripped = trim(Input::text($query_vars['wat_path']), '/');
        }
        if ($stripped === '') {
            $stripped = self::strip_language_prefix(self::request_path());
        }

        self::$requestLanguage = $language;
        self::$requestPath = $stripped;

        $query_vars['wat_language'] = $language;
        $query_vars['wat_path'] = $stripped;
        unset($query_vars['error']);

        if ($stripped === '') {
            foreach (['pagename', 'name', 'page_id', 'p', 'post_type', 'attachment', 'attachment_id', 'category_name'] as $key) {
                unset($query_vars[$key]);
            }

            $frontPageVars = self::front_page_query_vars();
            if ($frontPageVars) {
                $query_vars = array_merge($query_vars, $frontPageVars);
            }

            return $query_vars;
        }

        foreach (['pagename', 'category_name', 'name', 'attachment'] as $key) {
            if (! empty($query_vars[$key]) && is_string($query_vars[$key])) {
                $query_vars[$key] = self::strip_language_prefix($query_vars[$key]);
            }
        }

        if (empty($query_vars['pagename']) && empty($query_vars['page_id']) && empty($query_vars['p']) && empty($query_vars['name'])) {
            $query_vars = array_merge($query_vars, self::query_vars_for_real_path($stripped, $language));
        }

        foreach (['pagename', 'category_name', 'name', 'attachment'] as $key) {
            if (! empty($query_vars[$key]) && is_string($query_vars[$key]) && self::language_from_path('/' . $query_vars[$key] . '/') !== '') {
                $query_vars[$key] = self::strip_language_prefix($query_vars[$key]);
            }
        }

        return $query_vars;
    }

    private static function language_from_query_vars(array $query_vars): string
    {
        $language = Input::key($query_vars['wat_language'] ?? '');
        return $language !== '' && LanguageDetector::language_exists($language) ? $language : '';
    }

    private static function rewrite_language_codes(): array
    {
        $codes = [];
        foreach (LanguageDetector::active_languages() as $language) {
            $code = Input::key($language['code'] ?? '');
            if ($code !== '') {
                $codes[] = $code;
            }
        }
        return array_values(array_unique($codes));
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

    private static function path_exists(string $path): bool
    {
        $path = trim($path, '/');
        if ($path === '') {
            return true;
        }
        $language = self::current_request_language();
        if ($language !== '' && UrlMapping::query_vars_for_mapped_path($language, $path)) {
            return true;
        }
        if (UrlMapping::query_vars_for_existing_term_path($path)) {
            return true;
        }
        $postTypes = get_post_types(['public' => true], 'names');
        return get_page_by_path($path, OBJECT, $postTypes ?: 'page') instanceof \WP_Post;
    }
}
