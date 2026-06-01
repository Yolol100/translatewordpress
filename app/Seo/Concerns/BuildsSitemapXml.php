<?php

declare(strict_types=1);

namespace Webactueel\Translate\Seo\Concerns;

if (! defined('ABSPATH')) {
    exit;
}

trait BuildsSitemapXml
{
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
}
