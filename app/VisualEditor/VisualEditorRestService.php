<?php

declare(strict_types=1);

namespace Webactueel\Translate\VisualEditor;

use Webactueel\Translate\Automation\AiTranslationService;
use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\LanguageDomainMapper;
use Webactueel\Translate\Rest\Concerns\ChecksRestPermissions;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Translation\StringNormalizer;
use Webactueel\Translate\Translation\TranslationRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned custom tables; table identifiers are prepared with %i placeholders.

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
            'args' => $this->segment_preview_args(),
        ]);

        register_rest_route($this->namespace, '/visual-editor/segments', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'preview_segments'],
            'permission_callback' => [$this, 'can_save_segment'],
            'args' => [
                'segments' => ['type' => 'array', 'required' => true, 'validate_callback' => [self::class, 'validate_visual_editor_segments']],
                'language' => $this->language_arg(),
            ],
        ]);

        register_rest_route($this->namespace, '/visual-editor/segment', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'save_segment'],
            'permission_callback' => [$this, 'can_save_segment'],
            'args' => $this->segment_save_args(),
        ]);

        register_rest_route($this->namespace, '/visual-editor/suggestion', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'suggest_segment'],
            'permission_callback' => [$this, 'can_save_segment'],
            'args' => $this->segment_suggestion_args(),
        ]);
    }

    public function can_save_segment(): bool
    {
        return current_user_can('manage_options') || $this->can_translate();
    }

    /** @return array<string, array<string, mixed>> */
    private function segment_preview_args(): array
    {
        return [
            'original' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_visual_editor_text']],
            'language' => $this->language_arg(),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function segment_save_args(): array
    {
        return [
            'original' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_visual_editor_text']],
            'translation' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_textarea_field', 'validate_callback' => [self::class, 'validate_visual_editor_translation']],
            'language' => $this->language_arg(),
            'status' => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_key', 'validate_callback' => [self::class, 'validate_visual_editor_status']],
            'selector' => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_visual_editor_selector']],
            'url' => ['type' => 'string', 'format' => 'uri', 'required' => false, 'sanitize_callback' => [self::class, 'sanitize_visual_editor_url'], 'validate_callback' => [self::class, 'validate_visual_editor_url']],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function segment_suggestion_args(): array
    {
        return [
            'original' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_visual_editor_text']],
            'language' => $this->language_arg(),
            'selector' => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_visual_editor_selector']],
            'url' => ['type' => 'string', 'format' => 'uri', 'required' => false, 'sanitize_callback' => [self::class, 'sanitize_visual_editor_url'], 'validate_callback' => [self::class, 'validate_visual_editor_url']],
        ];
    }

    /** @return array<string, mixed> */
    private function language_arg(): array
    {
        return [
            'type' => 'string',
            'required' => true,
            'sanitize_callback' => 'sanitize_key',
            'validate_callback' => static fn($value): bool => is_scalar($value) && preg_match('/^[a-z]{2,3}(?:[-_][a-z0-9]{2,8})?$/i', (string) $value) === 1,
        ];
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

    public static function validate_visual_editor_status($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (! is_scalar($value)) {
            return false;
        }

        return in_array(sanitize_key((string) $value), ['draft', 'needs_review', 'reviewed', 'published'], true);
    }

    public static function validate_visual_editor_segments($value): bool
    {
        if (! is_array($value) || $value === [] || count($value) > 120) {
            return false;
        }

        foreach ($value as $segment) {
            if (! self::validate_visual_editor_text($segment)) {
                return false;
            }
        }

        return true;
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
        if (! is_scalar($value)) {
            return '';
        }

        $url = esc_url_raw(Input::scalar_string($value));
        if ($url === '') {
            return '';
        }

        $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        $urlHost = self::normalize_visual_editor_host((string) wp_parse_url($url, PHP_URL_HOST));
        if ($urlHost === '') {
            return '';
        }

        return in_array($urlHost, self::allowed_visual_editor_url_hosts(), true) ? $url : '';
    }

    /** @return list<string> */
    private static function allowed_visual_editor_url_hosts(): array
    {
        $hosts = [self::normalize_visual_editor_host((string) wp_parse_url(home_url(), PHP_URL_HOST))];
        foreach (LanguageDomainMapper::map() as $baseUrl) {
            $hosts[] = self::normalize_visual_editor_host((string) wp_parse_url((string) $baseUrl, PHP_URL_HOST));
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    private static function normalize_visual_editor_host(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?: $host;
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }

        return $host;
    }

    public static function validate_visual_editor_url($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return self::sanitize_visual_editor_url($value) !== '';
    }

    public function preview_segments(WP_REST_Request $request): WP_REST_Response
    {
        $segments = $request->get_param('segments');
        $result = (new VisualEditorSegmentWorkflow())->preview_segments(
            is_array($segments) ? $segments : [],
            Input::key($request->get_param('language'))
        );

        if (is_wp_error($result)) {
            return $this->error_response($result);
        }

        return new WP_REST_Response($result, 200);
    }

    public function preview_segment(WP_REST_Request $request): WP_REST_Response
    {
        $original = Input::text($request->get_param('original'));
        $language = Input::key($request->get_param('language'));
        $validation = $this->validate_segment_language($original, $language);
        if (is_wp_error($validation)) {
            return $this->error_response($validation);
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
            'can_publish' => current_user_can('manage_options'),
            'review_required' => ! empty(Settings::all()['translator_review_required']),
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
            'SELECT t.translated_text, t.status, t.origin FROM %i s INNER JOIN %i t ON t.string_id = s.id WHERE s.normalized_text = %s AND t.language_code = %s AND TRIM(t.translated_text) <> "" ORDER BY t.updated_at DESC, t.id DESC LIMIT 1',
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

    public function suggest_segment(WP_REST_Request $request): WP_REST_Response
    {
        $original = Input::text($request->get_param('original'));
        $language = Input::key($request->get_param('language'));
        $selector = Input::text($request->get_param('selector'));
        $url = self::sanitize_visual_editor_url($request->get_param('url'));
        $validation = $this->validate_segment_language($original, $language);
        if (is_wp_error($validation)) {
            return $this->error_response($validation);
        }

        $repository = new TranslationRepository();
        $memory = $repository->find_translation_memory_match($original, $language);
        if (! empty($memory['translated_text'])) {
            return new WP_REST_Response([
                'suggestion' => (string) $memory['translated_text'],
                'origin' => 'memory',
                'score' => absint($memory['score'] ?? 0),
                'message' => __('Translation Memory-voorstel gebruikt; er is geen AI-verzoek verstuurd.', 'webactueel-translate-language-dropdowns'),
            ], 200);
        }

        $stringId = $repository->upsert_string($original, 'visual_editor', 0, $url, $selector);
        if ($stringId <= 0) {
            return new WP_REST_Response([
                'message' => __('Deze tekst kan niet als AI-suggestie worden voorbereid.', 'webactueel-translate-language-dropdowns'),
            ], 400);
        }

        $result = (new AiTranslationService())->translate($original, LanguageDetector::default_language(), $language, [
            'string_id' => $stringId,
            'source' => 'visual_editor',
        ]);
        if (is_wp_error($result)) {
            return $this->error_response($result);
        }

        return new WP_REST_Response([
            'suggestion' => (string) ($result['translated_text'] ?? ''),
            'origin' => sanitize_key($result['origin'] ?? 'ai'),
            'provider' => sanitize_key($result['provider'] ?? ''),
            'model' => sanitize_text_field((string) ($result['model'] ?? '')),
            'review_status' => sanitize_key($result['review_status'] ?? 'needs_review'),
            'message' => __('AI-suggestie gemaakt. Controleer de tekst voordat je opslaat.', 'webactueel-translate-language-dropdowns'),
        ], 200);
    }

    public function save_segment(WP_REST_Request $request): WP_REST_Response
    {
        $original = Input::text($request->get_param('original'));
        $translation = trim(sanitize_textarea_field(Input::scalar_string($request->get_param('translation'))));
        $language = Input::key($request->get_param('language'));
        $selector = Input::text($request->get_param('selector'));
        $url = self::sanitize_visual_editor_url($request->get_param('url'));
        $validation = $this->validate_segment_language($original, $language, $translation);
        if (is_wp_error($validation)) {
            return $this->error_response($validation);
        }

        $repository = new TranslationRepository();
        $stringId = $repository->upsert_string($original, 'visual_editor', 0, $url, $selector);
        if ($stringId <= 0) {
            return new WP_REST_Response([
                'message' => __('Deze tekst kan niet worden opgeslagen.', 'webactueel-translate-language-dropdowns'),
            ], 400);
        }

        $requestedStatus = Input::key($request->get_param('status'));
        $status = $this->review_status_for_request($requestedStatus, $translation);
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
            'message' => $this->save_message($status),
        ], 200);
    }

    /** @return true|WP_Error */
    private function validate_segment_language(string $original, string $language, string $translation = 'x')
    {
        if ($original === '' || $language === '' || $translation === '') {
            return new WP_Error('wat_visual_editor_missing_fields', __('Originele tekst, vertaling en taal zijn verplicht.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        if (! $this->is_translatable_language($language)) {
            return new WP_Error('wat_visual_editor_invalid_language', __('Kies een actieve niet-standaardtaal om te vertalen.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        return true;
    }

    private function review_status_for_request(string $requestedStatus, string $translation): string
    {
        $status = $requestedStatus !== '' ? $requestedStatus : 'published';
        if ($translation === '' && in_array($status, ['published', 'reviewed'], true)) {
            $status = 'draft';
        }
        if (! in_array($status, ['draft', 'needs_review', 'reviewed', 'published'], true)) {
            $status = 'published';
        }
        if (! current_user_can('manage_options') && ! empty(Settings::all()['translator_review_required']) && in_array($status, ['published', 'reviewed'], true)) {
            return 'needs_review';
        }

        return $status;
    }

    private function save_message(string $status): string
    {
        if ($status === 'published') {
            return __('Vertaling gepubliceerd.', 'webactueel-translate-language-dropdowns');
        }
        if ($status === 'reviewed') {
            return __('Vertaling gemarkeerd als reviewed.', 'webactueel-translate-language-dropdowns');
        }
        if ($status === 'needs_review') {
            return __('Vertaling opgeslagen ter review.', 'webactueel-translate-language-dropdowns');
        }

        return __('Vertaling opgeslagen als concept.', 'webactueel-translate-language-dropdowns');
    }

    private function error_response(WP_Error $error): WP_REST_Response
    {
        $data = $error->get_error_data();
        $status = is_array($data) && isset($data['status']) ? absint($data['status']) : 400;

        return new WP_REST_Response([
            'code' => $error->get_error_code(),
            'message' => $error->get_error_message(),
        ], $status > 0 ? $status : 400);
    }
}
