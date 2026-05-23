<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Defensive input normalization helpers for REST, query, cookie and option values.
 */
final class Input
{
    /**
     * Return an unslashed scalar string or a safe default for arrays/objects/resources.
     *
     * @param mixed $value Raw input value.
     */
    public static function scalar_string($value, string $default = ''): string
    {
        if (is_scalar($value)) {
            return (string) wp_unslash($value);
        }

        return $default;
    }

    /**
     * Sanitize a scalar text field. Arrays/objects/resources become the default.
     *
     * @param mixed $value Raw input value.
     */
    public static function text($value, string $default = ''): string
    {
        return sanitize_text_field(self::scalar_string($value, $default));
    }

    /**
     * Sanitize a scalar key. Arrays/objects/resources become the default.
     *
     * @param mixed $value Raw input value.
     */
    public static function key($value, string $default = ''): string
    {
        return sanitize_key(self::scalar_string($value, $default));
    }

    /**
     * Sanitize scalar textarea input. Arrays/objects/resources become the default.
     *
     * @param mixed $value Raw input value.
     */
    public static function textarea($value, string $default = ''): string
    {
        return sanitize_textarea_field(self::scalar_string($value, $default));
    }

    /**
     * Convert only scalar numeric-ish values to absint. Arrays/objects/resources become the default.
     *
     * @param mixed $value Raw input value.
     */
    public static function absint($value, int $default = 0): int
    {
        return is_scalar($value) ? absint($value) : $default;
    }

    /**
     * Sanitize comma-separated strings or flat arrays into unique keys.
     *
     * @param mixed $value Raw input value.
     * @return array<int, string>
     */
    public static function key_list($value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $key = sanitize_key((string) wp_unslash($item));
            if ($key !== '') {
                $items[] = $key;
            }
        }

        return array_values(array_unique($items));
    }

    public static function get_key(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Read-only request value normalization; callers decide whether nonce verification is required.
        return isset($_GET[$key]) ? self::key($_GET[$key], $default) : $default;
    }

    public static function get_text(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Read-only request value normalization; callers decide whether nonce verification is required.
        return isset($_GET[$key]) ? self::text($_GET[$key], $default) : $default;
    }

    public static function get_exists(string $key): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request presence check; callers decide whether nonce verification is required.
        return isset($_GET[$key]);
    }

    /**
     * @return array<int|string, string>|array<int|string, mixed>
     */
    public static function get_array_text(string $key): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Read-only request value normalization; callers decide whether nonce verification is required.
        if (! isset($_GET[$key]) || ! is_array($_GET[$key])) {
            return [];
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Read-only request value normalization; callers decide whether nonce verification is required.
        $value = wp_unslash($_GET[$key]);
        return is_array($value) ? map_deep($value, 'sanitize_text_field') : [];
    }

    public static function post_text(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Centralized input helper; nonce/capability checks are performed by the calling REST/admin handlers.
        return isset($_POST[$key]) ? self::text($_POST[$key], $default) : $default;
    }

    /**
     * @return array<int|string, string>|array<int|string, mixed>
     */
    public static function post_array_text(string $key): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Presence/type check before unslash and sanitization; nonce/capability checks are performed by callers.
        if (! isset($_POST[$key]) || ! is_array($_POST[$key])) {
            return [];
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately with map_deep(); nonce/capability checks are performed by callers.
        $value = wp_unslash($_POST[$key]);
        return is_array($value) ? map_deep($value, 'sanitize_text_field') : [];
    }

    public static function cookie_key(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Centralized input helper; self::key() unslashes and sanitizes scalar values.
        return isset($_COOKIE[$key]) ? self::key($_COOKIE[$key], $default) : $default;
    }

    public static function server_text(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Centralized input helper; self::text() unslashes and sanitizes scalar values.
        return isset($_SERVER[$key]) ? self::text($_SERVER[$key], $default) : $default;
    }

    public static function server_method(string $default = 'GET'): string
    {
        $method = strtoupper(self::server_text('REQUEST_METHOD', $default));
        return preg_match('/^[A-Z]+$/', $method) ? $method : $default;
    }
}
