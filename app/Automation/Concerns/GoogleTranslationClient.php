<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation\Concerns;

use Webactueel\Translate\Support\Input;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

trait GoogleTranslationClient
{
    /** @return array<string, mixed>|WP_Error */
    private function translate_google_translate(string $apiKey, string $text, string $sourceLanguage, string $targetLanguage, array $settings, array $context)
    {
        $glossaryTerms = self::glossary_terms($text, $targetLanguage);
        $body = [
            'q' => self::enforce_glossary_terms($text, $glossaryTerms),
            'target' => self::google_language_code($targetLanguage),
            'format' => self::contains_html_markup($text) ? 'html' : 'text',
        ];
        $source = self::google_language_code($sourceLanguage);
        if ($source !== '') {
            $body['source'] = $source;
        }

        $endpoint = add_query_arg('key', $apiKey, 'https://translation.googleapis.com/language/translate/v2');
        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'limit_response_size' => 1048576,
            'headers' => ['Accept' => 'application/json'],
            'body' => $body,
        ]);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || ! is_array($decoded)) {
            return new WP_Error('wat_ai_provider_failed', __('Google Translate gaf geen geldige vertaling terug.', 'webactueel-translate-language-dropdowns'), ['status' => 502]);
        }

        $translation = self::sanitize_provider_translation(html_entity_decode(Input::scalar_string($decoded['data']['translations'][0]['translatedText'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($translation === '') {
            return new WP_Error('wat_ai_empty_translation', __('Google Translate gaf een lege vertaling terug.', 'webactueel-translate-language-dropdowns'), ['status' => 502]);
        }

        $translation = self::enforce_glossary_terms($translation, $glossaryTerms);
        self::record_usage($context, 'google_translate', 'google-translate-v2', $sourceLanguage, $targetLanguage, $text, $translation, false, count($glossaryTerms));
        return ['translated_text' => $translation, 'origin' => 'ai', 'provider' => 'google_translate', 'model' => 'google-translate-v2', 'review_status' => 'needs_review', 'glossary_terms' => count($glossaryTerms)];
    }

    private static function google_language_code(string $language): string
    {
        $language = strtolower(str_replace('_', '-', sanitize_key($language)));
        return preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/', $language) === 1 ? $language : '';
    }
}
