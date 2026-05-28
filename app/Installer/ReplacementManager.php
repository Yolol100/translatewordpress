<?php

declare(strict_types=1);

namespace Webactueel\Translate\Installer;

if (! defined('ABSPATH')) {
    exit;
}

final class ReplacementManager
{
    /** @var array<int, string> */
    private const MATCH_TEXT_DOMAINS = [
        'webactueel-translate-language-dropdowns',
        'webactueel-translate',
        'webactueel-language-dropdowns',
        'wat-translate',
    ];

    /** @var array<int, string> */
    private const MATCH_PLUGIN_NAMES = [
        'webactueel translate',
        'webactueel translate language dropdowns',
        'webactueel language dropdowns',
        'webactueel vertalen',
        'webactueel taal dropdowns',
    ];

    /** @var array<int, string> */
    private const MATCH_SLUG_PARTS = [
        'webactueel-translate',
        'webactueel-translate-language-dropdowns',
        'webactueel-language-dropdowns',
        'wat-translate',
        'wat-language-dropdown',
    ];

    /** @var array<int, string> */
    private const STRONG_FILE_FINGERPRINTS = [
        'Webactueel\\Translate',
        'WAT_VERSION',
        'WAT_PLUGIN_FILE',
        'WAT_TEXT_DOMAIN',
        'wat_settings',
        'wat_languages',
        'wat_translation',
        'webactueel-translate-language-dropdowns',
    ];

    /** @var array<int, string> */
    private const FUNCTIONAL_KEYWORDS = [
        'frontend translation',
        'language dropdown',
        'language switcher',
        'clean language urls',
        'csv import/export',
        'hreflang',
        'manual translations',
        'universal frontend translation',
    ];

    public static function replace_older_installations(): void
    {
        if (! is_admin() || ! current_user_can('activate_plugins')) {
            return;
        }

        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $current = plugin_basename(WAT_PLUGIN_FILE);
        $targets = [];

        foreach ($plugins as $pluginFile => $headers) {
            if ($pluginFile === $current) {
                continue;
            }

            if (self::is_replacement_target((string) $pluginFile, is_array($headers) ? $headers : [])) {
                $targets[] = (string) $pluginFile;
            }
        }

        $targets = array_values(array_unique($targets));
        if ($targets === []) {
            delete_option('wat_replacement_cleanup_targets');
            delete_option('wat_replacement_cleanup_error');
            delete_option('wat_replaced_plugins');
            return;
        }

        // Never deactivate or delete another plugin during activation.
        // Store detected candidates for explicit, human-reviewed cleanup instead.
        update_option('wat_replacement_cleanup_targets', $targets, false);
        delete_option('wat_replacement_cleanup_error');
        delete_option('wat_replaced_plugins');
    }

    /**
     * Match older/similar Webactueel Translate builds by identity and code fingerprints.
     *
     * This is intentionally strict enough to avoid deleting unrelated multilingual plugins
     * such as Polylang, WPML or TranslatePress, but version-independent for older forks,
     * renamed folders and earlier Webactueel language-dropdown builds.
     *
     * @param array<string, mixed> $headers Plugin header data from get_plugins().
     */
    private static function is_replacement_target(string $pluginFile, array $headers): bool
    {
        $slug = strtolower(str_replace('\\', '/', $pluginFile));
        $folder = strtolower((string) dirname($slug));
        $name = self::normalize((string) ($headers['Name'] ?? ''));
        $description = self::normalize((string) ($headers['Description'] ?? ''));
        $textDomain = strtolower(trim((string) ($headers['TextDomain'] ?? '')));
        $headerText = trim($name . ' ' . $description . ' ' . self::normalize($textDomain));

        if ($textDomain !== '' && in_array($textDomain, self::MATCH_TEXT_DOMAINS, true)) {
            return true;
        }

        if ($name !== '' && in_array($name, self::MATCH_PLUGIN_NAMES, true)) {
            return true;
        }

        foreach (self::MATCH_SLUG_PARTS as $part) {
            if (str_contains($slug, $part) || str_contains($folder, $part)) {
                return true;
            }
        }

        if (str_contains($headerText, 'webactueel') && self::has_translate_or_language_context($headerText)) {
            return true;
        }

        $fingerprintScore = self::file_fingerprint_score($pluginFile);
        if ($fingerprintScore >= 2) {
            return true;
        }

        // Last-resort functional match: only flag plugins that are explicitly a
        // Webactueel/WAT build and also describe the same translation/dropdown role.
        if ((str_contains($slug, 'webactueel') || str_contains($slug, 'wat-') || str_contains($headerText, 'webactueel'))
            && self::functional_match_score($headerText) >= 2) {
            return true;
        }

        return false;
    }

    private static function file_fingerprint_score(string $pluginFile): int
    {
        $path = trailingslashit(WP_PLUGIN_DIR) . ltrim(str_replace('\\', '/', $pluginFile), '/');
        if (! is_readable($path) || ! is_file($path)) {
            return 0;
        }

        $contents = (string) file_get_contents($path, false, null, 0, 131072);
        if ($contents === '') {
            return 0;
        }

        $score = 0;
        foreach (self::STRONG_FILE_FINGERPRINTS as $fingerprint) {
            if (str_contains($contents, $fingerprint)) {
                ++$score;
            }
        }

        return $score;
    }

    private static function functional_match_score(string $value): int
    {
        $score = 0;
        foreach (self::FUNCTIONAL_KEYWORDS as $keyword) {
            if (str_contains($value, self::normalize($keyword))) {
                ++$score;
            }
        }
        return $score;
    }

    private static function has_translate_or_language_context(string $value): bool
    {
        return str_contains($value, 'translate')
            || str_contains($value, 'translation')
            || str_contains($value, 'vertalen')
            || str_contains($value, 'taal')
            || str_contains($value, 'language')
            || str_contains($value, 'dropdown')
            || str_contains($value, 'switcher');
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';
        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }
}
