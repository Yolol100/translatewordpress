<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Routing;

if (! defined('ABSPATH')) {
    exit;
}

final class PublicQuerySanitizer
{
    private const MAX_DEPTH = 4;
    private const MAX_ITEMS = 50;
    private const MAX_KEY_LENGTH = 64;
    private const MAX_VALUE_LENGTH = 300;

    /**
     * @param array<string, mixed> $query Raw query args from parse_str() or callers.
     * @return array<string, mixed>
     */
    public static function public_query_args(array $query): array
    {
        foreach (['wat_switch_lang', 'wat_lang', 'wat_language', 'wat_path'] as $key) {
            unset($query[$key]);
        }

        $clean = [];
        $count = 0;
        foreach ($query as $key => $value) {
            if ($count >= self::MAX_ITEMS) {
                break;
            }

            $key = self::sanitize_public_query_key((string) $key, true);
            if ($key === '' || str_starts_with($key, 'wat_') || is_object($value)) {
                continue;
            }

            $item = self::sanitize_public_query_value($value, 0);
            if ($item !== null && $item !== '' && $item !== []) {
                $clean[$key] = $item;
                ++$count;
            }
        }

        return $clean;
    }

    private static function sanitize_public_query_key(string $key, bool $allowBrackets = false): string
    {
        $pattern = $allowBrackets ? '/[^A-Za-z0-9_\-\[\]]/' : '/[^A-Za-z0-9_\-]/';
        $key = preg_replace($pattern, '', $key) ?: '';
        return substr($key, 0, self::MAX_KEY_LENGTH);
    }

    /**
     * @param mixed $value Raw query value.
     * @return mixed|null Sanitized value or null when it should be removed.
     */
    private static function sanitize_public_query_value($value, int $depth)
    {
        if (is_object($value) || $depth > self::MAX_DEPTH) {
            return null;
        }

        if (is_array($value)) {
            $clean = [];
            $count = 0;
            foreach ($value as $key => $item) {
                if ($count >= self::MAX_ITEMS) {
                    break;
                }

                $cleanKey = is_int($key) ? $key : self::sanitize_public_query_key((string) $key);
                if ($cleanKey === '') {
                    continue;
                }

                $cleanValue = self::sanitize_public_query_value($item, $depth + 1);
                if ($cleanValue !== null && $cleanValue !== '' && $cleanValue !== []) {
                    $clean[$cleanKey] = $cleanValue;
                    ++$count;
                }
            }
            return $clean;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $value = sanitize_text_field(wp_unslash((string) $value));
        if ((function_exists('mb_strlen') ? mb_strlen($value) : strlen($value)) > self::MAX_VALUE_LENGTH) {
            $value = function_exists('mb_substr') ? mb_substr($value, 0, self::MAX_VALUE_LENGTH) : substr($value, 0, self::MAX_VALUE_LENGTH);
        }

        return $value;
    }
}
