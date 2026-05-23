<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Concerns;

if (! defined('ABSPATH')) {
    exit;
}

trait UrlMappingQueryVars
{
    public static function query_vars_for_mapped_path(string $language, string $path): array
    {
        $language = sanitize_key($language);
        $path = self::normalize_path($path);
        if ($language === '' || $path === '') {
            return [];
        }

        $post = self::post_for_mapped_path($language, $path);
        if ($post instanceof \WP_Post) {
            if ($post->post_type === 'page') {
                return ['page_id' => (int) $post->ID, 'pagename' => self::post_path((int) $post->ID)];
            }
            return ['p' => (int) $post->ID, 'post_type' => $post->post_type, 'name' => $post->post_name];
        }

        $term = self::term_for_mapped_path($language, $path);
        if ($term instanceof \WP_Term) {
            if ($term->taxonomy === 'category') {
                return ['category_name' => self::term_slug_path($term)];
            }
            if ($term->taxonomy === 'post_tag') {
                return ['tag' => $term->slug];
            }
            return [$term->taxonomy => self::term_slug_path($term)];
        }

        return [];
    }

    public static function query_vars_for_existing_term_path(string $path): array
    {
        static $cache = [];

        $path = self::normalize_path($path);
        if ($path === '') {
            return [];
        }
        if (array_key_exists($path, $cache)) {
            return $cache[$path];
        }

        $taxonomies = get_taxonomies(['public' => true], 'names');
        if (! $taxonomies) {
            return $cache[$path] = [];
        }

        $parts = explode('/', $path);
        $slug = sanitize_title((string) end($parts));
        if ($slug === '') {
            return $cache[$path] = [];
        }

        $terms = get_terms([
            'taxonomy' => array_values($taxonomies),
            'hide_empty' => false,
            'slug' => $slug,
            'number' => 50,
        ]);
        if (is_wp_error($terms) || empty($terms)) {
            return $cache[$path] = [];
        }

        foreach ($terms as $term) {
            if (! $term instanceof \WP_Term) {
                continue;
            }
            if (self::term_path($term) !== $path) {
                continue;
            }
            if ($term->taxonomy === 'category') {
                return $cache[$path] = ['category_name' => self::term_slug_path($term)];
            }
            if ($term->taxonomy === 'post_tag') {
                return $cache[$path] = ['tag' => $term->slug];
            }
            return $cache[$path] = [$term->taxonomy => self::term_slug_path($term)];
        }

        return $cache[$path] = [];
    }
}
