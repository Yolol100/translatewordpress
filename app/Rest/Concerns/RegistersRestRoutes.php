<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

if (! defined('ABSPATH')) {
    exit;
}

trait RegistersRestRoutes
{
    use RestRouteArguments;
    /**
     * Register REST routes.
     *
     * The historical `webactueel-translate/v1` namespace is disabled by default to
     * avoid doubling the public endpoint surface. Existing integrations can opt in
     * temporarily while they migrate to the canonical namespace.
     */
    public function routes(): void
    {
        $primary_namespace = $this->namespace;
        $namespaces = [$primary_namespace];

        if ((bool) apply_filters('wat_register_legacy_rest_namespace', false)) {
            $namespaces[] = 'webactueel-translate/v1';
        }

        foreach (array_unique($namespaces) as $namespace) {
            $this->namespace = $namespace;
            $this->register_routes();
        }
        $this->namespace = $primary_namespace;
    }

    private function register_routes(): void
    {
        $this->route('/dashboard', 'GET', 'dashboard');
        $this->route('/health', 'GET', 'health');
        $this->route('/seo/audit', 'GET', 'seo_audit');
        $this->route('/translation-coverage', 'GET', 'translation_coverage');
        $this->route('/woocommerce/coverage', 'GET', 'woocommerce_coverage');
        register_rest_route($this->namespace, '/languages', [
            ['methods' => 'GET', 'callback' => [$this, 'languages'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'POST', 'callback' => [$this, 'save_language'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->language_args()],
        ]);
        register_rest_route($this->namespace, '/languages/(?P<id>\d+)', [
            ['methods' => ['PUT', 'POST'], 'callback' => [$this, 'save_language'], 'permission_callback' => [$this, 'can_manage'], 'args' => array_merge($this->id_arg(), $this->language_args())],
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_language'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->id_arg()],
        ]);
        $this->route('/languages/(?P<id>\d+)/delete', 'POST', 'delete_language', $this->id_arg());
        $this->translate_route('/strings', 'GET', 'strings', $this->strings_args());
        $this->translate_route('/strings/(?P<id>\d+)/translations', 'GET', 'string_translations', $this->id_arg());
        $this->translate_route('/strings/(?P<id>\d+)', ['PUT', 'POST'], 'update_string', array_merge($this->id_arg(), $this->translation_args()));
        $this->scan_route('/scan/start', 'POST', 'scan_start', $this->scan_start_args());
        $this->scan_route('/scan/status/(?P<id>\d+)', 'GET', 'scan_status', $this->id_arg());
        $this->scan_route('/scan/run-batch/(?P<id>\d+)', 'POST', 'scan_run_batch', array_merge($this->id_arg(), $this->scan_batch_args()));
        $this->scan_route('/scan/pause/(?P<id>\d+)', 'POST', 'scan_pause', $this->id_arg());
        $this->scan_route('/scan/resume/(?P<id>\d+)', 'POST', 'scan_resume', $this->id_arg());
        $this->scan_route('/scan/stop/(?P<id>\d+)', 'POST', 'scan_stop', $this->id_arg());
        $this->import_export_route('/csv/preview', 'POST', 'csv_preview');
        $this->import_export_route('/csv/import', 'POST', 'csv_import', $this->csv_import_args());
        $this->import_export_route('/csv/export', 'GET', 'csv_export', $this->export_args());
        $this->import_export_route('/xliff/export', 'GET', 'xliff_export', $this->export_args());
        $this->import_export_route('/xliff/import', 'POST', 'xliff_import', $this->import_languages_args());
        register_rest_route($this->namespace, '/glossary', [
            ['methods' => 'GET', 'callback' => [$this, 'glossary'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'POST', 'callback' => [$this, 'save_glossary'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->glossary_args()],
        ]);
        register_rest_route($this->namespace, '/glossary/(?P<id>\d+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_glossary'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->id_arg()],
        ]);
        $this->route('/glossary/(?P<id>\d+)/delete', 'POST', 'delete_glossary', $this->id_arg());
        register_rest_route($this->namespace, '/settings', [
            ['methods' => 'GET', 'callback' => [$this, 'settings'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => ['PUT', 'POST'], 'callback' => [$this, 'save_settings'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->settings_args()],
        ]);
        $this->route('/compatibility', 'GET', 'compatibility');
        $this->route('/cache/clear', 'POST', 'cache_clear');
        register_rest_route($this->namespace, '/preferences', [
            ['methods' => 'GET', 'callback' => [$this, 'preferences'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => ['PUT', 'POST'], 'callback' => [$this, 'save_preferences'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->preferences_args()],
        ]);
        register_rest_route($this->namespace, '/logs', [
            ['methods' => 'GET', 'callback' => [$this, 'logs'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'DELETE', 'callback' => [$this, 'clear_logs'], 'permission_callback' => [$this, 'can_manage']],
        ]);
        $this->route('/logs/clear', 'POST', 'clear_logs');
    }

    /**
     * Register a manage-options protected REST route.
     *
     * Use explicit `register_rest_route()` blocks instead when a route needs a
     * different permission callback, multiple methods, or special argument handling.
     *
     * @param array<string, mixed> $args REST argument schema.
     */
    private function route(string $path, string|array $methods, string $callback, array $args = []): void
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

    /**
     * Register a translation-workflow route available to explicitly allowed
     * translation users, not just full administrators.
     *
     * @param array<string, mixed> $args REST argument schema.
     */
    private function translate_route(string $path, string|array $methods, string $callback, array $args = []): void
    {
        $route = [
            'methods' => $methods,
            'callback' => [$this, $callback],
            'permission_callback' => [$this, 'can_translate'],
        ];
        if ($args) {
            $route['args'] = $args;
        }
        register_rest_route($this->namespace, $path, $route);
    }

    /**
     * Register a scan route protected by the dedicated scan capability.
     *
     * @param array<string, mixed> $args REST argument schema.
     */
    private function scan_route(string $path, string|array $methods, string $callback, array $args = []): void
    {
        $route = [
            'methods' => $methods,
            'callback' => [$this, $callback],
            'permission_callback' => [$this, 'can_scan'],
        ];
        if ($args) {
            $route['args'] = $args;
        }
        register_rest_route($this->namespace, $path, $route);
    }

    /**
     * Register an import/export route protected by the dedicated data-movement capability.
     *
     * @param array<string, mixed> $args REST argument schema.
     */
    private function import_export_route(string $path, string|array $methods, string $callback, array $args = []): void
    {
        $route = [
            'methods' => $methods,
            'callback' => [$this, $callback],
            'permission_callback' => [$this, 'can_import_export'],
        ];
        if ($args) {
            $route['args'] = $args;
        }
        register_rest_route($this->namespace, $path, $route);
    }
}
