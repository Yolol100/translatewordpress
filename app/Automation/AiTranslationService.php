<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Translation\GlossaryRepository;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* filter names are backward-compatible plugin API.

final class AiTranslationService
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
     * a provider key in the WordPress database only when the site owner explicitly enables database credential storage.
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

        global $wpdb;
        $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $bucket = $userId > 0 ? 'user_' . $userId : 'anonymous';
        $window = (string) floor(time() / MINUTE_IN_SECONDS);
        $optionName = 'wat_ai_rate_' . md5($bucket . '|' . $window);

        // Shared-hosting-safe atomic counter. A single InnoDB UPSERT on the options row
        // is atomic, so concurrent translate/batch calls cannot all read the same
        // pre-increment value and blow past the per-minute provider cap.
        //
        // ON DUPLICATE KEY UPDATE affected-rows convention: 1 = fresh INSERT (first hit
        // this window, count = 1); 2 = existing row updated, where LAST_INSERT_ID() was
        // set to the incremented count and is exposed via $wpdb->insert_id.
        $affected = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE option_value = LAST_INSERT_ID(CAST(option_value AS UNSIGNED) + 1)",
            $optionName,
            '1',
            'no'
        )); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ($affected === false) {
            // Counter write failed; fail open so a transient DB hiccup cannot block translation.
            return true;
        }

        if ((int) $affected === 1) {
            $count = 1;
        } else {
            $count = (int) $wpdb->insert_id;
            if ($count < 1) {
                // Fallback: re-read the stored value if LAST_INSERT_ID was unavailable.
                $count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT CAST(option_value AS UNSIGNED) FROM {$wpdb->options} WHERE option_name = %s",
                    $optionName
                ));
                $count = max(1, $count);
            }
        }
        wp_cache_delete($optionName, 'options');

        if ($count > $limit) {
            return new WP_Error(
                'wat_ai_rate_limited',
                __('AI-vertaling is tijdelijk beperkt. Probeer het over een minuut opnieuw.', 'webactueel-translate-language-dropdowns'),
                ['status' => 429]
            );
        }

        // Best-effort cleanup of expired windows so the options table does not grow unbounded.
        if ($count === 1) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name <> %s",
                $wpdb->esc_like('wat_ai_rate_') . '%',
                $optionName
            )); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        }

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

    /**
     * Preserve safe translation markup while removing unsafe provider output.
     *
     * AI providers may return valid translated snippets containing links, emphasis or
     * short inline markup. Stripping all tags here broke the documented "preserve
     * HTML" behavior; `wp_kses_post()` keeps the WordPress-safe subset and removes
     * unsafe tags/attributes before anything is stored or returned to the admin UI.
     */
    private static function sanitize_provider_translation(string $translation): string
    {
        $translation = self::strip_markdown_fence($translation);
        $translation = wp_kses_post(str_replace("\0", '', $translation));
        return trim(self::limit_string($translation, 6000));
    }

    private static function sanitize_translatable_markup(string $text): string
    {
        return trim(wp_kses_post(str_replace("\0", '', $text)));
    }

    private static function strip_markdown_fence(string $text): string
    {
        $text = trim($text);
        if (preg_match('/^```(?:html|xml|text)?\s*(.*?)\s*```$/is', $text, $matches)) {
            return trim((string) $matches[1]);
        }
        return $text;
    }

    private static function string_length(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    private static function limit_string(string $text, int $limit): string
    {
        if (self::string_length($text) <= $limit) {
            return $text;
        }
        return function_exists('mb_substr') ? mb_substr($text, 0, $limit) : substr($text, 0, $limit);
    }

    private static function contains_html_markup(string $text): bool
    {
        return $text !== wp_strip_all_tags($text);
    }

    private static function deepl_language_code(string $language): string
    {
        return strtoupper(str_replace('_', '-', sanitize_key($language)));
    }

    /** @param array<int, array<string, mixed>> $terms */
    private static function glossary_prompt(array $terms): string
    {
        if ($terms === []) {
            return '';
        }
        $lines = ["", "Glossary rules, apply exactly:"];
        foreach ($terms as $term) {
            $source = sanitize_text_field(Input::scalar_string($term['source_term'] ?? ''));
            $target = sanitize_text_field(Input::scalar_string($term['target_term'] ?? ''));
            if ($source !== '' && $target !== '') {
                $lines[] = '- ' . $source . ' => ' . $target;
            }
        }
        return "\n" . implode("\n", $lines);
    }

    /** @return array<int, array<string, mixed>> */
    private static function glossary_terms(string $text, string $targetLanguage): array
    {
        $terms = (new GlossaryRepository())->matches($text, $targetLanguage);
        return array_slice($terms, 0, 25);
    }

    /** @param array<int, array<string, mixed>> $terms */
    private static function enforce_glossary_terms(string $translation, array $terms): string
    {
        foreach ($terms as $term) {
            $source = Input::scalar_string($term['source_term'] ?? '');
            $target = Input::scalar_string($term['target_term'] ?? '');
            if ($source === '' || $target === '') {
                continue;
            }
            $containsSource = ! empty($term['case_sensitive']) ? strpos($translation, $source) !== false : stripos($translation, $source) !== false;
            $containsTarget = ! empty($term['case_sensitive']) ? strpos($translation, $target) !== false : stripos($translation, $target) !== false;
            if ($containsSource && ! $containsTarget) {
                $translation = ! empty($term['case_sensitive']) ? str_replace($source, $target, $translation) : str_ireplace($source, $target, $translation);
            }
        }
        return trim($translation);
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

    /** @return array<string, mixed>|WP_Error */
    private function translate_deepl(string $apiKey, string $text, string $sourceLanguage, string $targetLanguage, array $settings)
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
        self::record_usage([], 'deepl', 'deepl-api', $sourceLanguage, $targetLanguage, $text, $translation, false, count($glossaryTerms));
        return ['translated_text' => $translation, 'origin' => 'ai', 'provider' => 'deepl', 'model' => 'deepl-api', 'review_status' => 'needs_review', 'glossary_terms' => count($glossaryTerms)];
    }
}
