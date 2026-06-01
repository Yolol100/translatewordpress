<?php

declare(strict_types=1);

namespace Webactueel\Translate\Translation;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

final class StringNormalizer
{
    public static function normalize(string $text): string
    {
        $text = wp_strip_all_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?: $text;
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?: '';
        $filtered = apply_filters('wat_string_normalization', $text);
        return is_scalar($filtered) ? (string) $filtered : $text;
    }

    public static function should_skip(string $text): bool
    {
        $normalized = self::normalize($text);
        $length = function_exists('mb_strlen') ? mb_strlen($normalized) : strlen($normalized);

        if ($normalized === '' || $length < 2) {
            return true;
        }
        if (preg_match('/^[\d\s\.,:;\-+%€$\/]+$/u', $normalized)) {
            return true;
        }
        if (is_email($normalized) || filter_var($normalized, FILTER_VALIDATE_URL)) {
            return true;
        }
        return false;
    }

    public static function hash(string $text, string $context = ''): string
    {
        return hash('sha256', self::normalize($text) . '|' . sanitize_key($context));
    }
}
