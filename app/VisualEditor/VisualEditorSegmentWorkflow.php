<?php

declare(strict_types=1);

namespace Webactueel\Translate\VisualEditor;

use Webactueel\Translate\Automation\AiTranslationService;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Translation\StringNormalizer;
use Webactueel\Translate\Translation\TranslationRepository;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Visual editor reads plugin-owned custom tables; table identifiers use %i placeholders.

final class VisualEditorSegmentWorkflow
{
    use ValidatesLanguages;

    private const MAX_BULK_SEGMENTS = 120;

    /** @return array<string, mixed>|WP_Error */
    public function preview_segment(string $original, string $language)
    {
        $original = $this->clean_segment_text($original);
        $language = sanitize_key($language);

        if ($original === '' || $language === '') {
            return new WP_Error('wat_visual_editor_invalid_segment', __('Originele tekst en taal zijn verplicht.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        if (! $this->is_translatable_language($language)) {
            return new WP_Error('wat_visual_editor_invalid_language', __('Kies een actieve niet-standaardtaal om te vertalen.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        return $this->segment_status($original, $language);
    }

    /**
     * @param array<int, mixed> $segments
     * @return array<string, mixed>|WP_Error
     */
    public function preview_segments(array $segments, string $language)
    {
        $language = sanitize_key($language);
        if (! $this->is_translatable_language($language)) {
            return new WP_Error('wat_visual_editor_invalid_language', __('Kies een actieve niet-standaardtaal om te vertalen.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        $clean = $this->clean_segment_list($segments);
        if ($clean === []) {
            return new WP_Error('wat_visual_editor_empty_segments', __('Geen geldige tekstsegmenten gevonden.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        $items = [];
        $summary = [
            'checked' => 0,
            'translated' => 0,
            'review' => 0,
            'memory' => 0,
            'missing' => 0,
        ];

        foreach ($clean as $original) {
            $item = $this->segment_status($original, $language);
            $items[] = $item;
            ++$summary['checked'];
            $bucket = $this->status_bucket($item);
            if (isset($summary[$bucket])) {
                ++$summary[$bucket];
            }
        }

        return [
            'language' => $language,
            'items' => $items,
            'summary' => $summary,
            'max_segments' => self::MAX_BULK_SEGMENTS,
        ];
    }

    /** @return array<string, mixed>|WP_Error */
    public function ai_suggestion(string $original, string $language, string $selector = '', string $url = '')
    {
        $original = $this->clean_segment_text($original);
        $language = sanitize_key($language);

        if ($original === '' || $language === '') {
            return new WP_Error('wat_visual_editor_ai_invalid_segment', __('Originele tekst en taal zijn verplicht voor een AI-voorstel.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        if (! $this->is_translatable_language($language)) {
            return new WP_Error('wat_visual_editor_invalid_language', __('Kies een actieve niet-standaardtaal om te vertalen.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        $result = (new AiTranslationService())->translate($original, LanguageDetector::default_language(), $language, [
            'source' => 'visual_editor',
            'selector' => sanitize_text_field($selector),
            'url' => esc_url_raw($url),
            'user_id' => get_current_user_id(),
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'original' => $original,
            'language' => $language,
            'translation' => wp_kses_post(Input::scalar_string($result['translated_text'] ?? '')),
            'origin' => 'ai',
            'provider' => sanitize_key($result['provider'] ?? ''),
            'model' => sanitize_text_field(Input::scalar_string($result['model'] ?? '')),
            'status' => sanitize_key($result['review_status'] ?? 'needs_review') ?: 'needs_review',
            'glossary_terms' => absint($result['glossary_terms'] ?? 0),
            'message' => __('AI-voorstel opgehaald. Controleer en sla handmatig op.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    /** @return array<string, mixed> */
    private function segment_status(string $original, string $language): array
    {
        $repository = new TranslationRepository();
        $normalized = StringNormalizer::normalize($original);
        $existing = $this->find_existing_translation($normalized, $language);
        $memory = $repository->find_translation_memory_match($original, $language);

        $translation = Input::scalar_string($existing['translated_text'] ?? '');
        $status = sanitize_key($existing['status'] ?? '');
        $origin = sanitize_key($existing['origin'] ?? '');
        $memoryPayload = ! empty($memory) ? [
            'translation' => Input::scalar_string($memory['translated_text'] ?? ''),
            'score' => absint($memory['score'] ?? 0),
            'source_string_id' => absint($memory['source_string_id'] ?? 0),
        ] : null;

        return [
            'original' => $original,
            'language' => $language,
            'string_id' => absint($existing['string_id'] ?? 0),
            'translation' => $translation !== '' ? $translation : ($memoryPayload['translation'] ?? ''),
            'status' => $status,
            'origin' => $origin !== '' ? $origin : ($memoryPayload ? 'memory' : ''),
            'bucket' => $this->status_bucket([
                'translation' => $translation,
                'status' => $status,
                'memory' => $memoryPayload,
            ]),
            'memory' => $memoryPayload,
        ];
    }

    /** @return array<string, mixed> */
    private function find_existing_translation(string $normalized, string $language): array
    {
        global $wpdb;

        if ($normalized === '' || $language === '') {
            return [];
        }

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT s.id AS string_id, t.translated_text, t.status, t.origin FROM %i s INNER JOIN %i t ON t.string_id = s.id WHERE s.normalized_text = %s AND t.language_code = %s AND TRIM(t.translated_text) <> "" ORDER BY t.updated_at DESC, t.id DESC LIMIT 1',
            Tables::strings(),
            Tables::translations(),
            $normalized,
            $language
        ), ARRAY_A);

        return is_array($row) ? [
            'string_id' => absint($row['string_id'] ?? 0),
            'translated_text' => Input::scalar_string($row['translated_text'] ?? ''),
            'status' => sanitize_key($row['status'] ?? ''),
            'origin' => sanitize_key($row['origin'] ?? ''),
        ] : [];
    }

    /** @param array<string, mixed> $item */
    private function status_bucket(array $item): string
    {
        $translation = trim(Input::scalar_string($item['translation'] ?? ''));
        $status = sanitize_key($item['status'] ?? '');
        if ($translation !== '' && in_array($status, ['published', 'reviewed'], true)) {
            return 'translated';
        }
        if ($translation !== '' && in_array($status, ['draft', 'needs_review', 'outdated'], true)) {
            return 'review';
        }
        if (! empty($item['memory'])) {
            return 'memory';
        }
        return 'missing';
    }

    /** @param array<int, mixed> $segments @return list<string> */
    private function clean_segment_list(array $segments): array
    {
        $clean = [];
        foreach ($segments as $segment) {
            if (! is_scalar($segment)) {
                continue;
            }
            $text = $this->clean_segment_text((string) $segment);
            if ($text !== '') {
                $clean[] = $text;
            }
            if (count($clean) >= self::MAX_BULK_SEGMENTS) {
                break;
            }
        }

        return array_values(array_unique($clean));
    }

    private function clean_segment_text(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($text)) ?: '');
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($length < 2 || $length > 300) {
            return '';
        }
        if (preg_match('/^[\d\s.,:;!?\'"()\\-\x{2013}\x{2014}\x{20AC}$%]+$/u', $text)) {
            return '';
        }

        return sanitize_text_field($text);
    }
}
