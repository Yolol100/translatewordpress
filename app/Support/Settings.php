<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

use Webactueel\Translate\Support\Concerns\NormalizesSettings;
use Webactueel\Translate\Support\Concerns\SanitizesSettingValues;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: public wat_* hooks are intentional.

final class Settings
{
    use NormalizesSettings;
    use SanitizesSettingValues;

    public static function defaults(): array
    {
        return [
            'frontend_enabled' => true,
            'safe_mode' => true,
            'compatibility_override' => false,
            'browser_redirect' => false,
            'media_translation_enabled' => true,
            'woocommerce_deep_translation_enabled' => true,
            'translator_review_required' => true,
            'remember_language' => true,
            'url_mode' => 'subdirectory',
            'language_domains' => '',
            'hreflang_enabled' => true,
            'hreflang_force' => false,
            'x_default_enabled' => true,
            'canonical_enabled' => true,
            'multilingual_sitemap_enabled' => true,
            'cache_enabled' => true,
            'cache_ttl' => 12 * HOUR_IN_SECONDS,
            'max_buffer_size' => 2097152,
            'max_replacements' => 1000,
            'scan_batch_size' => 25,
            'csv_preview_rows' => 250,
            'csv_import_max_rows' => 10000,
            'delete_data_on_uninstall' => false,
            'debug_logging' => false,
            'ai_enabled' => false,
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
            'ai_tone' => 'professional',
            'ai_formality' => 'default',
            'ai_review_required' => true,
            'performance_monitoring' => true,
            'switcher_layout' => 'dropdown',
            'switcher_style' => 'light',
            'switcher_floating' => false,
            'switcher_position' => 'bottom-right',
            'exclude_selectors' => ".notranslate\n[translate=\"no\"]\n#wpadminbar\n.wat-language-switcher",
            'exclude_paths' => "/checkout/\n/cart/\n/my-account/\n/wp-login.php",
        ];
    }

    public static function all(): array
    {
        $settings = get_option('wat_settings', []);
        return self::normalize(array_merge(self::defaults(), is_array($settings) ? $settings : []));
    }

    public static function update(array $input): array
    {
        $settings = self::all();

        foreach (self::boolean_keys() as $key) {
            if (array_key_exists($key, $input)) {
                $settings[$key] = self::bool_value($input[$key]);
            }
        }

        // URL mode is intentionally fixed; per-language domains are configured via language_domains.
        $settings['url_mode'] = 'subdirectory';
        self::update_numeric_setting($settings, $input, 'max_buffer_size', 100000, 5000000);
        self::update_numeric_setting($settings, $input, 'max_replacements', 10, 5000);
        self::update_numeric_setting($settings, $input, 'scan_batch_size', 1, 100);
        self::update_numeric_setting($settings, $input, 'cache_ttl', 300, DAY_IN_SECONDS * 7);
        self::update_numeric_setting($settings, $input, 'csv_preview_rows', 20, 1000);
        self::update_numeric_setting($settings, $input, 'csv_import_max_rows', 100, 50000);

        if (isset($input['ai_provider'])) {
            $settings['ai_provider'] = self::allowed_key($input['ai_provider'], ['openai', 'deepl'], 'openai');
        }
        if (isset($input['ai_model'])) {
            $settings['ai_model'] = sanitize_text_field(Input::scalar_string($input['ai_model']));
        }
        if (isset($input['ai_tone'])) {
            $settings['ai_tone'] = self::allowed_key($input['ai_tone'], ['professional', 'friendly', 'formal', 'casual', 'seo'], 'professional');
        }
        if (isset($input['ai_formality'])) {
            $settings['ai_formality'] = self::allowed_key($input['ai_formality'], ['default', 'more', 'less', 'prefer_more', 'prefer_less'], 'default');
        }

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
        if (isset($input['exclude_selectors'])) {
            $settings['exclude_selectors'] = Input::textarea($input['exclude_selectors']);
        }
        if (isset($input['exclude_paths'])) {
            $settings['exclude_paths'] = Input::textarea($input['exclude_paths']);
        }

        update_option('wat_settings', $settings, false);
        update_option('wat_delete_data_on_uninstall', $settings['delete_data_on_uninstall'] ? '1' : '0', false);
        do_action('wat_settings_updated', $settings);
        return $settings;
    }
}
