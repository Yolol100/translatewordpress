<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

final class AiTranslationService
{
    /** @return array<string, mixed> */
    public static function capabilities(): array
    {
        $settings = Settings::all();
        $provider = self::provider($settings);
        return [
            'enabled' => ! empty($settings['ai_enabled']),
            'provider' => $provider,
            'model' => self::model($settings, $provider),
            'tone' => Input::key($settings['ai_tone'] ?? 'professional'),
            'formality' => Input::key($settings['ai_formality'] ?? 'default'),
            'hasApiKey' => self::api_key($provider) !== '',
            'hasEndpoint' => $provider !== 'openai_compatible' || self::custom_endpoint($settings) !== '',
            'providers' => self::providers(),
            'supportsReviewWorkflow' => true,
            'storesApiKey' => Settings::ai_api_key($provider) !== '',
            'supportsServerConstants' => true,
            'note' => __('AI API-sleutels kunnen veilig via serverconstanten of de wat_ai_api_key filter worden geleverd. Als je een sleutel via de beheerinterface invoert, wordt die in de WordPress-database opgeslagen. Ingeschakelde AI-vertaling verstuurt de aangeboden tekst naar de gekozen externe provider; gebruik dit alleen voor content die extern verwerkt mag worden.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    /**
     * Translate one text through a configured AI provider.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function translate(string $text, string $sourceLanguage, string $targetLanguage, array $context = [])
    {
        $settings = Settings::all();
        if (empty($settings['ai_enabled'])) {
            return new WP_Error('wat_ai_disabled', __('AI-vertaling staat uit.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        $text = trim(wp_strip_all_tags($text));
        $sourceLanguage = sanitize_key($sourceLanguage);
        $targetLanguage = sanitize_key($targetLanguage);
        if ($text === '' || $targetLanguage === '') {
            return new WP_Error('wat_ai_invalid_request', __('Tekst en doeltaal zijn verplicht.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }
        if (function_exists('mb_strlen') ? mb_strlen($text) > 5000 : strlen($text) > 5000) {
            return new WP_Error('wat_ai_text_too_long', __('AI-vertaling is beperkt tot 5000 tekens per verzoek.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        $provider = self::provider($settings);
        $apiKey = self::api_key($provider);
        if ($apiKey === '') {
            return new WP_Error('wat_ai_missing_key', __('Geen AI API-sleutel gevonden. Vul je API-sleutel in bij AI-assistent of definieer een serverconstante.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        $rateLimit = self::check_rate_limit();
        if (is_wp_error($rateLimit)) {
            return $rateLimit;
        }

        if ($provider === 'deepl') {
            return $this->translate_deepl($apiKey, $text, $sourceLanguage, $targetLanguage, $settings);
        }

        if ($provider === 'openai_compatible') {
            return $this->translate_openai_compatible($apiKey, $text, $sourceLanguage, $targetLanguage, $settings, $context);
        }

        return $this->translate_openai($apiKey, $text, $sourceLanguage, $targetLanguage, $settings, $context);
    }

    private static function provider(array $settings): string
    {
        $provider = Input::key($settings['ai_provider'] ?? 'openai');
        return in_array($provider, ['openai', 'deepl', 'openai_compatible'], true) ? $provider : 'openai';
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
     * a provider key in the WordPress database when a site owner explicitly saves one there.
     */
    private static function api_key(string $provider): string
    {
        $constant = $provider === 'deepl' ? 'WAT_DEEPL_API_KEY' : ($provider === 'openai_compatible' ? 'WAT_OPENAI_COMPATIBLE_API_KEY' : 'WAT_OPENAI_API_KEY');
        $key = defined($constant) ? (string) constant($constant) : Settings::ai_api_key($provider);
        $filtered = apply_filters('wat_ai_api_key', $key, $provider);
        return is_scalar($filtered) ? trim((string) $filtered) : '';
    }

    /**
     * Apply a small per-user throttle before external AI calls are made.
     *
     * @return true|WP_Error
     */
    private static function check_rate_limit()
    {
        $limit = (int) apply_filters('wat_ai_rate_limit_per_minute', 20);
        if ($limit <= 0) {
            return true;
        }

        $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $bucket = $userId > 0 ? 'user_' . $userId : 'anonymous';
        $transientKey = 'wat_ai_rate_' . md5($bucket . '|' . (string) floor(time() / MINUTE_IN_SECONDS));
        $count = (int) get_transient($transientKey);
        if ($count >= $limit) {
            return new WP_Error(
                'wat_ai_rate_limited',
                __('AI-vertaling is tijdelijk beperkt. Probeer het over een minuut opnieuw.', 'webactueel-translate-language-dropdowns'),
                ['status' => 429]
            );
        }

        set_transient($transientKey, $count + 1, MINUTE_IN_SECONDS + 10);
        return true;
    }

    /** @return array<string, mixed>|WP_Error */
    private function translate_openai(string $apiKey, string $text, string $sourceLanguage, string $targetLanguage, array $settings, array $context)
    {
        return $this->translate_chat_completion('openai', 'https://api.openai.com/v1/chat/completions', $apiKey, $text, $sourceLanguage, $targetLanguage, $settings, $context);
    }

    /** @return array<string, mixed>|WP_Error */
    private function translate_openai_compatible(string $apiKey, string $text, string $sourceLanguage, string $targetLanguage, array $settings, array $context)
    {
        $endpoint = self::custom_endpoint($settings);
        if ($endpoint === '') {
            return new WP_Error('wat_ai_missing_endpoint', __('Geen OpenAI-compatible endpoint ingesteld. Vul een HTTPS API basis-URL in of definieer WAT_OPENAI_COMPATIBLE_API_BASE.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }
        return $this->translate_chat_completion('openai_compatible', $endpoint, $apiKey, $text, $sourceLanguage, $targetLanguage, $settings, $context);
    }

    /** @return array<string, mixed>|WP_Error */
    private function translate_chat_completion(string $provider, string $endpoint, string $apiKey, string $text, string $sourceLanguage, string $targetLanguage, array $settings, array $context)
    {
        $model = self::model($settings, $provider);
        $system = sprintf(
            /* translators: 1: source language code, 2: target language code. */
            __('You are a professional WordPress website translator. Translate from %1$s to %2$s. Preserve HTML entities, brand names, shortcodes, variables, placeholders and URLs. Return only the translation.', 'webactueel-translate-language-dropdowns'),
            $sourceLanguage ?: 'auto',
            $targetLanguage
        );
        $tone = sanitize_text_field(Input::scalar_string($settings['ai_tone'] ?? 'professional'));
        $formality = sanitize_text_field(Input::scalar_string($settings['ai_formality'] ?? 'default'));
        $prompt = trim($text . "\n\n" . 'Tone: ' . $tone . "\n" . 'Formality: ' . $formality);

        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'limit_response_size' => 1048576,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || ! is_array($body)) {
            return new WP_Error('wat_ai_provider_failed', __('AI-provider gaf geen geldige vertaling terug.', 'webactueel-translate-language-dropdowns'), ['status' => 502]);
        }
        $translation = self::sanitize_provider_translation(Input::scalar_string($body['choices'][0]['message']['content'] ?? ''));
        if ($translation === '') {
            return new WP_Error('wat_ai_empty_translation', __('AI-provider gaf een lege vertaling terug.', 'webactueel-translate-language-dropdowns'), ['status' => 502]);
        }
        return ['translated_text' => $translation, 'origin' => 'ai', 'provider' => $provider, 'model' => $model, 'review_status' => 'needs_review'];
    }

    private static function sanitize_provider_translation(string $translation): string
    {
        $translation = trim(wp_strip_all_tags($translation));
        if (function_exists('mb_substr')) {
            $translation = mb_substr($translation, 0, 6000);
        } else {
            $translation = substr($translation, 0, 6000);
        }
        return trim($translation);
    }

    private static function deepl_language_code(string $language): string
    {
        return strtoupper(str_replace('_', '-', sanitize_key($language)));
    }

    /** @return array<string, mixed>|WP_Error */
    private function translate_deepl(string $apiKey, string $text, string $sourceLanguage, string $targetLanguage, array $settings)
    {
        $endpoint = (bool) apply_filters('wat_deepl_free_api', true) ? 'https://api-free.deepl.com/v2/translate' : 'https://api.deepl.com/v2/translate';
        $deeplTargetLanguage = self::deepl_language_code($targetLanguage);
        $deeplSourceLanguage = self::deepl_language_code($sourceLanguage);
        $body = [
            'text' => $text,
            'target_lang' => $deeplTargetLanguage,
            'preserve_formatting' => '1',
        ];
        if ($deeplSourceLanguage !== '') {
            $body['source_lang'] = $deeplSourceLanguage;
        }
        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'limit_response_size' => 1048576,
            'headers' => ['Authorization' => 'DeepL-Auth-Key ' . $apiKey],
            'body' => $body,
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || ! is_array($decoded)) {
            return new WP_Error('wat_ai_provider_failed', __('DeepL gaf geen geldige vertaling terug.', 'webactueel-translate-language-dropdowns'), ['status' => 502]);
        }
        $translation = self::sanitize_provider_translation(Input::scalar_string($decoded['translations'][0]['text'] ?? ''));
        if ($translation === '') {
            return new WP_Error('wat_ai_empty_translation', __('DeepL gaf een lege vertaling terug.', 'webactueel-translate-language-dropdowns'), ['status' => 502]);
        }
        return ['translated_text' => $translation, 'origin' => 'ai', 'provider' => 'deepl', 'model' => 'deepl-api', 'review_status' => 'needs_review'];
    }
}
