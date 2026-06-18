<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Routing;

if (! defined('ABSPATH')) {
    exit;
}

final class PublicQuerySanitizer
{
    /**
     * Keep only public query arguments when building language URLs.
     *
     * @param array<string, mixed> $query Raw query args from parse_str() or callers.
     * @return array<string, mixed>
     */
    public static function public_query_args(array $query): array
    {
        foreach (['wat_switch_lang', 'wat_lang', 'wat_language', 'wat_path'] as $key) {
            unset($query[$key]);
        }

        $clean = [];
        foreach ($query as $key => $value) {
            $key = preg_replace('/[^A-Za-z0-9_\-\[\]]/', '', (string) $key) ?: '';
            if ($key === '' || str_starts_with($key, 'wat_') || is_object($value)) {
                continue;
            }

            if (is_array($value)) {
                $items = self::sanitize_public_query_value($value);
                if ($items !== null && $items !== []) {
                    $clean[$key] = $items;
                }
                continue;
            }

            $item = self::sanitize_public_query_value($value);
            if ($item !== null && $item !== '') {
                $clean[$key] = $item;
            }
        }

        return $clean;
    }

    /**
     * Sanitize query values while preserving nested public filter arrays.
     *
     * @param mixed $value Raw query value.
     * @return mixed|null Sanitized value or null when it should be removed.
     */
    private static function sanitize_public_query_value($value)
    {
        if (is_object($value)) {
            return null;
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $cleanKey = is_int($key) ? $key : (preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $key) ?: '');
                if ($cleanKey === '') {
                    continue;
                }
                $cleanValue = self::sanitize_public_query_value($item);
                if ($cleanValue !== null && $cleanValue !== '' && $cleanValue !== []) {
                    $clean[$cleanKey] = $cleanValue;
                }
            }
            return $clean;
        }

        if (! is_scalar($value)) {
            return null;
        }

        return sanitize_text_field(wp_unslash((string) $value));
    }
}
