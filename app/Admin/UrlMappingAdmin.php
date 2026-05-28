<?php

declare(strict_types=1);

namespace Webactueel\Translate\Admin;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\UrlMapping;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

final class UrlMappingAdmin
{
    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_post'], 10, 2);
        add_action('admin_init', [$this, 'register_term_hooks']);
    }

    public function add_meta_boxes(): void
    {
        foreach (get_post_types(['public' => true], 'names') as $postType) {
            add_meta_box(
                'wat-language-url-mapping',
                __('Taal URL mappings', 'webactueel-translate-language-dropdowns'),
                [$this, 'render_post_box'],
                $postType,
                'side',
                'default'
            );
        }
    }

    public function render_post_box(\WP_Post $post): void
    {
        wp_nonce_field('wat_save_url_mappings', 'wat_url_mapping_nonce');
        echo '<p>' . esc_html__('Optioneel: vul per taal een alternatieve slug/URL-pad in. Leeg laten = standaard dezelfde slug met taalprefix.', 'webactueel-translate-language-dropdowns') . '</p>';
        foreach (LanguageDetector::active_languages() as $language) {
            $code = Input::key($language['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $label = Input::scalar_string($language['native_name'] ?? '', strtoupper($code));
            if ($label === '') {
                $label = strtoupper($code);
            }
            $value = UrlMapping::mapped_path_for_post((int) $post->ID, $code);
            echo '<p><label for="wat_language_path_' . esc_attr($code) . '"><strong>' . esc_html($label) . '</strong></label>';
            echo '<input type="text" class="widefat" id="wat_language_path_' . esc_attr($code) . '" name="wat_language_paths[' . esc_attr($code) . ']" value="' . esc_attr($value) . '" placeholder="' . esc_attr__('bijv. services/web-design', 'webactueel-translate-language-dropdowns') . '" /></p>';
        }
    }

    public function save_post(int $postId, \WP_Post $post): void
    {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
            return;
        }
        $nonce = Input::post_text('wat_url_mapping_nonce');
        if ($nonce === '' || ! wp_verify_nonce($nonce, 'wat_save_url_mappings')) {
            return;
        }
        if (! current_user_can('edit_post', $postId)) {
            return;
        }
        $raw_paths = Input::post_array_text('wat_language_paths');
        if ($raw_paths === []) {
            delete_post_meta($postId, UrlMapping::META_KEY);
            return;
        }
        $this->save_meta('post', $postId, $raw_paths);
    }

    public function register_term_hooks(): void
    {
        foreach (get_taxonomies(['public' => true], 'names') as $taxonomy) {
            add_action($taxonomy . '_edit_form_fields', [$this, 'render_term_fields'], 20, 2);
            add_action('edited_' . $taxonomy, [$this, 'save_term'], 10, 2);
        }
    }

    public function render_term_fields(\WP_Term $term, string $taxonomy): void
    {
        wp_nonce_field('wat_save_term_url_mappings', 'wat_term_url_mapping_nonce');
        echo '<tr class="form-field"><th scope="row">' . esc_html__('Taal URL mappings', 'webactueel-translate-language-dropdowns') . '</th><td>';
        echo '<p class="description">' . esc_html__('Optioneel: alternatieve slug/URL-pad per taal voor deze categorie/taxonomie.', 'webactueel-translate-language-dropdowns') . '</p>';
        foreach (LanguageDetector::active_languages() as $language) {
            $code = Input::key($language['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $label = Input::scalar_string($language['native_name'] ?? '', strtoupper($code));
            if ($label === '') {
                $label = strtoupper($code);
            }
            $value = UrlMapping::mapped_path_for_term((int) $term->term_id, $code);
            echo '<p><label for="wat_term_language_path_' . esc_attr($code) . '"><strong>' . esc_html($label) . '</strong></label>';
            echo '<input type="text" class="regular-text" id="wat_term_language_path_' . esc_attr($code) . '" name="wat_language_paths[' . esc_attr($code) . ']" value="' . esc_attr($value) . '" placeholder="' . esc_attr__('bijv. product-category/shoes', 'webactueel-translate-language-dropdowns') . '" /></p>';
        }
        echo '</td></tr>';
    }

    public function save_term(int $termId, int $ttId): void
    {
        unset($ttId);
        $nonce = Input::post_text('wat_term_url_mapping_nonce');
        if ($nonce === '' || ! wp_verify_nonce($nonce, 'wat_save_term_url_mappings')) {
            return;
        }
        $taxonomy = $this->taxonomy_from_current_hook();
        $taxonomy_object = $taxonomy !== '' ? get_taxonomy($taxonomy) : false;
        $edit_capability = ($taxonomy_object && isset($taxonomy_object->cap->edit_terms)) ? (string) $taxonomy_object->cap->edit_terms : 'manage_categories';
        if (! current_user_can($edit_capability)) {
            return;
        }
        $raw_paths = Input::post_array_text('wat_language_paths');
        if ($raw_paths === []) {
            delete_term_meta($termId, UrlMapping::META_KEY);
            return;
        }
        $this->save_meta('term', $termId, $raw_paths);
    }

    private function taxonomy_from_current_hook(): string
    {
        $hook = (string) current_filter();
        if (preg_match('/^edited_(.+)$/', $hook, $matches)) {
            return sanitize_key($matches[1]);
        }
        return '';
    }

    private function save_meta(string $type, int $objectId, array $raw): void
    {
        $clean = [];
        foreach (LanguageDetector::active_languages() as $language) {
            $code = Input::key($language['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $rawValue = $raw[$code] ?? '';
            $path = UrlMapping::normalize_path(Input::scalar_string($rawValue));
            if ($path !== '') {
                $clean[$code] = $path;
            }
            if ($type === 'post') {
                $path !== '' ? update_post_meta($objectId, UrlMapping::META_PREFIX . $code, $path) : delete_post_meta($objectId, UrlMapping::META_PREFIX . $code);
            } else {
                $path !== '' ? update_term_meta($objectId, UrlMapping::META_PREFIX . $code, $path) : delete_term_meta($objectId, UrlMapping::META_PREFIX . $code);
            }
        }
        if ($type === 'post') {
            $clean ? update_post_meta($objectId, UrlMapping::META_KEY, $clean) : delete_post_meta($objectId, UrlMapping::META_KEY);
        } else {
            $clean ? update_term_meta($objectId, UrlMapping::META_KEY, $clean) : delete_term_meta($objectId, UrlMapping::META_KEY);
        }
    }
}
