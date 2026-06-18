<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support\Concerns;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Formatting;

if (! defined('ABSPATH')) {
    exit;
}

trait SanitizesSettingValues
{
    /** @return list<string> */
    private static function boolean_keys(): array
    {
        return [
            'frontend_enabled',
            'safe_mode',
            'frontend_strict_request_guard',
            'compatibility_override',
            'browser_redirect',
            'media_translation_enabled',
            'woocommerce_deep_translation_enabled',
            'gettext_discovery_enabled',
            'runtime_discovery_enabled',
            'conditional_publish_enabled',
            'translator_review_required',
            'remember_language',
            'hreflang_enabled',
            'hreflang_force',
            'x_default_enabled',
            'canonical_enabled',
            'multilingual_sitemap_enabled',
            'cache_enabled',
            'delete_data_on_uninstall',
            'debug_logging',
            'switcher_floating',
            'switcher_hide_untranslated',
            'ai_enabled',
            'ai_review_required',
            'ai_context_enabled',
            'performance_monitoring',
        ];
    }

    /** @return list<string> */
    private static function switcher_layouts(): array
    {
        return ['dropdown', 'inline', 'flags_name', 'flags', 'code', 'flag_code', 'name_code', 'flags_name_code'];
    }

    /** @return list<string> */
    private static function switcher_styles(): array
    {
        return ['light', 'dark', 'compact', 'outline', 'minimal'];
    }

    /** @return list<string> */
    private static function switcher_positions(): array
    {
        return ['bottom-right', 'bottom-left', 'top-right', 'top-left'];
    }

    private static function allowed_key($value, array $allowed, string $default): string
    {
        $key = Input::key($value);
        return in_array($key, $allowed, true) ? $key : $default;
    }

    private static function bool_value($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($filtered !== null) {
                return $filtered;
            }
        }

        if (is_array($value) || is_object($value) || is_resource($value)) {
            return false;
        }

        return ! empty($value);
    }

    private static function sanitize_language_domains($input): string
    {
        $lines = [];
        $append = static function (string $code, string $url) use (&$lines): void {
            $code = sanitize_key($code);
            $url = Formatting::base_url($url);
            if ($code !== '' && $url !== '') {
                $lines[] = $code . '|' . $url;
            }
        };

        if (is_array($input)) {
            foreach ($input as $key => $value) {
                if (is_array($value)) {
                    $append(Input::scalar_string($value['code'] ?? $key), Input::scalar_string($value['url'] ?? $value['domain'] ?? ''));
                    continue;
                }
                $append(Input::scalar_string($key), Input::scalar_string($value));
            }
            return implode("\n", array_values(array_unique($lines)));
        }

        foreach (preg_split('/\r\n|\r|\n/', Input::scalar_string($input)) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || strpos($line, '|') === false) {
                continue;
            }
            [$code, $url] = array_map('trim', explode('|', $line, 2));
            $append($code, $url);
        }
        return implode("\n", array_values(array_unique($lines)));
    }

    private static function limited_textarea($value, int $maxLength): string
    {
        $text = Input::textarea($value);
        if ($maxLength > 0 && (function_exists('mb_strlen') ? mb_strlen($text) : strlen($text)) > $maxLength) {
            return function_exists('mb_substr') ? mb_substr($text, 0, $maxLength) : substr($text, 0, $maxLength);
        }

        return $text;
    }

    private static function sanitize_discovery_domains($input): string
    {
        $domains = [];
        foreach (preg_split('/\r\n|\r|\n|,/', Input::scalar_string($input)) ?: [] as $line) {
            $domain = sanitize_key(trim((string) $line));
            if ($domain !== '') {
                $domains[] = $domain;
            }
        }

        return implode("\n", array_values(array_unique($domains)));
    }

}
