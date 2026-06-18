<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Concerns;

use DOMElement;
use DOMXPath;
use Webactueel\Translate\Frontend\LanguageRouter;
use Webactueel\Translate\Frontend\UrlMapping;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

trait OutputBufferUrlRewriter
{
    private function rewrite_internal_urls(DOMXPath $xpath): int
    {
        $home = wp_parse_url(home_url('/')) ?: [];
        $homeHost = strtolower((string) ($home['host'] ?? ''));
        $rewrittenCount = 0;
        $targets = (array) apply_filters('wat_url_rewrite_targets', [
            ['query' => '//a[@href]', 'attribute' => 'href'],
        ]);
        foreach ($targets as $target) {
            if (! is_array($target) || empty($target['query']) || empty($target['attribute']) || ! is_scalar($target['query']) || ! is_scalar($target['attribute'])) {
                continue;
            }
            $query = (string) $target['query'];
            $attribute = preg_replace('/[^a-zA-Z0-9_:\-]/', '', (string) $target['attribute']);
            if ($attribute === '') {
                continue;
            }
            $nodes = $xpath->query($query);
            if (! $nodes) {
                continue;
            }
            foreach ($nodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }
                if ($this->should_skip_url_rewrite($node)) {
                    continue;
                }
                $attr = $attribute;
                $url = (string) ($node->attributes->getNamedItem($attr)->nodeValue ?? '');
                $rewritten = $this->rewrite_href($url, $homeHost);
                if ($rewritten !== $url) {
                    $node->setAttribute($attr, $rewritten);
                    $rewrittenCount++;
                }
            }
        }

        return $rewrittenCount;
    }

    private function rewrite_href(string $href, string $homeHost): string
    {
        $href = trim($href);
        if ($href === '' || $href[0] === '#' || preg_match('/^(mailto|tel|sms|javascript):/i', $href)) {
            return $href;
        }
        if (preg_match('#/(wp-admin|wp-login\.php|wp-json|wp-content|wp-includes|xmlrpc\.php)#i', $href)) {
            return $href;
        }

        $parsed = wp_parse_url($href);
        if ($parsed === false) {
            return $href;
        }
        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        if ($scheme !== '' && ! in_array($scheme, ['http', 'https'], true)) {
            return $href;
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));
        if ($host !== '' && $homeHost !== '' && $host !== $homeHost) {
            return $href;
        }

        $path = isset($parsed['path']) ? (string) $parsed['path'] : '';
        if ($path === '') {
            $path = str_starts_with($href, '?') ? LanguageRouter::current_base_path() : '/';
        }

        $query = [];
        if (! empty($parsed['query'])) {
            parse_str((string) $parsed['query'], $query);
        }
        $fragment = (string) ($parsed['fragment'] ?? '');
        $contentPath = UrlMapping::normalize_path($path);

        return LanguageRouter::url_for_content_path($this->language, $contentPath, LanguageRouter::public_query_args($query), $fragment);
    }

    private function should_skip_url_rewrite($node): bool
    {
        if (! $node instanceof DOMElement) {
            return true;
        }

        if (strtolower($node->tagName) === 'form') {
            $method = strtoupper(trim($node->getAttribute('method') ?: 'GET'));
            if ($method !== '' && $method !== 'GET') {
                return true;
            }
        }

        $current = $node;
        while ($current instanceof DOMElement) {
            if ($current->hasAttribute('data-wat-language')) {
                return true;
            }

            $class = ' ' . trim($current->getAttribute('class')) . ' ';
            if (strpos($class, ' wat-language-switcher ') !== false || strpos($class, ' notranslate ') !== false) {
                return true;
            }

            if (strtolower(trim($current->getAttribute('translate'))) === 'no') {
                return true;
            }

            $current = $current->parentNode;
        }

        return false;
    }
}
