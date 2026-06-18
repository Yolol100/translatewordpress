<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation;

use Webactueel\Translate\Automation\Concerns\AiChatCompletionClient;
use Webactueel\Translate\Automation\Concerns\AiProviderConfiguration;
use Webactueel\Translate\Automation\Concerns\AiProviderTextHelpers;
use Webactueel\Translate\Automation\Concerns\AiRateLimiter;
use Webactueel\Translate\Automation\Concerns\DeepLTranslationClient;
use Webactueel\Translate\Automation\Concerns\GoogleTranslationClient;
use Webactueel\Translate\Support\Settings;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* filter names are backward-compatible plugin API.

final class AiTranslationService
{
    use AiProviderConfiguration;
    use AiRateLimiter;
    use AiChatCompletionClient;
    use AiProviderTextHelpers;
    use DeepLTranslationClient;
    use GoogleTranslationClient;

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

        $text = self::sanitize_translatable_markup($text);
        $sourceLanguage = sanitize_key($sourceLanguage);
        $targetLanguage = sanitize_key($targetLanguage);
        if ($text === '' || trim(wp_strip_all_tags($text)) === '' || $targetLanguage === '') {
            return new WP_Error('wat_ai_invalid_request', __('Tekst en doeltaal zijn verplicht.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }
        if (self::string_length($text) > 5000) {
            return new WP_Error('wat_ai_text_too_long', __('AI-vertaling is beperkt tot 5000 tekens per verzoek.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        $provider = self::provider($settings);
        $apiKey = self::api_key($provider);
        if ($apiKey === '') {
            return new WP_Error('wat_ai_missing_key', __('Geen AI API-sleutel gevonden. Configureer de sleutel via WAT_OPENAI_API_KEY, WAT_DEEPL_API_KEY, WAT_OPENAI_COMPATIBLE_API_KEY, WAT_GOOGLE_TRANSLATE_API_KEY of de wat_ai_api_key filter. Database-opslag via de beheerinterface is standaard uitgeschakeld en werkt alleen na expliciete opt-in.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        $rateLimit = self::check_rate_limit($context);
        if (is_wp_error($rateLimit)) {
            return $rateLimit;
        }

        if ($provider === 'deepl') {
            return $this->translate_deepl($apiKey, $text, $sourceLanguage, $targetLanguage, $settings, $context);
        }

        if ($provider === 'google_translate') {
            return $this->translate_google_translate($apiKey, $text, $sourceLanguage, $targetLanguage, $settings, $context);
        }

        if ($provider === 'openai_compatible') {
            return $this->translate_openai_compatible($apiKey, $text, $sourceLanguage, $targetLanguage, $settings, $context);
        }

        return $this->translate_openai($apiKey, $text, $sourceLanguage, $targetLanguage, $settings, $context);
    }
    private static function record_usage(array $context, string $provider, string $model, string $sourceLanguage, string $targetLanguage, string $text, string $translation, bool $memoryReused, int $glossaryTerms): void
    {
        AiUsageLedger::record([
            'job_id' => absint($context['job_id'] ?? 0),
            'string_id' => absint($context['string_id'] ?? 0),
            'provider' => $provider,
            'model' => $model,
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
            'source_text' => $text,
            'translated_text' => $translation,
            'memory_reused' => $memoryReused,
            'glossary_terms' => $glossaryTerms,
        ]);
    }

}
