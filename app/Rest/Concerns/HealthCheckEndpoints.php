<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Compatibility\CompatibilityRegistry;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Seo\SeoAuditService;
use Webactueel\Translate\Translation\TranslationCoverageReporter;
use Webactueel\Translate\WooCommerce\WooCommerceCoverageReporter;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * Runtime health checks for the admin command center.
 */
trait HealthCheckEndpoints
{
    /**
     * Return a client-safe operational health report.
     *
     * @return array<string, mixed>
     */
    public function health(): array
    {
        $checks = $this->health_checks(Settings::all());
        $summary = $this->health_summary($checks);

        return [
            'ok' => $summary['fail'] === 0,
            'summary' => $summary,
            'checks' => array_values($checks),
            'schema_version' => get_option('wat_db_version', ''),
            'cache_version' => get_option('wat_cache_version', '1'),
            'object_cache' => function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache(),
            'generated_at' => current_time('mysql'),
        ];
    }

    private function health_checks(array $settings): array
    {
        return [
            'php' => $this->php_health_check(),
            'dom' => $this->dom_health_check(),
            'database' => $this->database_health_check(),
            'rest' => $this->rest_health_check(),
            'ai' => $this->ai_health_check($settings),
            'frontend' => $this->frontend_health_check($settings),
            'seo' => $this->seo_health_check(),
            'coverage' => $this->coverage_health_check(),
            'woocommerce' => $this->woocommerce_health_check(),
            'compatibility' => $this->compatibility_health_check(),
            'debug' => $this->debug_health_check(),
        ];
    }

    private function health_summary(array $checks): array
    {
        $summary = ['pass' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
        foreach ($checks as $check) {
            $status = is_string($check['status']) ? $check['status'] : 'info';
            ++$summary[$status];
        }

        return $summary;
    }

    private function php_health_check(): array
    {
        return [
            'label' => __('PHP-versie', 'webactueel-translate-language-dropdowns'),
            'status' => version_compare(PHP_VERSION, '8.1', '>=') ? 'pass' : 'fail',
            'detail' => PHP_VERSION,
        ];
    }

    private function dom_health_check(): array
    {
        $available = class_exists('DOMDocument') && extension_loaded('libxml');

        return [
            'label' => __('DOM/ext-xml', 'webactueel-translate-language-dropdowns'),
            'status' => $available ? 'pass' : 'fail',
            'detail' => class_exists('DOMDocument') ? __('Beschikbaar', 'webactueel-translate-language-dropdowns') : __('Niet beschikbaar', 'webactueel-translate-language-dropdowns'),
        ];
    }

    private function database_health_check(): array
    {
        return [
            'label' => __('Database-tabellen', 'webactueel-translate-language-dropdowns'),
            'status' => $this->all_plugin_tables_exist() ? 'pass' : 'fail',
            'detail' => __('Plugin-tabellen gecontroleerd.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    private function rest_health_check(): array
    {
        return [
            'label' => __('REST API', 'webactueel-translate-language-dropdowns'),
            'status' => 'pass',
            'detail' => __('Admin-endpoints zijn geregistreerd voor de huidige gebruiker.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    private function ai_health_check(array $settings): array
    {
        $enabled = ! empty($settings['ai_enabled']);

        return [
            'label' => __('AI-provider', 'webactueel-translate-language-dropdowns'),
            'status' => $enabled ? ($this->has_ai_credentials($settings) ? 'pass' : 'warn') : 'info',
            'detail' => $enabled ? __('AI staat aan; controleer providerconfiguratie.', 'webactueel-translate-language-dropdowns') : __('AI staat uit.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    private function frontend_health_check(array $settings): array
    {
        $enabled = ! empty($settings['frontend_enabled']);

        return [
            'label' => __('Frontend-renderer', 'webactueel-translate-language-dropdowns'),
            'status' => $enabled ? 'pass' : 'info',
            'detail' => $enabled ? __('Frontendvertaling staat aan.', 'webactueel-translate-language-dropdowns') : __('Frontendvertaling staat uit.', 'webactueel-translate-language-dropdowns'),
        ];
    }


    private function seo_health_check(): array
    {
        $report = (new SeoAuditService())->report();
        $summary = $report['summary'] ?? [];
        $warn = absint($summary['warn'] ?? 0);
        $fail = absint($summary['fail'] ?? 0);

        return [
            'label' => __('Meertalige SEO', 'webactueel-translate-language-dropdowns'),
            'status' => $fail > 0 ? 'fail' : ($warn > 0 ? 'warn' : 'pass'),
            'detail' => sprintf(
                /* translators: 1: warning count, 2: failure count. */
                __('SEO-check: %1$d waarschuwingen, %2$d fouten.', 'webactueel-translate-language-dropdowns'),
                $warn,
                $fail
            ),
            'report' => $report,
        ];
    }

    private function coverage_health_check(): array
    {
        $coverage = TranslationCoverageReporter::summary();
        $average = (float) ($coverage['average_percent'] ?? 0.0);

        return [
            'label' => __('Vertaaldekking', 'webactueel-translate-language-dropdowns'),
            'status' => $average >= 80.0 ? 'pass' : ($average > 0.0 ? 'warn' : 'info'),
            'detail' => sprintf(
                /* translators: %s: average translation coverage percentage. */
                __('Gemiddelde dekking voor niet-standaardtalen: %s%%.', 'webactueel-translate-language-dropdowns'),
                number_format_i18n($average, 1)
            ),
            'coverage' => $coverage,
        ];
    }

    private function woocommerce_health_check(): array
    {
        $report = (new WooCommerceCoverageReporter())->report();
        $summary = $report['summary'] ?? [];
        $warn = absint($summary['warn'] ?? 0);
        $fail = absint($summary['fail'] ?? 0);

        return [
            'label' => __('WooCommerce taalveiligheid', 'webactueel-translate-language-dropdowns'),
            'status' => $fail > 0 ? 'fail' : ($warn > 0 ? 'warn' : 'pass'),
            'detail' => sprintf(
                /* translators: 1: warning count, 2: failure count. */
                __('WooCommerce-check: %1$d waarschuwingen, %2$d fouten.', 'webactueel-translate-language-dropdowns'),
                $warn,
                $fail
            ),
            'report' => $report,
        ];
    }

    private function compatibility_health_check(): array
    {
        $hasConflict = CompatibilityRegistry::has_multilingual_conflict();

        return [
            'label' => __('Compatibiliteit', 'webactueel-translate-language-dropdowns'),
            'status' => $hasConflict ? 'warn' : 'pass',
            'detail' => $hasConflict ? __('Andere meertalige plugin gedetecteerd.', 'webactueel-translate-language-dropdowns') : __('Geen bekende meertalige conflictplugin actief.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    private function debug_health_check(): array
    {
        $debug = defined('WP_DEBUG') && WP_DEBUG;

        return [
            'label' => __('Debugmodus', 'webactueel-translate-language-dropdowns'),
            'status' => $debug ? 'info' : 'pass',
            'detail' => $debug ? __('WP_DEBUG staat aan.', 'webactueel-translate-language-dropdowns') : __('WP_DEBUG staat uit.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    private function all_plugin_tables_exist(): bool
    {
        global $wpdb;

        foreach ([Tables::languages(), Tables::strings(), Tables::translations(), Tables::sources(), Tables::glossary(), Tables::logs(), Tables::jobs(), Tables::ai_usage()] as $table) {
            $table_like = $wpdb->esc_like($table);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Health check intentionally verifies plugin-owned table existence.
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_like));
            if (! is_string($exists) || $exists === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $settings Settings array.
     */
    private function has_ai_credentials(array $settings): bool
    {
        $provider = isset($settings['ai_provider']) && is_string($settings['ai_provider']) ? $settings['ai_provider'] : '';
        if ($provider === '') {
            return false;
        }

        return Settings::has_ai_api_key($provider);
    }
}
