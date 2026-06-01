<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation\Concerns;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

trait AiChatCompletionClient
{
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
        if (Settings::sanitize_ai_endpoint($endpoint) === '') {
            return new WP_Error('wat_ai_endpoint_blocked', __('Het OpenAI-compatible endpoint is niet toegestaan.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }
        return $this->translate_chat_completion('openai_compatible', $endpoint, $apiKey, $text, $sourceLanguage, $targetLanguage, $settings, $context);
    }

    /** @return array<string, mixed>|WP_Error */
    private function translate_chat_completion(string $provider, string $endpoint, string $apiKey, string $text, string $sourceLanguage, string $targetLanguage, array $settings, array $context)
    {
        $model = self::model($settings, $provider);
        $glossaryTerms = self::glossary_terms($text, $targetLanguage);
        $system = sprintf(
            /* translators: 1: source language code, 2: target language code. */
            __('You are a professional WordPress website translator. Translate from %1$s to %2$s. Preserve allowed HTML tags and attributes, HTML entities, brand names, shortcodes, variables, placeholders and URLs. Apply glossary terms exactly when supplied. Do not translate protected brand terms or product codes. Do not add markdown fences. Return only the translation.', 'webactueel-translate-language-dropdowns'),
            $sourceLanguage ?: 'auto',
            $targetLanguage
        );
        $tone = sanitize_text_field(Input::scalar_string($settings['ai_tone'] ?? 'professional'));
        $formality = sanitize_text_field(Input::scalar_string($settings['ai_formality'] ?? 'default'));
        $prompt = trim($text . "\n\n" . 'Tone: ' . $tone . "\n" . 'Formality: ' . $formality . self::glossary_prompt($glossaryTerms));

        $payload = wp_json_encode([
            'model' => $model,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);
        if (! is_string($payload)) {
            return new WP_Error('wat_ai_payload_encoding_failed', __('AI-verzoek voorbereiden mislukt.', 'webactueel-translate-language-dropdowns'), ['status' => 500]);
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'limit_response_size' => 1048576,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => $payload,
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
        $translation = self::enforce_glossary_terms($translation, $glossaryTerms);
        self::record_usage($context, $provider, $model, $sourceLanguage, $targetLanguage, $text, $translation, false, count($glossaryTerms));
        return ['translated_text' => $translation, 'origin' => 'ai', 'provider' => $provider, 'model' => $model, 'review_status' => 'needs_review', 'glossary_terms' => count($glossaryTerms)];
    }
}
