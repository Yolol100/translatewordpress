<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\ImportExport\CsvExporter;
use Webactueel\Translate\ImportExport\CsvImporter;
use Webactueel\Translate\ImportExport\CsvPreviewer;
use Webactueel\Translate\ImportExport\XliffExporter;
use Webactueel\Translate\ImportExport\XliffImporter;
use Webactueel\Translate\Support\Logger;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Input;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

trait CsvEndpoints
{
    public function csv_preview(WP_REST_Request $request): array
    {
        $files = $request->get_file_params();
        $settings = Settings::all();
        $previewLimit = absint($settings['csv_preview_rows'] ?? 250);
        if ($previewLimit < 1) {
            $previewLimit = 250;
        }
        if ($previewLimit > 1000) {
            $previewLimit = 1000;
        }
        $result = (new CsvPreviewer())->preview_uploaded($files['file'] ?? [], $previewLimit);
        do_action('wat_after_csv_preview', $result);
        return $result;
    }

    public function csv_import(WP_REST_Request $request)
    {
        $params = $request->get_params();
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

    public function xliff_export(WP_REST_Request $request): WP_REST_Response
    {
        $rawLanguages = $request->get_param('languages');
        if (is_string($rawLanguages)) {
            $rawLanguages = explode(',', $rawLanguages);
        }
        $languages = Input::key_list($rawLanguages);
        $mode = Input::key($request->get_param('mode') ?: 'all');
        $xliff = (new XliffExporter())->xliff_string($languages, $mode);
        $response = new WP_REST_Response($xliff);
        $response->set_headers([
            'Content-Type' => 'application/x-xliff+xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="webactueel-translate-export.xliff"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
        return $response;
    }

    public function xliff_import(WP_REST_Request $request)
    {
        $files = $request->get_file_params();
        $params = $request->get_params();
        $languages = [];
        if (isset($params['languages'])) {
            $languages = Input::key_list($params['languages']);
        }

        $result = (new XliffImporter())->import_uploaded($files['file'] ?? [], $languages);
        if (! empty($result['errors']) && empty($result['imported'])) {
            return new WP_Error('wat_xliff_import_failed', implode(' ', (array) $result['errors']), ['status' => 400]);
        }
        Logger::write('info', 'XLIFF import uitgevoerd', $result);
        return $result;
    }
}
