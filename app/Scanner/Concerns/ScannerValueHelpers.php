<?php

declare(strict_types=1);

namespace Webactueel\Translate\Scanner\Concerns;

use Webactueel\Translate\Translation\StringNormalizer;

if (! defined('ABSPATH')) {
    exit;
}

trait ScannerValueHelpers
{
    private function maybe_decode_value(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        if (($trimmed[0] === '{' || $trimmed[0] === '[') && strlen($trimmed) < 1000000) {
            $json = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return $json;
            }
        }
        if (is_serialized($trimmed) && strlen($trimmed) < 1000000) {
            $unserialized = maybe_unserialize($trimmed);
            if (is_array($unserialized)) {
                return $unserialized;
            }
        }
        return null;
    }

    private function split_text(string $text): array
    {
        $text = $this->clean_text_candidate($text);
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/[\r\n]+|(?<=[.!?])\s+/u', $text) ?: [$text];
        $items = [];
        foreach ($parts as $part) {
            $part = $this->clean_text_candidate((string) $part);
            if ($part !== '') {
                $items[] = $part;
            }
        }
        return $items;
    }

    private function clean_text_candidate(string $text): string
    {
        $charset = get_bloginfo('charset') ?: 'UTF-8';
        $text = html_entity_decode(wp_strip_all_tags(strip_shortcodes($text)), ENT_QUOTES | ENT_HTML5, $charset);
        $text = preg_replace('/\s+/u', ' ', $text) ?: '';
        return trim($text);
    }

    private function is_likely_translatable(string $text): bool
    {
        $text = trim($text);
        if ($text === '' || StringNormalizer::should_skip($text)) {
            return false;
        }
        if (preg_match('~^(https?:)?//|^mailto:|^tel:|^[#./][a-z0-9_/?=&%+.-]+$~i', $text)) {
            return false;
        }
        if (preg_match('/^#[0-9a-f]{3,8}$/i', $text)) {
            return false;
        }
        if (preg_match('/^[a-z0-9_-]{1,40}$/i', $text) && ! preg_match('/[aeiouy]{2,}|\s/u', $text)) {
            return false;
        }
        return (bool) preg_match('/\p{L}/u', $text);
    }

    private function is_relevant_meta_key(string $key): bool
    {
        if ($key === '' || str_starts_with($key, 'wat_')) {
            return false;
        }
        if (str_starts_with($key, '_elementor')) {
            return in_array($key, ['_elementor_data', '_elementor_page_settings'], true);
        }
        if ($key[0] === '_') {
            return false;
        }
        return ! preg_match('/(^|_)(edit|lock|nonce|token|hash|uuid|id|ids|color|width|height|margin|padding|template|layout|css|class|style|icon|image|file|attachment|gallery|video|url|link|redirect|count|order|position|date|time)$/i', $key);
    }

    private function is_technical_key(string $key): bool
    {
        return (bool) preg_match('/^(id|_id|elType|widgetType|isInner|editor_settings|__globals__|_element_id|_css_classes|_animation|_skin|_column_size|_inline_size|_padding|_margin|_border|_background|_typography|url|href|link|image|icon|media|file|attachment|gallery|color|size|width|height|align|css|class|style|query|taxonomy|term|post_id|template_id)$/i', $key);
    }

    private function context_for_key(string $base, string $key): string
    {
        $key = sanitize_key($key);
        if ($key === '') {
            return sanitize_key($base);
        }
        if (preg_match('/title|heading|text|editor|description|caption|button|placeholder|label|content|html|subtitle|quote|testimonial|name|before|after/i', $key)) {
            return sanitize_key($base . '_' . $key);
        }
        return sanitize_key($base);
    }
}
