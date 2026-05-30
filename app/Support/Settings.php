<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

use Webactueel\Translate\Support\Concerns\NormalizesSettings;
use Webactueel\Translate\Support\Concerns\SanitizesSettingValues;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Temporary error handlers are used to safely inspect malformed serialized data.

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
            'ai_custom_endpoint' => '',
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
        $normalized = self::normalize(array_merge(self::defaults(), is_array($settings) ? $settings : []));
        $provider = self::allowed_key($normalized['ai_provider'] ?? 'openai', ['openai', 'deepl', 'openai_compatible'], 'openai');
        $normalized['ai_has_api_key'] = self::has_ai_api_key($provider);
        $normalized['ai_database_key_storage_allowed'] = self::allows_db_ai_credentials();
        return $normalized;
    }

    public static function ai_api_key(string $provider): string
    {
        $provider = self::allowed_key($provider, ['openai', 'deepl', 'openai_compatible'], 'openai');
        $constant = self::ai_api_key_constant($provider);
        $key = defined($constant) ? (string) constant($constant) : '';
        $filtered = apply_filters('wat_ai_api_key', $key, $provider);
        if (is_scalar($filtered) && trim((string) $filtered) !== '') {
            return trim((string) $filtered);
        }

        if (! self::allows_db_ai_credentials()) {
            return '';
        }

        $credentials = get_option('wat_ai_credentials', []);
        if (! is_array($credentials)) {
            return '';
        }
        $dbKey = $credentials[$provider] ?? '';
        return is_scalar($dbKey) ? trim((string) $dbKey) : '';
    }

    public static function has_ai_api_key(string $provider): bool
    {
        return self::ai_api_key($provider) !== '';
    }

    private static function update_ai_api_key(string $provider, string $apiKey): void
    {
        $provider = self::allowed_key($provider, ['openai', 'deepl', 'openai_compatible'], 'openai');
        $credentials = get_option('wat_ai_credentials', []);
        $credentials = is_array($credentials) ? $credentials : [];
        $apiKey = trim(sanitize_text_field($apiKey));
        if ($apiKey === '' || ! self::allows_db_ai_credentials()) {
            unset($credentials[$provider]);
        } else {
            $credentials[$provider] = $apiKey;
        }
        update_option('wat_ai_credentials', $credentials, false);
    }

    private static function ai_api_key_constant(string $provider): string
    {
        return $provider === 'deepl' ? 'WAT_DEEPL_API_KEY' : ($provider === 'openai_compatible' ? 'WAT_OPENAI_COMPATIBLE_API_KEY' : 'WAT_OPENAI_API_KEY');
    }

    public static function allows_db_ai_credentials(): bool
    {
        $enabled = defined('WAT_ENABLE_DB_AI_CREDENTIALS') && (bool) WAT_ENABLE_DB_AI_CREDENTIALS;
        return (bool) apply_filters('wat_allow_db_ai_credentials', $enabled);
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
            $settings['ai_provider'] = self::allowed_key($input['ai_provider'], ['openai', 'deepl', 'openai_compatible'], 'openai');
        }
        if (isset($input['ai_model'])) {
            $settings['ai_model'] = self::sanitize_ai_model($input['ai_model'], Input::key($settings['ai_provider'] ?? 'openai'));
        }
        if (isset($input['ai_custom_endpoint'])) {
            $settings['ai_custom_endpoint'] = self::sanitize_ai_endpoint($input['ai_custom_endpoint'] ?? '');
        }
        if (array_key_exists('ai_api_key', $input) && is_scalar($input['ai_api_key'])) {
            $api_key = trim((string) $input['ai_api_key']);
            if ($api_key !== '') {
                self::update_ai_api_key(Input::key($settings['ai_provider'] ?? 'openai'), $api_key);
            }
        }
        if (! empty($input['ai_api_key_clear'])) {
            self::update_ai_api_key(Input::key($settings['ai_provider'] ?? 'openai'), '');
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
        return self::all();
    }

    public static function sanitize_ai_endpoint($value): string
    {
        $endpoint = esc_url_raw(Input::scalar_string($value));
        if ($endpoint === '') {
            return '';
        }

        $parts = wp_parse_url($endpoint);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            return '';
        }

        if (! empty($parts['user']) || ! empty($parts['pass'])) {
            return '';
        }

        $host = strtolower((string) $parts['host']);
        $allowedHosts = apply_filters('wat_ai_custom_endpoint_allowed_hosts', []);
        if (is_array($allowedHosts) && $allowedHosts !== []) {
            $allowedHosts = array_map(static fn($allowedHost): string => strtolower((string) $allowedHost), $allowedHosts);
            if (! in_array($host, $allowedHosts, true)) {
                return '';
            }
        }

        $isPrivateIp = filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local');
        $allowPrivate = (bool) apply_filters('wat_ai_allow_private_custom_endpoint', false, $endpoint, $host);
        if (($isPrivateIp || $isLocalHost || self::host_resolves_to_private_address($host)) && ! $allowPrivate) {
            return '';
        }

        return $endpoint;
    }

    /**
     * Guard custom AI endpoints against DNS rebinding to local or reserved networks.
     */
    private static function host_resolves_to_private_address(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        $addresses = [];
        if (function_exists('gethostbynamel')) {
            $ipv4 = gethostbynamel($host);
            if (is_array($ipv4)) {
                $addresses = array_merge($addresses, $ipv4);
            }
        }

        if (function_exists('dns_get_record')) {
            set_error_handler(static function (): bool {
                return true;
            });
            try {
                $records = dns_get_record($host, DNS_A + DNS_AAAA);
            } finally {
                restore_error_handler();
            }
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (! empty($record['ip']) && is_string($record['ip'])) {
                        $addresses[] = $record['ip'];
                    }
                    if (! empty($record['ipv6']) && is_string($record['ipv6'])) {
                        $addresses[] = $record['ipv6'];
                    }
                }
            }
        }

        foreach (array_unique($addresses) as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP)
                && ! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            ) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public static function allowed_ai_models(string $provider): array
    {
        $provider = self::allowed_key($provider, ['openai', 'deepl', 'openai_compatible'], 'openai');
        if ($provider === 'deepl') {
            $models = ['deepl-api'];
        } elseif ($provider === 'openai_compatible') {
            $models = ['gpt-4o-mini', 'gpt-4.1-mini', 'llama-3.1-70b-versatile', 'mistral-large-latest'];
        } else {
            $models = ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1', 'gpt-4.1-nano'];
        }

        $filtered = apply_filters('wat_allowed_ai_models', $models, $provider);
        if (! is_array($filtered)) {
            $filtered = $models;
        }

        $allowed = [];
        foreach ($filtered as $model) {
            if (! is_scalar($model)) {
                continue;
            }
            $model = sanitize_text_field((string) $model);
            if ($model !== '') {
                $allowed[] = $model;
            }
        }

        return array_values(array_unique($allowed));
    }

    public static function sanitize_ai_model($model, string $provider): string
    {
        $provider = self::allowed_key($provider, ['openai', 'deepl', 'openai_compatible'], 'openai');
        $default = $provider === 'deepl' ? 'deepl-api' : 'gpt-4o-mini';
        $model = sanitize_text_field(Input::scalar_string($model, $default));

        if ($provider === 'openai_compatible') {
            return $model !== '' ? $model : $default;
        }

        $allowed = self::allowed_ai_models($provider);

        return in_array($model, $allowed, true) ? $model : $default;
    }
}
