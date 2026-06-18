<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: public wat_* hooks are intentional.

final class AiCredentialResolver
{
    /**
     * @var array<string, mixed>|null
     */
    private static ?array $credentialsCache = null;

    /** @return list<string> */
    private static function providers(): array
    {
        return ['openai', 'deepl', 'openai_compatible', 'google_translate'];
    }

    public static function api_key(string $provider): string
    {
        $provider = self::normalize_provider($provider);
        $constant = self::api_key_constant($provider);
        $key = defined($constant) ? (string) constant($constant) : '';
        $filtered = apply_filters('wat_ai_api_key', $key, $provider);
        if (is_scalar($filtered) && trim((string) $filtered) !== '') {
            return trim((string) $filtered);
        }

        if (! self::allows_db_credentials()) {
            return '';
        }

        $credentials = self::stored_credentials();
        $dbKey = $credentials[$provider] ?? '';
        return is_scalar($dbKey) ? trim((string) $dbKey) : '';
    }

    public static function has_api_key(string $provider): bool
    {
        return self::api_key($provider) !== '';
    }

    public static function update_api_key(string $provider, string $apiKey): void
    {
        $provider = self::normalize_provider($provider);
        $credentials = get_option('wat_ai_credentials', []);
        $credentials = is_array($credentials) ? $credentials : [];
        $apiKey = trim(sanitize_text_field($apiKey));
        if ($apiKey === '' || ! self::allows_db_credentials()) {
            unset($credentials[$provider]);
        } else {
            $credentials[$provider] = $apiKey;
        }
        update_option('wat_ai_credentials', $credentials, false);
        self::reset_cache();
    }

    public static function allows_db_credentials(): bool
    {
        $enabled = defined('WAT_ENABLE_DB_AI_CREDENTIALS') && (bool) WAT_ENABLE_DB_AI_CREDENTIALS;
        return (bool) apply_filters('wat_allow_db_ai_credentials', $enabled);
    }

    public static function reset_cache(): void
    {
        self::$credentialsCache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function stored_credentials(): array
    {
        if (self::$credentialsCache !== null) {
            return self::$credentialsCache;
        }

        $credentials = get_option('wat_ai_credentials', []);
        self::$credentialsCache = is_array($credentials) ? $credentials : [];
        return self::$credentialsCache;
    }

    private static function api_key_constant(string $provider): string
    {
        if ($provider === 'deepl') {
            return 'WAT_DEEPL_API_KEY';
        }
        if ($provider === 'google_translate') {
            return 'WAT_GOOGLE_TRANSLATE_API_KEY';
        }
        return $provider === 'openai_compatible' ? 'WAT_OPENAI_COMPATIBLE_API_KEY' : 'WAT_OPENAI_API_KEY';
    }

    private static function normalize_provider(string $provider): string
    {
        $provider = sanitize_key($provider);
        return in_array($provider, self::providers(), true) ? $provider : 'openai';
    }
}
