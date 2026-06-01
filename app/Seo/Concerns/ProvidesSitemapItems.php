<?php

declare(strict_types=1);

namespace Webactueel\Translate\Seo\Concerns;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\UrlMapping;

if (! defined('ABSPATH')) {
    exit;
}

trait ProvidesSitemapItems
{
    /** @return array<int, array{loc:string, alternates:array<int, array{hreflang:string, href:string}>, lastmod:string}> */
    private function post_sitemap_items(int $page): array
    {
        $items = [];
        foreach ($this->public_posts($page) as $post) {
            $items[] = [
                'loc' => UrlMapping::url_for_path(LanguageDetector::default_language(), UrlMapping::current_context_path_for_post((int) $post->ID, LanguageDetector::default_language())),
                'alternates' => $this->alternates_for_post((int) $post->ID),
                'lastmod' => get_post_modified_time('c', true, $post) ?: '',
            ];
        }
        return array_values(array_filter($items, static fn(array $item): bool => $item['loc'] !== '' && $item['alternates'] !== []));
    }

    /** @return array<int, array{loc:string, alternates:array<int, array{hreflang:string, href:string}>, lastmod:string}> */
    private function term_sitemap_items(int $page): array
    {
        $items = [];
        foreach ($this->public_terms($page) as $term) {
            $items[] = [
                'loc' => UrlMapping::url_for_path(LanguageDetector::default_language(), UrlMapping::current_context_path_for_term((int) $term->term_id, LanguageDetector::default_language())),
                'alternates' => $this->alternates_for_term((int) $term->term_id),
                'lastmod' => '',
            ];
        }
        return array_values(array_filter($items, static fn(array $item): bool => $item['loc'] !== '' && $item['alternates'] !== []));
    }

    /** @return array<int, \WP_Post> */
    private function public_posts(int $page): array
    {
        $postTypes = get_post_types(['public' => true], 'names');
        unset($postTypes['attachment']);
        if (! $postTypes) {
            return [];
        }

        $posts = get_posts([
            'post_type' => array_values($postTypes),
            'post_status' => 'publish',
            'posts_per_page' => self::PAGE_SIZE,
            'paged' => max(1, $page),
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'suppress_filters' => false,
        ]);
        return array_values(array_filter($posts, static fn($post): bool => $post instanceof \WP_Post));
    }

    private function public_post_count(): int
    {
        $postTypes = get_post_types(['public' => true], 'names');
        unset($postTypes['attachment']);
        $count = 0;
        foreach (array_values($postTypes) as $postType) {
            $counts = wp_count_posts($postType);
            if (is_object($counts) && isset($counts->publish)) {
                $count += (int) $counts->publish;
            }
        }
        return max(0, $count);
    }

    private function latest_post_modified_time(): string
    {
        $posts = $this->public_posts(1);
        if ($posts === []) {
            return '';
        }
        return get_post_modified_time('c', true, $posts[0]) ?: '';
    }

    /** @return array<int, \WP_Term> */
    private function public_terms(int $page): array
    {
        $taxonomies = get_taxonomies(['public' => true], 'names');
        if (! $taxonomies) {
            return [];
        }
        $terms = get_terms([
            'taxonomy' => array_values($taxonomies),
            'hide_empty' => false,
            'number' => self::PAGE_SIZE,
            'offset' => (max(1, $page) - 1) * self::PAGE_SIZE,
        ]);
        if (is_wp_error($terms) || ! is_array($terms)) {
            return [];
        }
        return array_values(array_filter($terms, static fn($term): bool => $term instanceof \WP_Term));
    }

    private function public_term_count(): int
    {
        $taxonomies = get_taxonomies(['public' => true], 'names');
        if (! $taxonomies) {
            return 0;
        }

        $count = wp_count_terms([
            'taxonomy' => array_values($taxonomies),
            'hide_empty' => false,
        ]);
        if (is_wp_error($count)) {
            return 0;
        }
        return max(0, (int) $count);
    }
}
