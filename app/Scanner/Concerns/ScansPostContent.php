<?php

declare(strict_types=1);

namespace Webactueel\Translate\Scanner\Concerns;

if (! defined('ABSPATH')) {
    exit;
}

trait ScansPostContent
{
    private function scan_post(\WP_Post $post, string $type): int
    {
        $postId = (int) $post->ID;
        $found = 0;
        $found += $this->scan_text($post->post_title, $post->post_type, $postId, 'post_title', 'post:' . $postId . ':title');
        $found += $this->scan_text($post->post_excerpt, $post->post_type, $postId, 'post_excerpt', 'post:' . $postId . ':excerpt');
        $found += $this->scan_html($post->post_content, $post->post_type, $postId, 'post_content', 'post:' . $postId . ':content');
        $found += $this->scan_elementor($postId, $post->post_type);
        $found += $this->scan_acf($postId, $post->post_type);
        $found += $this->scan_relevant_post_meta($postId, $post->post_type);
        return $found;
    }

    private function scan_text(string $text, string $sourceType, int $sourceId, string $context, string $sourceKey = ''): int
    {
        $text = $this->clean_text_candidate($text);
        if ($text === '') {
            return 0;
        }
        return $this->repository->upsert_string($text, $sourceType, $sourceId, $context, $sourceKey) ? 1 : 0;
    }

    private function scan_html(string $html, string $sourceType, int $sourceId, string $context, string $sourceKey = '', bool $parseBlocks = true): int
    {
        if (trim($html) === '') {
            return 0;
        }
        $count = 0;
        foreach ($this->split_text(wp_strip_all_tags(strip_shortcodes($html))) as $index => $part) {
            $count += $this->scan_text($part, $sourceType, $sourceId, $context, $sourceKey . ':part:' . $index);
        }
        if ($parseBlocks && function_exists('parse_blocks')) {
            foreach (parse_blocks($html) as $block) {
                $count += $this->scan_block($block, $sourceType, $sourceId);
            }
        }
        return $count;
    }

    private function scan_block(array $block, string $sourceType, int $sourceId): int
    {
        $count = 0;
        if (! empty($block['attrs']) && is_array($block['attrs'])) {
            $count += $this->scan_mixed($block['attrs'], $sourceType, $sourceId, 'block', 'block_attrs');
        }
        if (! empty($block['innerHTML']) && is_string($block['innerHTML'])) {
            $count += $this->scan_html($block['innerHTML'], $sourceType, $sourceId, 'block_html', 'block_inner_html', false);
        }
        if (! empty($block['innerBlocks'])) {
            foreach ($block['innerBlocks'] as $inner) {
                $count += $this->scan_block($inner, $sourceType, $sourceId);
            }
        }
        return $count;
    }

    private function scan_elementor(int $postId, string $sourceType): int
    {
        $count = 0;
        foreach (['_elementor_data', '_elementor_page_settings'] as $metaKey) {
            $value = get_post_meta($postId, $metaKey, true);
            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);
                $count += is_array($decoded)
                    ? $this->scan_mixed($decoded, $sourceType, $postId, 'elementor', $metaKey)
                    : $this->scan_html($value, $sourceType, $postId, 'elementor_' . sanitize_key($metaKey), 'meta:' . $metaKey);
            } elseif (is_array($value)) {
                $count += $this->scan_mixed($value, $sourceType, $postId, 'elementor', $metaKey);
            }
        }
        return $count;
    }

    private function scan_acf(int $postId, string $sourceType): int
    {
        $count = 0;
        if (function_exists('get_fields')) {
            $fields = get_fields($postId);
            if (is_array($fields)) {
                $count += $this->scan_mixed($fields, $sourceType, $postId, 'acf', 'acf:get_fields');
            }
        }
        return $count;
    }

    private function scan_relevant_post_meta(int $postId, string $sourceType): int
    {
        $meta = get_post_meta($postId);
        if (! is_array($meta)) {
            return 0;
        }
        $count = 0;
        foreach ($meta as $key => $values) {
            $key = (string) $key;
            if ($this->is_relevant_meta_key($key)) {
                $count += $this->scan_mixed($values, $sourceType, $postId, 'meta_' . sanitize_key($key), 'meta:' . $key);
            }
        }
        return $count;
    }

    private function scan_mixed($value, string $sourceType, int $sourceId, string $context, string $sourceKey = ''): int
    {
        $count = 0;
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $childKey = is_string($key) ? $key : (string) $key;
                if ($this->is_technical_key($childKey)) {
                    continue;
                }
                $nextContext = $this->context_for_key($context, $childKey);
                $nextSource = $sourceKey !== '' ? $sourceKey . '.' . $childKey : $childKey;
                $count += $this->scan_mixed($child, $sourceType, $sourceId, $nextContext, $nextSource);
            }
            return $count;
        }
        if (! is_string($value)) {
            return 0;
        }
        $decoded = $this->maybe_decode_value($value);
        if (is_array($decoded)) {
            return $this->scan_mixed($decoded, $sourceType, $sourceId, $context, $sourceKey);
        }
        if (str_contains($value, '<') && str_contains($value, '>')) {
            return $this->scan_html($value, $sourceType, $sourceId, $context, $sourceKey);
        }
        foreach ($this->split_text($value) as $index => $part) {
            if ($this->is_likely_translatable($part)) {
                $count += $this->scan_text($part, $sourceType, $sourceId, $context, $sourceKey . ':part:' . $index);
            }
        }
        return $count;
    }
}
