<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Translation\TranslationRepository;
use WP_Error;
use WP_REST_Request;

if (! defined('ABSPATH')) {
    exit;
}


trait TranslationEndpoints
{
    use ValidatesLanguages;
    public function strings(WP_REST_Request $request): array
    {
        return (new TranslationRepository())->get_strings($request->get_params());
    }

    public function string_translations(WP_REST_Request $request): array
    {
        return (new TranslationRepository())->get_translations_for_string(absint($request['id']));
    }

    public function update_string(WP_REST_Request $request)
    {
        $params = $request->get_params();
        $payload = $this->prepare_translation_update_payload(absint($request['id']), $params);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $repo = new TranslationRepository();
        $saved = $repo->save_translation($payload['id'], $payload['language'], $payload['translated'], $payload['status'], 'manual');
        if (! $saved) {
            return new WP_Error('wat_translation_save_failed', __('Vertaling opslaan mislukt.', 'webactueel-translate-language-dropdowns'), ['status' => 500]);
        }

        return [
            'saved' => true,
            'memory_applied' => $this->apply_translation_memory_if_requested($repo, $payload, $params),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{id: int, language: string, translated: string, status: string}|WP_Error
     */
    private function prepare_translation_update_payload(int $id, array $params)
    {
        $language = Input::key($params['language_code'] ?? '');
        $translated = trim(wp_kses_post(Input::scalar_string($params['translated_text'] ?? '')));
        if (! $id || $language === '') {
            return new WP_Error('wat_invalid_translation', __('String ID en taal zijn verplicht.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }
        if (! $this->is_translatable_language($language)) {
            return new WP_Error('wat_invalid_translation_language', __('Kies een actieve niet-standaardtaal om te vertalen.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        $status = $this->normalise_translation_status(Input::key($params['status'] ?? 'published'), $translated);
        if (is_wp_error($status)) {
            return $status;
        }

        return [
            'id' => $id,
            'language' => $language,
            'translated' => $translated,
            'status' => $this->apply_translation_review_policy($status, $translated),
        ];
    }

    /**
     * @return string|WP_Error
     */
    private function normalise_translation_status(string $status, string $translated)
    {
        if ($status !== '' && ! in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review', 'outdated'], true)) {
            return new WP_Error('wat_invalid_translation_status', __('Ongeldige vertaalstatus.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }
        if ($status === '') {
            return $translated !== '' ? 'published' : 'draft';
        }
        if ($translated === '' && in_array($status, ['published', 'reviewed'], true)) {
            return 'draft';
        }

        return $status;
    }

    private function apply_translation_review_policy(string $status, string $translated): string
    {
        $settings = Settings::all();
        if (! current_user_can('manage_options') && ! empty($settings['translator_review_required']) && $translated !== '' && in_array($status, ['published', 'reviewed'], true)) {
            return 'needs_review';
        }

        return $status;
    }

    /**
     * @param array{id: int, language: string, translated: string, status: string} $payload
     * @param array<string, mixed> $params
     */
    private function apply_translation_memory_if_requested(TranslationRepository $repo, array $payload, array $params): int
    {
        if (empty($params['apply_memory']) || $payload['translated'] === '') {
            return 0;
        }

        return $repo->apply_translation_memory($payload['id'], $payload['language'], $payload['translated'], $payload['status']);
    }
}
