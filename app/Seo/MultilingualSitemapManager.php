<?php

declare(strict_types=1);

namespace Webactueel\Translate\Seo;

use Webactueel\Translate\Seo\Concerns\BuildsSitemapAlternates;
use Webactueel\Translate\Seo\Concerns\BuildsSitemapXml;
use Webactueel\Translate\Seo\Concerns\ProvidesSitemapItems;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

final class MultilingualSitemapManager
{
    use BuildsSitemapAlternates;
    use BuildsSitemapXml;
    use ProvidesSitemapItems;

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
        header('X-Content-Type-Options: nosniff');

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
}
