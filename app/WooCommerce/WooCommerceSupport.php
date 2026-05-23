<?php

declare(strict_types=1);

namespace Webactueel\Translate\WooCommerce;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Translation\TranslationRepository;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

final class WooCommerceSupport
{
    private ?TranslationRepository $repository = null;

    public function register(): void
    {
        $settings = Settings::all();
        add_filter('wat_skip_output_translation_for_request', [$this, 'skip_conversion_pages'], 10, 2);
        add_filter('wat_excluded_paths', [$this, 'excluded_paths']);
        if (empty($settings['woocommerce_deep_translation_enabled'])) {
            return;
        }
        add_filter('woocommerce_product_get_name', [$this, 'translate_product_string'], 20, 2);
        add_filter('woocommerce_product_variation_get_name', [$this, 'translate_product_string'], 20, 2);
        add_filter('woocommerce_product_get_short_description', [$this, 'translate_product_string'], 20, 2);
        add_filter('woocommerce_product_get_description', [$this, 'translate_product_string'], 20, 2);
        add_filter('woocommerce_product_get_attribute', [$this, 'translate_product_attribute'], 20, 3);
        add_filter('woocommerce_order_item_name', [$this, 'translate_order_item_name'], 20, 2);
        add_filter('get_terms', [$this, 'translate_product_terms'], 20, 4);
    }

    public function skip_conversion_pages(bool $skip, array $context): bool
    {
        if ($skip) {
            return true;
        }
        if (! function_exists('is_woocommerce')) {
            return false;
        }
        return (function_exists('is_cart') && is_cart())
            || (function_exists('is_checkout') && is_checkout())
            || (function_exists('is_account_page') && is_account_page())
            || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay'));
    }

    /** @param array<int, string> $paths @return array<int, string> */
    public function excluded_paths(array $paths): array
    {
        return array_values(array_unique(array_merge($paths, ['/cart/', '/checkout/', '/my-account/', '/order-pay/'])));
    }

    public function translate_product_string(string $value, $product = null): string
    {
        return $this->translate($value);
    }

    public function translate_product_attribute(string $value, $product = null, string $attribute = ''): string
    {
        return $this->translate($value);
    }

    public function translate_order_item_name(string $name, $item = null): string
    {
        return $this->translate($name);
    }

    /**
     * @param mixed $terms
     * @return mixed
     */
    public function translate_product_terms($terms, array $taxonomies = [], array $args = [], $termQuery = null)
    {
        if (! is_array($terms)) {
            return $terms;
        }

        $productTaxonomy = false;
        foreach ($taxonomies as $taxonomy) {
            $taxonomy = (string) $taxonomy;
            if (in_array($taxonomy, ['product_cat', 'product_tag'], true) || strpos($taxonomy, 'pa_') === 0) {
                $productTaxonomy = true;
                break;
            }
        }
        if (! $productTaxonomy) {
            return $terms;
        }

        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                $term->name = $this->translate((string) $term->name);
                $term->description = $this->translate((string) $term->description);
            }
        }

        return $terms;
    }

    private function translate(string $value): string
    {
        $language = LanguageDetector::current_language();
        if ($value === '' || $language === '' || LanguageDetector::is_default_language($language)) {
            return $value;
        }

        $translated = $this->repository()->translate_text($value, $language);
        return $translated !== '' ? $translated : $value;
    }

    private function repository(): TranslationRepository
    {
        if (! $this->repository) {
            $this->repository = new TranslationRepository();
        }

        return $this->repository;
    }
}
