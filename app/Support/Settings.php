<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

use Webactueel\Translate\Support\Concerns\NormalizesSettings;
use Webactueel\Translate\Support\Concerns\SanitizesSettingValues;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: public wat_* hooks are intentional.

final class Settings
{
    use NormalizesSettings;
    use SanitizesSettingValues;

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $settingsCache = null;

    public static function defaults(): array
    {
        return [
            'frontend_enabled' => true,
            'safe_mode' => true,
            'frontend_strict_request_guard' => true,
            'compatibility_override' => false,
            'browser_redirect' => false,
            'media_translation_enabled' => true,
            'woocommerce_deep_translation_enabled' => true,
            'gettext_discovery_enabled' => false,
            'runtime_discovery_enabled' => false,
            'conditional_publish_enabled' => false,
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
            'gettext_discovery_max_per_request' => 50,
            'runtime_discovery_max_per_request' => 100,
            'scan_batch_size' => 25,
            'csv_preview_rows' => 250,
            'csv_import_max_rows' => 10000,
            'delete_data_on_uninstall' => false,
            'debug_logging' => false,
            'ai_enabled' => false,
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
            'ai_custom_endpoint' => '',
            'ai_tone' => 'professional',
            'ai_formality' => 'default',
            'ai_review_required' => true,
            'ai_context_enabled' => false,
            'ai_site_context' => '',
            'ai_target_audience' => '',
            'ai_brand_terms' => '',
            'ai_do_not_translate' => '',
            'performance_monitoring' => false,
            'switcher_layout' => 'dropdown',
            'switcher_style' => 'light',
            'switcher_floating' => false,
            'switcher_hide_untranslated' => false,
            'switcher_position' => 'bottom-right',
            'gettext_discovery_domains' => "default\nwoocommerce\nelementor\nwordpress-seo\nrank-math",
            'exclude_selectors' => ".notranslate\n[translate=\"no\"]\n#wpadminbar\n.wat-language-switcher",
            'exclude_paths' => "/checkout/\n/cart/\n/my-account/\n/order-pay/\n/order-received/\n/wp-login.php",
        ];
    }

    public static function all(): array
    {
        if (self::$settingsCache !== null) {
            return self::$settingsCache;
        }

        $normalized = self::normalize(array_merge(self::defaults(), SettingsRepository::load()));
        $provider = self::allowed_key($normalized['ai_provider'] ?? 'openai', ['openai', 'deepl', 'openai_compatible', 'google_translate'], 'openai');
        $normalized['ai_has_api_key'] = self::has_ai_api_key($provider);
        $normalized['ai_database_key_storage_allowed'] = self::allows_db_ai_credentials();

        self::$settingsCache = $normalized;
        return self::$settingsCache;
    }

    public static function ai_api_key(string $provider): string
    {
        return AiCredentialResolver::api_key($provider);
    }

    public static function has_ai_api_key(string $provider): bool
    {
        return AiCredentialResolver::has_api_key($provider);
    }

    public static function allows_db_ai_credentials(): bool
    {
        return AiCredentialResolver::allows_db_credentials();
    }

    public static function update(array $input): array
    {
        $settings = SettingsSanitizer::for_update(self::all(), $input);
        SettingsRepository::persist($settings);
        self::reset_cache();
        do_action('wat_settings_updated', $settings);

        return self::all();
    }

    public static function sanitize_ai_endpoint($value): string
    {
        return AiEndpointValidator::sanitize($value);
    }

    /** @return list<string> */
    public static function allowed_ai_models(string $provider): array
    {
        return AiModelPolicy::allowed_models($provider);
    }

    public static function sanitize_ai_model($model, string $provider): string
    {
        return AiModelPolicy::sanitize_model($model, $provider);
    }

    private static function reset_cache(): void
    {
        self::$settingsCache = null;
        AiCredentialResolver::reset_cache();
    }
}
