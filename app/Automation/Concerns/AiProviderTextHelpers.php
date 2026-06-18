<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation\Concerns;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Translation\GlossaryRepository;

if (! defined('ABSPATH')) {
    exit;
}

trait AiProviderTextHelpers
{
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


    /** @param array<string, mixed> $settings */
    private static function ai_context_prompt(array $settings): string
    {
        if (empty($settings['ai_context_enabled'])) {
            return '';
        }

        $lines = [];
        $siteContext = trim(sanitize_textarea_field(Input::scalar_string($settings['ai_site_context'] ?? '')));
        $audience = trim(sanitize_textarea_field(Input::scalar_string($settings['ai_target_audience'] ?? '')));
        $brandTerms = self::prompt_lines_from_textarea(Input::scalar_string($settings['ai_brand_terms'] ?? ''), 30);
        $doNotTranslate = self::prompt_lines_from_textarea(Input::scalar_string($settings['ai_do_not_translate'] ?? ''), 30);

        if ($siteContext !== '') {
            $lines[] = 'Website context: ' . $siteContext;
        }
        if ($audience !== '') {
            $lines[] = 'Target audience: ' . $audience;
        }
        if ($brandTerms !== []) {
            $lines[] = 'Brand/terminology to keep consistent: ' . implode(', ', $brandTerms);
        }
        if ($doNotTranslate !== []) {
            $lines[] = 'Do not translate these terms; copy them exactly: ' . implode(', ', $doNotTranslate);
        }

        return $lines === [] ? '' : "\n\nTranslation context profile:\n" . implode("\n", $lines);
    }

    /** @return list<string> */
    private static function prompt_lines_from_textarea(string $value, int $limit): array
    {
        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', $value) ?: [] as $line) {
            $line = trim(sanitize_text_field((string) $line));
            if ($line !== '') {
                $items[] = $line;
            }
            if (count($items) >= $limit) {
                break;
            }
        }

        return array_values(array_unique($items));
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
}
