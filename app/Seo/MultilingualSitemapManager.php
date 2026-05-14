<?php

declare(strict_types=1);

namespace Webactueel\Translate\Seo;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\UrlMapping;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

final class MultilingualSitemapManager
{
    private const QUERY_VAR = 'wat_language_sitemap';
    private const QUERY_TYPE = 'wat_sitemap_type';
    private const QUERY_PAGE = 'wat_sitemap_page';
    private const PAGE_SIZE = 500;

    public function register(): void
    {
        add_filter('query_vars', [$this, 'query_vars']);
        add_action('template_redirect', [$this, 'maybe_render'], -20);
        add_filter('robots_txt', [$this, 'robots_txt'], 20, 2);
    }

    /** @param array<int, string> $vars @return array<int, string> */
    public function query_vars(array $vars): array
    {
        $vars[] = self::QUERY_VAR;
        $vars[] = self::QUERY_TYPE;
        $vars[] = self::QUERY_PAGE;
        return array_values(array_unique($vars));
    }

    public function robots_txt(string $output, bool $public): string
    {
        if (! $public || ! $this->enabled()) {
            return $output;
        }

        $line = 'Sitemap: ' . home_url('/?wat_language_sitemap=1');
        return trim($output) . "\n" . $line . "\n";
    }

    public function maybe_render(): void
    {
        if (Input::scalar_string(get_query_var(self::QUERY_VAR)) !== '1') {
            return;
        }

        if (! $this->enabled()) {
            status_header(404);
            nocache_headers();
            exit;
        }

        $type = Input::key(get_query_var(self::QUERY_TYPE));
        $page = max(1, Input::absint(get_query_var(self::QUERY_PAGE), 1));

        status_header(200);
        nocache_headers();
        header('Content-Type: application/xml; charset=UTF-8');

        if (in_array($type, ['posts', 'terms'], true)) {
            echo $this->urlset_xml($type, $page); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is escaped by builder methods.
            exit;
        }

        echo $this->index_xml(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is escaped by builder methods.
        exit;
    }

    private function enabled(): bool
    {
        $settings = Settings::all();
        return ! empty($settings['multilingual_sitemap_enabled']) && ! empty($settings['hreflang_enabled']);
    }

    private function index_xml(): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($this->sitemap_index_entries() as $entry) {
            $out .= "\t<sitemap>\n";
            $out .= "\t\t<loc>" . esc_url($entry['loc']) . "</loc>\n";
            if ($entry['lastmod'] !== '') {
                $out .= "\t\t<lastmod>" . esc_html($entry['lastmod']) . "</lastmod>\n";
            }
            $out .= "\t</sitemap>\n";
        }
        $out .= '</sitemapindex>' . "\n";
        return $out;
    }

    /** @return array<int, array{loc:string, lastmod:string}> */
    private function sitemap_index_entries(): array
    {
        $entries = [];
        $postPages = max(1, (int) ceil($this->public_post_count() / self::PAGE_SIZE));
        for ($page = 1; $page <= $postPages; $page++) {
            $entries[] = [
                'loc' => $this->sitemap_url('posts', $page),
                'lastmod' => $this->latest_post_modified_time(),
            ];
        }

        $termPages = (int) ceil($this->public_term_count() / self::PAGE_SIZE);
        for ($page = 1; $page <= $termPages; $page++) {
            $entries[] = [
                'loc' => $this->sitemap_url('terms', $page),
                'lastmod' => '',
            ];
        }

        return $entries;
    }

    private function sitemap_url(string $type, int $page): string
    {
        return add_query_arg(
            [
                self::QUERY_VAR => '1',
                self::QUERY_TYPE => $type,
                self::QUERY_PAGE => max(1, $page),
            ],
            home_url('/')
        );
    }

    private function urlset_xml(string $type, int $page): string
    {
        $items = $type === 'terms' ? $this->term_sitemap_items($page) : $this->post_sitemap_items($page);
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($items as $item) {
            $out .= "\t<url>\n";
            $out .= "\t\t<loc>" . esc_url($item['loc']) . "</loc>\n";
            foreach ($item['alternates'] as $alternate) {
                $out .= "\t\t<xhtml:link rel=\"alternate\" hreflang=\"" . esc_attr($alternate['hreflang']) . "\" href=\"" . esc_url($alternate['href']) . "\" />\n";
            }
            if (! empty($item['lastmod'])) {
                $out .= "\t\t<lastmod>" . esc_html($item['lastmod']) . "</lastmod>\n";
            }
            $out .= "\t</url>\n";
        }
        $out .= '</urlset>' . "\n";
        return $out;
    }

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

    /** @return array<int, array{hreflang:string, href:string}> */
    private function alternates_for_term(int $termId): array
    {
        $alternates = [];
        foreach (LanguageDetector::active_languages() as $language) {
            $code = Input::key($language['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $path = UrlMapping::current_context_path_for_term($termId, $code);
            $href = UrlMapping::url_for_path($code, $path);
            if ($href !== '') {
                $alternates[] = ['hreflang' => $code, 'href' => $href];
            }
        }
        if (! empty(Settings::all()['x_default_enabled'])) {
            $default = LanguageDetector::default_language();
            $path = UrlMapping::current_context_path_for_term($termId, $default);
            $href = UrlMapping::url_for_path($default, $path);
            if ($href !== '') {
                $alternates[] = ['hreflang' => 'x-default', 'href' => $href];
            }
        }
        return $alternates;
    }

    /** @return array<int, array{hreflang:string, href:string}> */
    private function alternates_for_post(int $postId): array
    {
        $alternates = [];
        foreach (LanguageDetector::active_languages() as $language) {
            $code = Input::key($language['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $path = UrlMapping::current_context_path_for_post($postId, $code);
            $href = UrlMapping::url_for_path($code, $path);
            if ($href !== '') {
                $alternates[] = ['hreflang' => $code, 'href' => $href];
            }
        }
        if (! empty(Settings::all()['x_default_enabled'])) {
            $default = LanguageDetector::default_language();
            $path = UrlMapping::current_context_path_for_post($postId, $default);
            $href = UrlMapping::url_for_path($default, $path);
            if ($href !== '') {
                $alternates[] = ['hreflang' => 'x-default', 'href' => $href];
            }
        }
        return $alternates;
    }
}
