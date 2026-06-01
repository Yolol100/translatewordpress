<?php

declare(strict_types=1);

namespace Webactueel\Translate\VisualEditor;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Translation\TranslationRepository;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use WP_REST_Request;
use Webactueel\Translate\Rest\Concerns\ChecksRestPermissions;
use WP_REST_Response;
use WP_REST_Server;
use Webactueel\Translate\Translation\StringNormalizer;

if (! defined('ABSPATH')) {
    exit;
}

final class VisualEditorRestService
{
    use ChecksRestPermissions;
    use ValidatesLanguages;

    private string $namespace = 'webactueel-translate-language-dropdowns/v1';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void
    {
        register_rest_route($this->namespace, '/visual-editor/segment', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'preview_segment'],
            'permission_callback' => [$this, 'can_save_segment'],
            'args' => [
                'original' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_visual_editor_text']],
                'language' => [
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => static fn($value): bool => is_scalar($value) && preg_match('/^[a-z]{2,3}(?:[-_][a-z0-9]{2,8})?$/i', (string) $value) === 1,
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/visual-editor/segment', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'save_segment'],
            'permission_callback' => [$this, 'can_save_segment'],
            'args' => [
                'original' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_visual_editor_text']],
                'translation' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_textarea_field', 'validate_callback' => [self::class, 'validate_visual_editor_translation']],
                'language' => [
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => static fn($value): bool => is_scalar($value) && preg_match('/^[a-z]{2,3}(?:[-_][a-z0-9]{2,8})?$/i', (string) $value) === 1,
                ],
                'selector' => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_visual_editor_selector']],
                'url' => ['type' => 'string', 'format' => 'uri', 'required' => false, 'sanitize_callback' => [self::class, 'sanitize_visual_editor_url'], 'validate_callback' => [self::class, 'validate_visual_editor_url']],
            ],
        ]);
    }

    public function can_save_segment(): bool
    {
        return current_user_can('manage_options') || $this->can_translate();
    }

    public static function validate_visual_editor_text($value): bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        $text = trim(sanitize_text_field((string) $value));
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);

        return $length >= 2 && $length <= 300;
    }

    public static function validate_visual_editor_translation($value): bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        $text = trim(sanitize_textarea_field((string) $value));
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);

        return $length >= 1 && $length <= 1000;
    }

    public static function validate_visual_editor_selector($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (! is_scalar($value)) {
            return false;
        }

        $selector = sanitize_text_field((string) $value);
        $length = function_exists('mb_strlen') ? mb_strlen($selector) : strlen($selector);

        return $length <= 300 && strpos($selector, "\0") === false;
    }

    public static function sanitize_visual_editor_url($value): string
    {
        $url = esc_url_raw((string) $value);
        if ($url === '') {
            return '';
        }

        $homeHost = wp_parse_url(home_url(), PHP_URL_HOST);
        $urlHost = wp_parse_url($url, PHP_URL_HOST);

        return is_string($homeHost) && is_string($urlHost) && strcasecmp($homeHost, $urlHost) === 0 ? $url : '';
    }

    public static function validate_visual_editor_url($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return self::sanitize_visual_editor_url($value) !== '';
    }


    public function preview_segment(WP_REST_Request $request): WP_REST_Response
    {
        $original = Input::text($request->get_param('original'));
        $language = Input::key($request->get_param('language'));

        if ($original === '' || $language === '') {
            return new WP_REST_Response([
                'message' => __('Originele tekst en taal zijn verplicht.', 'webactueel-translate-language-dropdowns'),
            ], 400);
        }

        if (! $this->is_translatable_language($language)) {
            return new WP_REST_Response([
                'message' => __('Kies een actieve niet-standaardtaal om te vertalen.', 'webactueel-translate-language-dropdowns'),
            ], 400);
        }

        $repository = new TranslationRepository();
        $memory = $repository->find_translation_memory_match($original, $language);
        $normalized = StringNormalizer::normalize($original);
        $existing = $this->find_existing_translation($normalized, $language);

        return new WP_REST_Response([
            'original' => $original,
            'language' => $language,
            'translation' => $existing['translated_text'] ?? ($memory['translated_text'] ?? ''),
            'status' => $existing['status'] ?? '',
            'origin' => $existing['origin'] ?? (! empty($memory) ? 'memory' : ''),
            'memory' => ! empty($memory) ? [
                'translation' => $memory['translated_text'] ?? '',
                'score' => $memory['score'] ?? 0,
                'source_string_id' => $memory['source_string_id'] ?? 0,
            ] : null,
        ], 200);
    }

    /** @return array<string, string> */
    private function find_existing_translation(string $normalized, string $language): array
    {
        global $wpdb;

        if ($normalized === '' || $language === '') {
            return [];
        }

        $stringsTable = \Webactueel\Translate\Database\Tables::strings();
        $translationsTable = \Webactueel\Translate\Database\Tables::translations();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT t.translated_text, t.status, t.origin FROM %i s INNER JOIN %i t ON t.string_id = s.id WHERE s.normalized_text = %s AND t.language_code = %s AND TRIM(t.translated_text) <> '' ORDER BY t.updated_at DESC, t.id DESC LIMIT 1",
            $stringsTable,
            $translationsTable,
            $normalized,
            $language
        ), ARRAY_A);

        return is_array($row) ? [
            'translated_text' => (string) ($row['translated_text'] ?? ''),
            'status' => sanitize_key($row['status'] ?? ''),
            'origin' => sanitize_key($row['origin'] ?? ''),
        ] : [];
    }

    public function save_segment(WP_REST_Request $request): WP_REST_Response
    {
        $original = Input::text($request->get_param('original'));
        $translation = trim(sanitize_textarea_field((string) $request->get_param('translation')));
        $language = Input::key($request->get_param('language'));
        $selector = Input::text($request->get_param('selector'));
        $url = self::sanitize_visual_editor_url($request->get_param('url'));

        if ($original === '' || $translation === '' || $language === '') {
            return new WP_REST_Response([
                'message' => __('Originele tekst, vertaling en taal zijn verplicht.', 'webactueel-translate-language-dropdowns'),
            ], 400);
        }

        if (! $this->is_translatable_language($language)) {
            return new WP_REST_Response([
                'message' => __('Kies een actieve niet-standaardtaal om te vertalen.', 'webactueel-translate-language-dropdowns'),
            ], 400);
        }

        $repository = new TranslationRepository();
        $stringId = $repository->upsert_string($original, 'visual_editor', 0, $url, $selector);
        if ($stringId <= 0) {
            return new WP_REST_Response([
                'message' => __('Deze tekst kan niet worden opgeslagen.', 'webactueel-translate-language-dropdowns'),
            ], 400);
        }

        $settings = Settings::all();
        $requiresReview = ! empty($settings['translator_review_required']);
        $status = (! current_user_can('manage_options') && $requiresReview) ? 'needs_review' : 'published';
        $saved = $repository->save_translation($stringId, $language, $translation, $status, 'manual');
        if (! $saved) {
            return new WP_REST_Response([
                'message' => __('Vertaling opslaan is mislukt.', 'webactueel-translate-language-dropdowns'),
            ], 400);
        }

        return new WP_REST_Response([
            'id' => $stringId,
            'original' => $original,
            'translation' => $translation,
            'language' => $language,
            'status' => $status,
            'message' => $status === 'published' ? __('Vertaling opgeslagen.', 'webactueel-translate-language-dropdowns') : __('Vertaling opgeslagen ter review.', 'webactueel-translate-language-dropdowns'),
        ], 200);
    }
}
