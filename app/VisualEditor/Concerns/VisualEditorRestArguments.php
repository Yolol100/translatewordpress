<?php

declare(strict_types=1);

namespace Webactueel\Translate\VisualEditor\Concerns;

use Webactueel\Translate\Frontend\LanguageDomainMapper;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

trait VisualEditorRestArguments
{
    /** @return array<string, array<string, mixed>> */
    private function segment_preview_args(): array
    {
        return [
            'original' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_visual_editor_text']],
            'language' => $this->language_arg(),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function segment_save_args(): array
    {
        return [
            'original' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_visual_editor_text']],
            'translation' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_textarea_field', 'validate_callback' => [self::class, 'validate_visual_editor_translation']],
            'language' => $this->language_arg(),
            'status' => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_key', 'validate_callback' => [self::class, 'validate_visual_editor_status']],
            'selector' => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_visual_editor_selector']],
            'url' => ['type' => 'string', 'format' => 'uri', 'required' => false, 'sanitize_callback' => [self::class, 'sanitize_visual_editor_url'], 'validate_callback' => [self::class, 'validate_visual_editor_url']],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function segment_suggestion_args(): array
    {
        return [
            'original' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_visual_editor_text']],
            'language' => $this->language_arg(),
            'selector' => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_visual_editor_selector']],
            'url' => ['type' => 'string', 'format' => 'uri', 'required' => false, 'sanitize_callback' => [self::class, 'sanitize_visual_editor_url'], 'validate_callback' => [self::class, 'validate_visual_editor_url']],
        ];
    }

    /** @return array<string, mixed> */
    private function language_arg(): array
    {
        return [
            'type' => 'string',
            'required' => true,
            'sanitize_callback' => 'sanitize_key',
            'validate_callback' => static fn($value): bool => is_scalar($value) && preg_match('/^[a-z]{2,3}(?:[-_][a-z0-9]{2,8})?$/i', (string) $value) === 1,
        ];
    }

    public static function validate_visual_editor_text($value): bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        $text = trim(sanitize_text_field((string) $value));
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);

        return $length >= 2 && $length <= 300;
    }

    public static function validate_visual_editor_translation($value): bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        $text = trim(sanitize_textarea_field((string) $value));
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);

        return $length >= 1 && $length <= 1000;
    }

    public static function validate_visual_editor_status($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (! is_scalar($value)) {
            return false;
        }

        return in_array(sanitize_key((string) $value), ['draft', 'needs_review', 'reviewed', 'published'], true);
    }

    public static function validate_visual_editor_segments($value): bool
    {
        if (! is_array($value) || $value === [] || count($value) > 120) {
            return false;
        }

        foreach ($value as $segment) {
            if (! self::validate_visual_editor_text($segment)) {
                return false;
            }
        }

        return true;
    }

    public static function validate_visual_editor_selector($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (! is_scalar($value)) {
            return false;
        }

        $selector = sanitize_text_field((string) $value);
        $length = function_exists('mb_strlen') ? mb_strlen($selector) : strlen($selector);

        return $length <= 300 && strpos($selector, "\0") === false;
    }

    public static function sanitize_visual_editor_url($value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $url = esc_url_raw(Input::scalar_string($value));
        if ($url === '') {
            return '';
        }

        $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        $urlHost = self::normalize_visual_editor_host((string) wp_parse_url($url, PHP_URL_HOST));
        if ($urlHost === '') {
            return '';
        }

        return in_array($urlHost, self::allowed_visual_editor_url_hosts(), true) ? $url : '';
    }

    /** @return list<string> */
    private static function allowed_visual_editor_url_hosts(): array
    {
        $hosts = [self::normalize_visual_editor_host((string) wp_parse_url(home_url(), PHP_URL_HOST))];
        foreach (LanguageDomainMapper::map() as $baseUrl) {
            $hosts[] = self::normalize_visual_editor_host((string) wp_parse_url((string) $baseUrl, PHP_URL_HOST));
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    private static function normalize_visual_editor_host(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?: $host;
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }

        return $host;
    }

    public static function validate_visual_editor_url($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return self::sanitize_visual_editor_url($value) !== '';
    }
}
