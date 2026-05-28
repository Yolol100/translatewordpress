<?php

declare(strict_types=1);

namespace Webactueel\Translate\Seo;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\UrlMapping;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

final class SeoMetaManager
{
    public function register(): void
    {
        add_filter('document_title_parts', [$this, 'document_title_parts'], 20);
        add_action('wp_head', [$this, 'render_meta_description'], 3);

        add_filter('wpseo_title', [$this, 'seo_title'], 20);
        add_filter('wpseo_metadesc', [$this, 'seo_description'], 20);
        add_filter('wpseo_opengraph_title', [$this, 'seo_title'], 20);
        add_filter('wpseo_opengraph_desc', [$this, 'seo_description'], 20);
        add_filter('rank_math/frontend/title', [$this, 'seo_title'], 20);
        add_filter('rank_math/frontend/description', [$this, 'seo_description'], 20);
        add_filter('rank_math/opengraph/facebook/title', [$this, 'seo_title'], 20);
        add_filter('rank_math/opengraph/facebook/description', [$this, 'seo_description'], 20);
        add_filter('wpseo_canonical', [$this, 'canonical'], 20);
        add_filter('rank_math/frontend/canonical', [$this, 'canonical'], 20);
        add_action('wp_head', [$this, 'render_canonical'], 2);
    }

    /** @param array<string, string> $parts @return array<string, string> */
    public function document_title_parts(array $parts): array
    {
        if (! is_singular()) {
            return $parts;
        }
        $translated = self::post_seo_value(get_queried_object_id(), 'title');
        if ($translated !== '') {
            $parts['title'] = $translated;
        }
        return $parts;
    }

    public function render_meta_description(): void
    {
        if (! is_singular() || $this->seo_plugin_active()) {
            return;
        }
        $description = self::post_seo_value(get_queried_object_id(), 'description');
        if ($description === '') {
            return;
        }
        echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
    }

    public function seo_title(string $title): string
    {
        if (! is_singular()) {
            return $title;
        }
        $translated = self::post_seo_value(get_queried_object_id(), 'title');
        return $translated !== '' ? $translated : $title;
    }

    public function seo_description(string $description): string
    {
        if (! is_singular()) {
            return $description;
        }
        $translated = self::post_seo_value(get_queried_object_id(), 'description');
        return $translated !== '' ? $translated : $description;
    }

    public function canonical(string $canonical): string
    {
        $translated = self::current_canonical_url();
        return $translated !== '' ? $translated : $canonical;
    }

    public function render_canonical(): void
    {
        if ($this->seo_plugin_active()) {
            return;
        }
        $canonical = self::current_canonical_url();
        if ($canonical === '') {
            return;
        }
        echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
    }

    public static function current_canonical_url(): string
    {
        $settings = Settings::all();
        if (empty($settings['canonical_enabled'])) {
            return '';
        }

        $language = LanguageDetector::current_language();
        if ($language === '') {
            return '';
        }

        if (is_singular()) {
            $path = UrlMapping::current_context_path_for_post((int) get_queried_object_id(), $language);
            return $path !== '' || is_front_page() ? UrlMapping::url_for_path($language, $path) : '';
        }

        $term = get_queried_object();
        if ($term instanceof \WP_Term) {
            return UrlMapping::url_for_current_context($language);
        }

        if (is_front_page()) {
            return UrlMapping::url_for_path($language, '');
        }

        return '';
    }

    public static function post_seo_value(int $post_id, string $field): string
    {
        $field = sanitize_key($field);
        $map = get_post_meta($post_id, '_wat_seo_translations', true);
        if (! is_array($map)) {
            return '';
        }
        $language = LanguageDetector::current_language();
        if (! $language || empty($map[$language]) || ! is_array($map[$language])) {
            return '';
        }
        $value = $map[$language][$field] ?? '';
        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }

    private function seo_plugin_active(): bool
    {
        return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || class_exists('WPSEO_Frontend') || class_exists('RankMath');
    }
}
