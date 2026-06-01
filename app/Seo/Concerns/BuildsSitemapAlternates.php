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
                $alternates[] = ['hreflang' => $code, 'href' => $href];
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
