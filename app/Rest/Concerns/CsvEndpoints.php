<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Cache\CacheInvalidator;
use Webactueel\Translate\Compatibility\CompatibilityRegistry;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\ImportExport\CsvExporter;
use Webactueel\Translate\ImportExport\CsvImporter;
use Webactueel\Translate\ImportExport\CsvPreviewer;
use Webactueel\Translate\Scanner\ScanBatchRunner;
use Webactueel\Translate\Scanner\ScanJobManager;
use Webactueel\Translate\Seo\HreflangManager;
use Webactueel\Translate\Support\Logger;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Translation\TranslationRepository;
use Webactueel\Translate\Translation\GlossaryRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

trait CsvEndpoints
{
    public function csv_preview(WP_REST_Request $request): array
    {
        $files = $request->get_file_params();
        $result = (new CsvPreviewer())->preview_uploaded($files['file'] ?? [], absint(Settings::all()['csv_preview_rows']));
        do_action('wat_after_csv_preview', $result);
        return $result;
    }

    public function csv_import(WP_REST_Request $request)
    {
        $params = $request->get_json_params() ?: $request->get_params();
        $token = preg_replace('/[^a-zA-Z0-9]/', '', Input::scalar_string($params['preview_token'] ?? ''));
        if ($token === '') {
            return new WP_Error('wat_csv_preview_required', __('CSV import vereist eerst een geldige preview.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }
        $languages = [];
        if (isset($params['languages'])) {
            $languages = Input::key_list($params['languages']);
        }
        $result = (new CsvImporter())->import_token($token, $languages);
        if (! empty($result['errors']) && empty($result['imported'])) {
            return new WP_Error('wat_csv_import_failed', implode(' ', (array) $result['errors']), ['status' => 400]);
        }
        Logger::write('info', 'CSV import uitgevoerd', $result);
        return $result;
    }

    public function csv_export(WP_REST_Request $request): WP_REST_Response
    {
        $rawLanguages = $request->get_param('languages');
        if (is_string($rawLanguages)) {
            $rawLanguages = explode(',', $rawLanguages);
        }
        $languages = Input::key_list($rawLanguages);
        $mode = Input::key($request->get_param('mode') ?: 'all');
        $csv = (new CsvExporter())->csv_string($languages, $mode);
        $response = new WP_REST_Response($csv);
        $response->set_headers([
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="webactueel-translate-language-dropdowns-export.csv"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
        return $response;
    }
}
