<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

use Webactueel\Translate\Support\Concerns\SanitizesSettingValues;

if (! defined('ABSPATH')) {
    exit;
}

final class SettingsSanitizer
{
    use SanitizesSettingValues;

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function for_update(array $settings, array $input): array
    {
        self::update_boolean_settings($settings, $input);
        $settings['url_mode'] = 'subdirectory';
        self::update_numeric_settings($settings, $input);
        self::update_ai_settings($settings, $input);
        self::update_language_and_switcher_settings($settings, $input);
        self::update_exclusion_settings($settings, $input);

        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $input
     */
    private static function update_boolean_settings(array &$settings, array $input): void
    {
        foreach (self::boolean_keys() as $key) {
            if (array_key_exists($key, $input)) {
                $settings[$key] = self::bool_value($input[$key]);
            }
        }
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $input
     */
    private static function update_numeric_settings(array &$settings, array $input): void
    {
        self::update_numeric_setting($settings, $input, 'max_buffer_size', 100000, 5000000);
        self::update_numeric_setting($settings, $input, 'max_replacements', 10, 5000);
        self::update_numeric_setting($settings, $input, 'gettext_discovery_max_per_request', 1, 500);
        self::update_numeric_setting($settings, $input, 'runtime_discovery_max_per_request', 1, 1000);
        self::update_numeric_setting($settings, $input, 'scan_batch_size', 1, 100);
        self::update_numeric_setting($settings, $input, 'cache_ttl', 300, DAY_IN_SECONDS * 7);
        self::update_numeric_setting($settings, $input, 'csv_preview_rows', 20, 1000);
        self::update_numeric_setting($settings, $input, 'csv_import_max_rows', 100, 50000);
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $input
     */
    private static function update_ai_settings(array &$settings, array $input): void
    {
        if (isset($input['ai_provider'])) {
            $settings['ai_provider'] = self::allowed_key($input['ai_provider'], ['openai', 'deepl', 'openai_compatible', 'google_translate'], 'openai');
        }
        if (isset($input['ai_model'])) {
            $settings['ai_model'] = AiModelPolicy::sanitize_model($input['ai_model'], Input::key($settings['ai_provider'] ?? 'openai'));
        }
        if (isset($input['ai_custom_endpoint'])) {
            $settings['ai_custom_endpoint'] = AiEndpointValidator::sanitize($input['ai_custom_endpoint'] ?? '');
        }
        if (array_key_exists('ai_api_key', $input) && is_scalar($input['ai_api_key'])) {
            $apiKey = trim((string) $input['ai_api_key']);
            if ($apiKey !== '') {
                AiCredentialResolver::update_api_key(Input::key($settings['ai_provider'] ?? 'openai'), $apiKey);
            }
        }
        if (! empty($input['ai_api_key_clear'])) {
            AiCredentialResolver::update_api_key(Input::key($settings['ai_provider'] ?? 'openai'), '');
        }
        if (isset($input['ai_tone'])) {
            $settings['ai_tone'] = self::allowed_key($input['ai_tone'], ['professional', 'friendly', 'formal', 'casual', 'seo'], 'professional');
        }
        if (isset($input['ai_formality'])) {
            $settings['ai_formality'] = self::allowed_key($input['ai_formality'], ['default', 'more', 'less', 'prefer_more', 'prefer_less'], 'default');
        }
        foreach (['ai_site_context' => 1000, 'ai_target_audience' => 500, 'ai_brand_terms' => 1000, 'ai_do_not_translate' => 1000] as $key => $limit) {
            if (array_key_exists($key, $input)) {
                $settings[$key] = self::limited_textarea($input[$key], $limit);
            }
        }
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $input
     */
    private static function update_language_and_switcher_settings(array &$settings, array $input): void
    {
        if (isset($input['language_domains'])) {
            $settings['language_domains'] = self::sanitize_language_domains($input['language_domains']);
        }
        if (isset($input['switcher_layout'])) {
            $settings['switcher_layout'] = self::allowed_key($input['switcher_layout'], self::switcher_layouts(), 'dropdown');
        }
        if (isset($input['switcher_style'])) {
            $settings['switcher_style'] = self::allowed_key($input['switcher_style'], self::switcher_styles(), 'light');
        }
        if (isset($input['switcher_position'])) {
            $settings['switcher_position'] = self::allowed_key($input['switcher_position'], self::switcher_positions(), 'bottom-right');
        }
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $input
     */
    private static function update_exclusion_settings(array &$settings, array $input): void
    {
        if (isset($input['exclude_selectors'])) {
            $settings['exclude_selectors'] = Input::textarea($input['exclude_selectors']);
        }
        if (isset($input['exclude_paths'])) {
            $settings['exclude_paths'] = Input::textarea($input['exclude_paths']);
        }
        if (isset($input['gettext_discovery_domains'])) {
            $settings['gettext_discovery_domains'] = self::sanitize_discovery_domains($input['gettext_discovery_domains']);
        }
    }

    /** @param array<string, mixed> $settings */
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
