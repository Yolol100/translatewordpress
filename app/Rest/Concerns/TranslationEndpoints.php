<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Cache\CacheInvalidator;
use Webactueel\Translate\Compatibility\CompatibilityRegistry;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\ImportExport\CsvExporter;
use Webactueel\Translate\ImportExport\CsvImporter;
use Webactueel\Translate\ImportExport\CsvPreviewer;
use Webactueel\Translate\Scanner\ScanBatchRunner;
use Webactueel\Translate\Scanner\ScanJobManager;
use Webactueel\Translate\Seo\HreflangManager;
use Webactueel\Translate\Support\Logger;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Translation\TranslationRepository;
use Webactueel\Translate\Translation\GlossaryRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

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
        $params = $request->get_json_params() ?: [];
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
        if ($status !== '' && ! in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review'], true)) {
            return new WP_Error('wat_invalid_translation_status', __('Ongeldige vertaalstatus.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }
        if ($status === '') {
            $status = $translated !== '' ? 'published' : 'draft';
        }
        if ($translated === '' && in_array($status, ['published', 'reviewed'], true)) {
            $status = 'draft';
        }
        $saved = $repo->save_translation($id, $language, $translated, $status, 'manual');
        if (! $saved) {
            return new WP_Error('wat_translation_save_failed', __('Vertaling opslaan mislukt.', 'webactueel-translate-language-dropdowns'), ['status' => 500]);
        }
        $memoryApplied = 0;
        if (! empty($params['apply_memory']) && $translated !== '') {
            $memoryApplied = $repo->apply_translation_memory($id, $language, $translated, $status);
        }
        do_action('wat_after_translation_saved', $id, $language);
        return ['saved' => true, 'memory_applied' => $memoryApplied];
    }
}
