<?php

declare(strict_types=1);

namespace Webactueel\Translate\Cache;

use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

final class TranslationCache
{
    public static function version(): string
    {
        return (string) get_option('wat_cache_version', '1');
    }

    public static function key(string $languageCode): string
    {
        return 'wat_translation_map_' . sanitize_key($languageCode) . '_' . self::version();
    }

    public static function get(string $languageCode): ?array
    {
        if (! Settings::all()['cache_enabled']) {
            return null;
        }
        $key = self::key($languageCode);
        $cached = wp_cache_get($key, 'webactueel-translate-language-dropdowns');
        if ($cached === false) {
            $cached = get_transient($key);
        }
        return is_array($cached) ? $cached : null;
    }

    public static function set(string $languageCode, array $map): void
    {
        $settings = Settings::all();
        if (! $settings['cache_enabled']) {
            return;
        }
        $key = self::key($languageCode);
        $ttl = absint($settings['cache_ttl']);
        wp_cache_set($key, $map, 'webactueel-translate-language-dropdowns', $ttl);
        set_transient($key, $map, $ttl);
    }
}
