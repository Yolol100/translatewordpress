<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation\Concerns;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

trait AiProviderConfiguration
{
    /** @return array<string, mixed> */
    public static function capabilities(): array
    {
        $settings = Settings::all();
        $provider = self::provider($settings);
        $databaseKeyStorageAllowed = Settings::allows_db_ai_credentials();
        return [
            'enabled' => ! empty($settings['ai_enabled']),
            'provider' => $provider,
            'model' => self::model($settings, $provider),
            'tone' => Input::key($settings['ai_tone'] ?? 'professional'),
            'formality' => Input::key($settings['ai_formality'] ?? 'default'),
            'contextProfileEnabled' => ! empty($settings['ai_context_enabled']),
            'hasApiKey' => self::api_key($provider) !== '',
            'hasEndpoint' => $provider !== 'openai_compatible' || self::custom_endpoint($settings) !== '',
            'providers' => self::providers(),
            'supportsReviewWorkflow' => true,
            'storesApiKey' => $databaseKeyStorageAllowed && Settings::ai_api_key($provider) !== '',
            'databaseKeyStorageAllowed' => $databaseKeyStorageAllowed,
            'supportsServerConstants' => true,
            'note' => __('AI API-sleutels kunnen veilig via serverconstanten of de wat_ai_api_key filter worden geleverd. Database-opslag via de beheerinterface is standaard uitgeschakeld en werkt alleen wanneer WAT_ENABLE_DB_AI_CREDENTIALS of de wat_allow_db_ai_credentials filter dit expliciet toestaat. Ingeschakelde AI-vertaling verstuurt de aangeboden tekst naar de gekozen externe provider; gebruik dit alleen voor content die extern verwerkt mag worden.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    private static function provider(array $settings): string
    {
        $provider = Input::key($settings['ai_provider'] ?? 'openai');
        return in_array($provider, ['openai', 'deepl', 'openai_compatible', 'google_translate'], true) ? $provider : 'openai';
    }

    private static function model(array $settings, string $provider): string
    {
        return Settings::sanitize_ai_model($settings['ai_model'] ?? '', $provider);
    }

    /** @return list<array<string, string>> */
    private static function providers(): array
    {
        return [
            ['label' => 'OpenAI', 'value' => 'openai'],
            ['label' => 'DeepL', 'value' => 'deepl'],
            ['label' => 'Google Translate', 'value' => 'google_translate'],
            ['label' => __('OpenAI-compatible API', 'webactueel-translate-language-dropdowns'), 'value' => 'openai_compatible'],
        ];
    }

    private static function custom_endpoint(array $settings): string
    {
        $endpoint = defined('WAT_OPENAI_COMPATIBLE_API_BASE') ? (string) WAT_OPENAI_COMPATIBLE_API_BASE : Input::scalar_string($settings['ai_custom_endpoint'] ?? '');
        $endpoint = Settings::sanitize_ai_endpoint($endpoint);
        if ($endpoint === '') {
            return '';
        }
        if (preg_match('#/chat/completions$#', $endpoint)) {
            return $endpoint;
        }
        return rtrim($endpoint, '/') . '/chat/completions';
    }

    /**
     * Resolve provider API keys.
     *
     * Preferred sources are server constants and the `wat_ai_api_key` filter so deployments
     * can keep credentials in environment/server configuration. The admin UI can also store
     * a provider key in the WordPress database only when the site owner explicitly enables database credential storage.
     */
    private static function api_key(string $provider): string
    {
        return Settings::ai_api_key($provider);
    }
}
