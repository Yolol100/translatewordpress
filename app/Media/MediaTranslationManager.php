<?php

declare(strict_types=1);

namespace Webactueel\Translate\Media;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

final class MediaTranslationManager
{
    private bool $urlGuard = false;

    public function register(): void
    {
        $settings = Settings::all();
        if (empty($settings['media_translation_enabled'])) {
            return;
        }
        add_filter('attachment_fields_to_edit', [$this, 'attachment_fields'], 20, 2);
        add_filter('attachment_fields_to_save', [$this, 'save_attachment_fields'], 20, 2);
        add_filter('wp_get_attachment_image_attributes', [$this, 'translate_attributes'], 20, 3);
        add_filter('wp_get_attachment_url', [$this, 'translate_attachment_url'], 20, 2);
    }

    /**
     * @param array<string, mixed> $formFields
     * @return array<string, mixed>
     */
    public function attachment_fields(array $formFields, \WP_Post $post): array
    {
        if (! current_user_can('edit_post', (int) $post->ID)) {
            return $formFields;
        }

        foreach (LanguageDetector::active_languages() as $language) {
            $code = Input::key($language['code'] ?? '');
            if ($code === '' || ! empty($language['is_default'])) {
                continue;
            }

            $values = $this->translation_map((int) $post->ID, $code);
            $label = sprintf(
                /* translators: %s: language name or code. */
                __('Media translation: %s', 'webactueel-translate-language-dropdowns'),
                (string) ($language['name'] ?? strtoupper($code))
            );

            $formFields['wat_media_' . $code] = [
                'label' => esc_html($label),
                'input' => 'html',
                'html' => $this->media_field_html((int) $post->ID, $code, $values),
                'helps' => __('Use a translated attachment ID for language-specific images, and optional translated alt/title text.', 'webactueel-translate-language-dropdowns'),
            ];
        }

        return $formFields;
    }

    /**
     * @param array<string, mixed> $attachment
     * @return array<string, mixed>
     */
    public function save_attachment_fields(array $post, array $attachment): array
    {
        if (empty($post['ID']) || ! current_user_can('edit_post', absint($post['ID']))) {
            return $post;
        }

        $existing = get_post_meta(absint($post['ID']), '_wat_media_translations', true);
        $map = is_array($existing) ? $existing : [];

        foreach (LanguageDetector::active_languages() as $language) {
            $code = Input::key($language['code'] ?? '');
            if ($code === '' || ! empty($language['is_default'])) {
                continue;
            }

            $incoming = is_array($attachment['wat_media'][$code] ?? null) ? $attachment['wat_media'][$code] : [];
            $row = [
                'attachment_id' => absint($incoming['attachment_id'] ?? 0),
                'alt' => sanitize_text_field(Input::scalar_string($incoming['alt'] ?? '')),
                'title' => sanitize_text_field(Input::scalar_string($incoming['title'] ?? '')),
            ];

            if ($row['attachment_id'] <= 0 && $row['alt'] === '' && $row['title'] === '') {
                unset($map[$code]);
                continue;
            }

            $map[$code] = $row;
        }

        if ($map) {
            update_post_meta(absint($post['ID']), '_wat_media_translations', $map);
        } else {
            delete_post_meta(absint($post['ID']), '_wat_media_translations');
        }

        return $post;
    }

    /**
     * @param array<string, mixed> $attr
     * @return array<string, mixed>
     */
    public function translate_attributes(array $attr, \WP_Post $attachment, $size): array
    {
        if (! $this->should_translate_frontend_media()) {
            return $attr;
        }

        $values = $this->current_translation_map((int) $attachment->ID);
        if (! $values) {
            return $attr;
        }

        $translatedId = absint($values['attachment_id'] ?? 0);
        if ($translatedId > 0 && $translatedId !== (int) $attachment->ID) {
            $image = wp_get_attachment_image_src($translatedId, $size);
            if (is_array($image) && ! empty($image[0])) {
                $attr['src'] = esc_url_raw((string) $image[0]);
                $attr['width'] = isset($image[1]) ? absint($image[1]) : ($attr['width'] ?? null);
                $attr['height'] = isset($image[2]) ? absint($image[2]) : ($attr['height'] ?? null);
            }
            $srcset = wp_get_attachment_image_srcset($translatedId, $size);
            if (is_string($srcset) && $srcset !== '') {
                $attr['srcset'] = $srcset;
            }
        }

        foreach (['alt', 'title'] as $key) {
            if (isset($values[$key]) && is_scalar($values[$key]) && trim((string) $values[$key]) !== '') {
                $attr[$key] = sanitize_text_field((string) $values[$key]);
            }
        }

        return $attr;
    }

    public function translate_attachment_url(string $url, int $attachmentId): string
    {
        if ($this->urlGuard || ! $this->should_translate_frontend_media()) {
            return $url;
        }

        $values = $this->current_translation_map($attachmentId);
        $translatedId = absint($values['attachment_id'] ?? 0);
        if ($translatedId <= 0 || $translatedId === $attachmentId) {
            return $url;
        }

        $this->urlGuard = true;
        try {
            $translatedUrl = wp_get_attachment_url($translatedId);
        } finally {
            $this->urlGuard = false;
        }

        return is_string($translatedUrl) && $translatedUrl !== '' ? $translatedUrl : $url;
    }

    private function should_translate_frontend_media(): bool
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }
        if (is_feed() || is_robots() || (function_exists('is_sitemap') && is_sitemap())) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function current_translation_map(int $attachmentId): array
    {
        $language = LanguageDetector::current_language();
        if ($language === '' || LanguageDetector::is_default_language($language)) {
            return [];
        }

        return $this->translation_map($attachmentId, $language);
    }

    /** @return array<string, mixed> */
    private function translation_map(int $attachmentId, string $language): array
    {
        $map = get_post_meta($attachmentId, '_wat_media_translations', true);
        if (! is_array($map) || empty($map[$language]) || ! is_array($map[$language])) {
            return [];
        }

        return $map[$language];
    }

    /** @param array<string, mixed> $values */
    private function media_field_html(int $attachmentId, string $code, array $values): string
    {
        $base = 'attachments[' . esc_attr((string) $attachmentId) . '][wat_media][' . esc_attr($code) . ']';
        return sprintf(
            '<label>%1$s <input type="number" min="0" step="1" name="%2$s[attachment_id]" value="%3$d" class="small-text" /></label><br><label>%4$s <input type="text" name="%2$s[alt]" value="%5$s" class="widefat" /></label><br><label>%6$s <input type="text" name="%2$s[title]" value="%7$s" class="widefat" /></label>',
            esc_html__('Translated attachment ID', 'webactueel-translate-language-dropdowns'),
            $base,
            absint($values['attachment_id'] ?? 0),
            esc_html__('Translated alt text', 'webactueel-translate-language-dropdowns'),
            esc_attr((string) ($values['alt'] ?? '')),
            esc_html__('Translated title', 'webactueel-translate-language-dropdowns'),
            esc_attr((string) ($values['title'] ?? ''))
        );
    }
}
