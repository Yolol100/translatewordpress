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
use Webactueel\Translate\Translation\TranslationRepository;
use Webactueel\Translate\Translation\GlossaryRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

trait RegistersRestRoutes
{
    use RestRouteArguments;
    public function routes(): void
    {
        $primary_namespace = $this->namespace;
        foreach (array_unique([$primary_namespace, 'webactueel-translate/v1']) as $namespace) {
            $this->namespace = $namespace;
            $this->register_routes();
        }
        $this->namespace = $primary_namespace;
    }

    private function register_routes(): void
    {
        $this->route('/dashboard', 'GET', 'dashboard');
        register_rest_route($this->namespace, '/languages', [
            ['methods' => 'GET', 'callback' => [$this, 'languages'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'POST', 'callback' => [$this, 'save_language'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->language_args()],
        ]);
        register_rest_route($this->namespace, '/languages/(?P<id>\d+)', [
            ['methods' => 'PUT', 'callback' => [$this, 'save_language'], 'permission_callback' => [$this, 'can_manage'], 'args' => array_merge($this->id_arg(), $this->language_args())],
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_language'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->id_arg()],
        ]);
        $this->route('/strings', 'GET', 'strings', $this->strings_args());
        $this->route('/strings/(?P<id>\d+)/translations', 'GET', 'string_translations', $this->id_arg());
        $this->route('/strings/(?P<id>\d+)', 'PUT', 'update_string', array_merge($this->id_arg(), $this->translation_args()));
        $this->route('/scan/start', 'POST', 'scan_start', $this->scan_start_args());
        $this->route('/scan/status/(?P<id>\d+)', 'GET', 'scan_status', $this->id_arg());
        $this->route('/scan/run-batch/(?P<id>\d+)', 'POST', 'scan_run_batch', array_merge($this->id_arg(), $this->scan_batch_args()));
        $this->route('/scan/pause/(?P<id>\d+)', 'POST', 'scan_pause', $this->id_arg());
        $this->route('/scan/resume/(?P<id>\d+)', 'POST', 'scan_resume', $this->id_arg());
        $this->route('/scan/stop/(?P<id>\d+)', 'POST', 'scan_stop', $this->id_arg());
        $this->route('/csv/preview', 'POST', 'csv_preview');
        $this->route('/csv/import', 'POST', 'csv_import', $this->csv_import_args());
        $this->route('/csv/export', 'GET', 'csv_export');
        register_rest_route($this->namespace, '/glossary', [
            ['methods' => 'GET', 'callback' => [$this, 'glossary'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'POST', 'callback' => [$this, 'save_glossary'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->glossary_args()],
        ]);
        register_rest_route($this->namespace, '/glossary/(?P<id>\d+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_glossary'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->id_arg()],
        ]);
        register_rest_route($this->namespace, '/settings', [
            ['methods' => 'GET', 'callback' => [$this, 'settings'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'PUT', 'callback' => [$this, 'save_settings'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->settings_args()],
        ]);
        $this->route('/compatibility', 'GET', 'compatibility');
        $this->route('/cache/clear', 'POST', 'cache_clear');
        register_rest_route($this->namespace, '/preferences', [
            ['methods' => 'GET', 'callback' => [$this, 'preferences'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'PUT', 'callback' => [$this, 'save_preferences'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->preferences_args()],
        ]);
        register_rest_route($this->namespace, '/logs', [
            ['methods' => 'GET', 'callback' => [$this, 'logs'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'DELETE', 'callback' => [$this, 'clear_logs'], 'permission_callback' => [$this, 'can_manage']],
        ]);
    }

    private function route(string $path, string $methods, string $callback, array $args = []): void
    {
        $route = [
            'methods' => $methods,
            'callback' => [$this, $callback],
            'permission_callback' => [$this, 'can_manage'],
        ];
        if ($args) {
            $route['args'] = $args;
        }
        register_rest_route($this->namespace, $path, $route);
    }

}
