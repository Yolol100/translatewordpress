<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support\Concerns;

use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

trait NormalizesSettings
{
    /**
     * Normalize settings loaded from previous releases or direct option edits.
     *
     * @param array<string, mixed> $settings Raw settings.
     * @return array<string, mixed>
     */
    private static function normalize(array $settings): array
    {
        foreach (self::boolean_keys() as $key) {
            $settings[$key] = self::bool_value($settings[$key] ?? false);
        }

        $settings['url_mode'] = 'subdirectory';
        $settings['max_buffer_size'] = self::bounded_absint($settings['max_buffer_size'] ?? 2097152, 2097152, 100000, 5000000);
        $settings['max_replacements'] = self::bounded_absint($settings['max_replacements'] ?? 1000, 1000, 10, 5000);
        $settings['scan_batch_size'] = self::bounded_absint($settings['scan_batch_size'] ?? 25, 25, 1, 100);
        $settings['cache_ttl'] = self::bounded_absint($settings['cache_ttl'] ?? (12 * HOUR_IN_SECONDS), 12 * HOUR_IN_SECONDS, 300, DAY_IN_SECONDS * 7);
        $settings['csv_preview_rows'] = self::bounded_absint($settings['csv_preview_rows'] ?? 250, 250, 20, 1000);
        $settings['csv_import_max_rows'] = self::bounded_absint($settings['csv_import_max_rows'] ?? 10000, 10000, 100, 50000);
        $settings['ai_provider'] = self::allowed_key($settings['ai_provider'] ?? 'openai', ['openai', 'deepl'], 'openai');
        $settings['ai_model'] = sanitize_text_field(Input::scalar_string($settings['ai_model'] ?? ($settings['ai_provider'] === 'deepl' ? 'deepl-api' : 'gpt-4o-mini')));
        $settings['ai_tone'] = self::allowed_key($settings['ai_tone'] ?? 'professional', ['professional', 'friendly', 'formal', 'casual', 'seo'], 'professional');
        $settings['ai_formality'] = self::allowed_key($settings['ai_formality'] ?? 'default', ['default', 'more', 'less', 'prefer_more', 'prefer_less'], 'default');
        $settings['switcher_layout'] = self::allowed_key($settings['switcher_layout'] ?? 'dropdown', self::switcher_layouts(), 'dropdown');
        $settings['switcher_style'] = self::allowed_key($settings['switcher_style'] ?? 'light', self::switcher_styles(), 'light');
        $settings['switcher_position'] = self::allowed_key($settings['switcher_position'] ?? 'bottom-right', self::switcher_positions(), 'bottom-right');
        $settings['language_domains'] = self::sanitize_language_domains($settings['language_domains'] ?? '');
        $settings['exclude_selectors'] = Input::textarea($settings['exclude_selectors'] ?? self::defaults()['exclude_selectors']);
        $settings['exclude_paths'] = Input::textarea($settings['exclude_paths'] ?? self::defaults()['exclude_paths']);

        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $input
     */
    private static function update_numeric_setting(array &$settings, array $input, string $key, int $min, int $max): void
    {
        if (isset($input[$key])) {
            $settings[$key] = self::bounded_absint($input[$key], (int) $settings[$key], $min, $max);
        }
    }

    private static function bounded_absint($value, int $default, int $min, int $max): int
    {
        return max($min, min($max, Input::absint($value, $default)));
    }
}
