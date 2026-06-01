<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation\Concerns;

use Webactueel\Translate\Support\Input;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

trait DeepLTranslationClient
{
    /** @return array<string, mixed>|WP_Error */
    private function translate_deepl(string $apiKey, string $text, string $sourceLanguage, string $targetLanguage, array $settings, array $context)
    {
        $endpoint = (bool) apply_filters('wat_deepl_free_api', true) ? 'https://api-free.deepl.com/v2/translate' : 'https://api.deepl.com/v2/translate';
        $deeplTargetLanguage = self::deepl_language_code($targetLanguage);
        $deeplSourceLanguage = self::deepl_language_code($sourceLanguage);
        $glossaryTerms = self::glossary_terms($text, $targetLanguage);
        $body = [
            'text' => self::enforce_glossary_terms($text, $glossaryTerms),
            'target_lang' => $deeplTargetLanguage,
            'preserve_formatting' => '1',
        ];
        if (self::contains_html_markup($text)) {
            $body['tag_handling'] = 'html';
        }
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
        $translation = self::enforce_glossary_terms($translation, $glossaryTerms);
        self::record_usage($context, 'deepl', 'deepl-api', $sourceLanguage, $targetLanguage, $text, $translation, false, count($glossaryTerms));
        return ['translated_text' => $translation, 'origin' => 'ai', 'provider' => 'deepl', 'model' => 'deepl-api', 'review_status' => 'needs_review', 'glossary_terms' => count($glossaryTerms)];
    }
}
