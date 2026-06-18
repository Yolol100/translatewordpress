<?php

declare(strict_types=1);

namespace Webactueel\Translate\Seo\Concerns;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\UrlMapping;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

trait BuildsSitemapAlternates
{
    /** @return array<int, array{hreflang:string, href:string}> */
    private function alternates_for_term(int $termId): array
    {
        return $this->alternates_for_context(
            $termId,
            static fn(int $id, string $code): string => UrlMapping::current_context_path_for_term($id, $code)
        );
    }

    /** @return array<int, array{hreflang:string, href:string}> */
    private function alternates_for_post(int $postId): array
    {
        return $this->alternates_for_context(
            $postId,
            static fn(int $id, string $code): string => UrlMapping::current_context_path_for_post($id, $code)
        );
    }

    private function sitemap_hreflang_code(string $code): string
    {
        $code = str_replace('_', '-', trim($code));
        if ($code === '') {
            return '';
        }

        $parts = array_values(array_filter(explode('-', $code), static fn(string $part): bool => $part !== ''));
        if ($parts === []) {
            return '';
        }

        $parts[0] = strtolower($parts[0]);
        if (isset($parts[1])) {
            $parts[1] = strtoupper($parts[1]);
        }

        return implode('-', $parts);
    }

    /**
     * @param callable(int, string): string $pathResolver Resolves the language-specific path for the current sitemap item.
     * @return array<int, array{hreflang:string, href:string}>
     */
    private function alternates_for_context(int $objectId, callable $pathResolver): array
    {
        $alternates = [];
        foreach (LanguageDetector::active_languages() as $language) {
            $code = Input::key($language['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $href = UrlMapping::url_for_path($code, $pathResolver($objectId, $code));
            if ($href !== '') {
                $alternates[] = ['hreflang' => $this->sitemap_hreflang_code($code), 'href' => $href];
            }
        }

        if (! empty(Settings::all()['x_default_enabled'])) {
            $default = LanguageDetector::default_language();
            $href = UrlMapping::url_for_path($default, $pathResolver($objectId, $default));
            if ($href !== '') {
                $alternates[] = ['hreflang' => 'x-default', 'href' => $href];
            }
        }

        return $alternates;
    }
}
