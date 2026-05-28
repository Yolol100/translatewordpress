<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Scanner\ScanBatchRunner;
use Webactueel\Translate\Scanner\ScanJobManager;
use Webactueel\Translate\Support\Logger;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Input;
use WP_Error;
use WP_REST_Request;

if (! defined('ABSPATH')) {
    exit;
}

trait ScanEndpoints
{
    public function scan_start(WP_REST_Request $request)
    {
        $bufferLevel = ob_get_level();
        ob_start();
        try {
            $params = $request->get_params();
            $job = (new ScanJobManager())->create(Input::key($params['type'] ?? 'full'), $params);
            $leakedOutput = ob_get_clean();
            if (is_string($leakedOutput) && $leakedOutput !== '') {
                Logger::write('warning', 'Output tijdens scan start opgeschoond', ['length' => strlen($leakedOutput)]);
            }
            Logger::write('info', 'Scan job gestart', $job);
            return $job;
        } catch (\Throwable $e) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            Logger::write('error', 'Scan starten mislukt', ['error' => $e->getMessage()]);
            return new WP_Error('wat_scan_start_failed', __('Scan starten mislukt. Controleer de logs of probeer opnieuw.', 'webactueel-translate-language-dropdowns'), ['status' => 500]);
        }
    }

    public function scan_status(WP_REST_Request $request): array
    {
        return (new ScanJobManager())->get(absint($request['id']));
    }

    public function scan_run_batch(WP_REST_Request $request)
    {
        $bufferLevel = ob_get_level();
        ob_start();
        try {
            $params = $request->get_params();
            $result = (new ScanBatchRunner())->run(absint($request['id']), Input::absint($params['batch_size'] ?? Settings::all()['scan_batch_size'], (int) Settings::all()['scan_batch_size']));
            $leakedOutput = ob_get_clean();
            if (is_string($leakedOutput) && $leakedOutput !== '') {
                Logger::write('warning', 'Output tijdens scan batch opgeschoond', ['length' => strlen($leakedOutput)]);
            }
            return $result;
        } catch (\Throwable $e) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            Logger::write('error', 'Scan batch mislukt', ['job_id' => absint($request['id']), 'error' => $e->getMessage()]);
            return new WP_Error('wat_scan_batch_failed', __('Scan batch mislukt. Controleer de logs of probeer opnieuw.', 'webactueel-translate-language-dropdowns'), ['status' => 500]);
        }
    }

    public function scan_pause(WP_REST_Request $request): array
    {
        return (new ScanJobManager())->set_status(absint($request['id']), 'paused');
    }

    public function scan_resume(WP_REST_Request $request): array
    {
        return (new ScanJobManager())->set_status(absint($request['id']), 'running');
    }

    public function scan_stop(WP_REST_Request $request): array
    {
        return (new ScanJobManager())->set_status(absint($request['id']), 'stopped');
    }
}
