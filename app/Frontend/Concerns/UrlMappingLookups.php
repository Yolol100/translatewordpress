<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Concerns;

use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

trait UrlMappingLookups
{
    private static function post_for_mapped_path(string $language, string $path): ?\WP_Post
    {
        $postTypes = get_post_types(['public' => true], 'names');
        $posts = get_posts([
            'post_type' => $postTypes ?: 'any',
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields' => 'all',
            'no_found_rows' => true,
            'meta_key' => self::META_PREFIX . $language, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value' => $path, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ]);
        return isset($posts[0]) && $posts[0] instanceof \WP_Post ? $posts[0] : null;
    }

    private static function term_for_mapped_path(string $language, string $path): ?\WP_Term
    {
        $taxonomies = get_taxonomies(['public' => true], 'names');
        if (! $taxonomies) {
            return null;
        }
        $terms = get_terms([
            'taxonomy' => array_values($taxonomies),
            'hide_empty' => false,
            'number' => 1,
            'meta_key' => self::META_PREFIX . $language, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value' => $path, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ]);
        if (is_wp_error($terms) || empty($terms)) {
            return null;
        }
        return $terms[0] instanceof \WP_Term ? $terms[0] : null;
    }

    private static function post_path(int $postId): string
    {
        if ($postId <= 0) {
            return '';
        }
        $post = get_post($postId);
        if (! $post instanceof \WP_Post) {
            return '';
        }
        if ($post->post_type === 'page') {
            $frontId = (int) get_option('page_on_front');
            if ($frontId > 0 && $frontId === $postId) {
                return '';
            }
            return self::normalize_path((string) get_page_uri($post));
        }
        $link = get_permalink($post);
        return self::path_from_url(is_string($link) ? $link : '');
    }

    private static function term_path(\WP_Term $term): string
    {
        $link = get_term_link($term);
        return self::path_from_url(is_string($link) ? $link : '');
    }

    private static function term_slug_path(\WP_Term $term): string
    {
        $slugs = [$term->slug];
        $parent = (int) $term->parent;
        while ($parent > 0) {
            $ancestor = get_term($parent, $term->taxonomy);
            if (! $ancestor instanceof \WP_Term) {
                break;
            }
            array_unshift($slugs, $ancestor->slug);
            $parent = (int) $ancestor->parent;
        }
        return implode('/', $slugs);
    }

    private static function path_from_url(string $url): string
    {
        if ($url === '') {
            return '';
        }
        $path = Input::scalar_string(wp_parse_url($url, PHP_URL_PATH));
        return self::normalize_path($path);
    }
}
