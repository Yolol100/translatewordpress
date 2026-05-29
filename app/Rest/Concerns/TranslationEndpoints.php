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

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

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
        $id = absint($request['id']);
        $params = $request->get_params();
        $language = Input::key($params['language_code'] ?? '');
        $translated = trim(wp_kses_post(Input::scalar_string($params['translated_text'] ?? '')));
        if (! $id || $language === '') {
            return new WP_Error('wat_invalid_translation', __('String ID en taal zijn verplicht.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }
        if (! $this->is_translatable_language($language)) {
            return new WP_Error('wat_invalid_translation_language', __('Kies een actieve niet-standaardtaal om te vertalen.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }
        $repo = new TranslationRepository();
        $status = Input::key($params['status'] ?? 'published');
        if ($status !== '' && ! in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review', 'outdated'], true)) {
            return new WP_Error('wat_invalid_translation_status', __('Ongeldige vertaalstatus.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }
        if ($status === '') {
            $status = $translated !== '' ? 'published' : 'draft';
        }
        if ($translated === '' && in_array($status, ['published', 'reviewed'], true)) {
            $status = 'draft';
        }

        $settings = Settings::all();
        if (! current_user_can('manage_options') && ! empty($settings['translator_review_required']) && $translated !== '' && in_array($status, ['published', 'reviewed'], true)) {
            $status = 'needs_review';
        }

        $saved = $repo->save_translation($id, $language, $translated, $status, 'manual');
        if (! $saved) {
            return new WP_Error('wat_translation_save_failed', __('Vertaling opslaan mislukt.', 'webactueel-translate-language-dropdowns'), ['status' => 500]);
        }
        $memoryApplied = 0;
        if (! empty($params['apply_memory']) && $translated !== '') {
            $memoryApplied = $repo->apply_translation_memory($id, $language, $translated, $status);
        }
        return ['saved' => true, 'memory_applied' => $memoryApplied];
    }
}
