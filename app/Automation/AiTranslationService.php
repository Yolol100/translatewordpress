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
            'supportsReviewWorkflow' => true,
            'storesApiKey' => false,
            'note' => __('API-sleutels worden niet in de database opgeslagen. Ingeschakelde AI-vertaling verstuurt de aangeboden tekst naar de gekozen externe provider; gebruik dit alleen voor content die extern verwerkt mag worden.', 'webactueel-translate-language-dropdowns'),
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
            return new WP_Error('wat_ai_missing_key', __('Geen AI API-sleutel gevonden. Definieer WAT_OPENAI_API_KEY, WAT_DEEPL_API_KEY of gebruik het wat_ai_api_key filter.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        if ($provider === 'deepl') {
            return $this->translate_deepl($apiKey, $text, $sourceLanguage, $targetLanguage, $settings);
        }

        return $this->translate_openai($apiKey, $text, $sourceLanguage, $targetLanguage, $settings, $context);
    }

    private static function provider(array $settings): string
    {
        $provider = Input::key($settings['ai_provider'] ?? 'openai');
        return in_array($provider, ['openai', 'deepl'], true) ? $provider : 'openai';
    }

    private static function model(array $settings, string $provider): string
    {
        $model = sanitize_text_field(Input::scalar_string($settings['ai_model'] ?? ''));
        if ($model !== '') {
            return $model;
        }
        return $provider === 'deepl' ? 'deepl-api' : 'gpt-4o-mini';
    }

    private static function api_key(string $provider): string
    {
        $constant = $provider === 'deepl' ? 'WAT_DEEPL_API_KEY' : 'WAT_OPENAI_API_KEY';
        $key = defined($constant) ? (string) constant($constant) : '';
        $filtered = apply_filters('wat_ai_api_key', $key, $provider);
        return is_scalar($filtered) ? trim((string) $filtered) : '';
    }

    /** @return array<string, mixed>|WP_Error */
    private function translate_openai(string $apiKey, string $text, string $sourceLanguage, string $targetLanguage, array $settings, array $context)
    {
        $model = self::model($settings, 'openai');
        $system = sprintf(
            /* translators: 1: source language code, 2: target language code. */
            __('You are a professional WordPress website translator. Translate from %1$s to %2$s. Preserve HTML entities, brand names, shortcodes, variables, placeholders and URLs. Return only the translation.', 'webactueel-translate-language-dropdowns'),
            $sourceLanguage ?: 'auto',
            $targetLanguage
        );
        $tone = sanitize_text_field(Input::scalar_string($settings['ai_tone'] ?? 'professional'));
        $formality = sanitize_text_field(Input::scalar_string($settings['ai_formality'] ?? 'default'));
        $prompt = trim($text . "\n\n" . 'Tone: ' . $tone . "\n" . 'Formality: ' . $formality);

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
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
        $translation = Input::scalar_string($body['choices'][0]['message']['content'] ?? '');
        if ($translation === '') {
            return new WP_Error('wat_ai_empty_translation', __('AI-provider gaf een lege vertaling terug.', 'webactueel-translate-language-dropdowns'), ['status' => 502]);
        }
        return ['translated_text' => trim($translation), 'origin' => 'ai', 'provider' => 'openai', 'model' => $model, 'review_status' => 'needs_review'];
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
        $translation = Input::scalar_string($decoded['translations'][0]['text'] ?? '');
        if ($translation === '') {
            return new WP_Error('wat_ai_empty_translation', __('DeepL gaf een lege vertaling terug.', 'webactueel-translate-language-dropdowns'), ['status' => 502]);
        }
        return ['translated_text' => trim($translation), 'origin' => 'ai', 'provider' => 'deepl', 'model' => 'deepl-api', 'review_status' => 'needs_review'];
    }
}
