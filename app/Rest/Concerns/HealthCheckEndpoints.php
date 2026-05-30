<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Compatibility\CompatibilityRegistry;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* filter names are backward-compatible plugin API.

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned custom tables; table identifiers are normalized through Tables::sql_identifier().

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
        global $wpdb;

        $settings = Settings::all();
        $checks = [
            'php' => [
                'label' => __('PHP-versie', 'webactueel-translate-language-dropdowns'),
                'status' => version_compare(PHP_VERSION, '8.1', '>=') ? 'pass' : 'fail',
                'detail' => PHP_VERSION,
            ],
            'dom' => [
                'label' => __('DOM/ext-xml', 'webactueel-translate-language-dropdowns'),
                'status' => class_exists('DOMDocument') && extension_loaded('libxml') ? 'pass' : 'fail',
                'detail' => class_exists('DOMDocument') ? __('Beschikbaar', 'webactueel-translate-language-dropdowns') : __('Niet beschikbaar', 'webactueel-translate-language-dropdowns'),
            ],
            'database' => [
                'label' => __('Database-tabellen', 'webactueel-translate-language-dropdowns'),
                'status' => $this->all_plugin_tables_exist() ? 'pass' : 'fail',
                'detail' => __('Plugin-tabellen gecontroleerd.', 'webactueel-translate-language-dropdowns'),
            ],
            'rest' => [
                'label' => __('REST API', 'webactueel-translate-language-dropdowns'),
                'status' => 'pass',
                'detail' => __('Admin-endpoints zijn geregistreerd voor de huidige gebruiker.', 'webactueel-translate-language-dropdowns'),
            ],
            'ai' => [
                'label' => __('AI-provider', 'webactueel-translate-language-dropdowns'),
                'status' => ! empty($settings['ai_enabled']) ? ($this->has_ai_credentials($settings) ? 'pass' : 'warn') : 'info',
                'detail' => ! empty($settings['ai_enabled']) ? __('AI staat aan; controleer providerconfiguratie.', 'webactueel-translate-language-dropdowns') : __('AI staat uit.', 'webactueel-translate-language-dropdowns'),
            ],
            'frontend' => [
                'label' => __('Frontend-renderer', 'webactueel-translate-language-dropdowns'),
                'status' => ! empty($settings['frontend_enabled']) ? 'pass' : 'info',
                'detail' => ! empty($settings['frontend_enabled']) ? __('Frontendvertaling staat aan.', 'webactueel-translate-language-dropdowns') : __('Frontendvertaling staat uit.', 'webactueel-translate-language-dropdowns'),
            ],
            'compatibility' => [
                'label' => __('Compatibiliteit', 'webactueel-translate-language-dropdowns'),
                'status' => CompatibilityRegistry::has_multilingual_conflict() ? 'warn' : 'pass',
                'detail' => CompatibilityRegistry::has_multilingual_conflict() ? __('Andere meertalige plugin gedetecteerd.', 'webactueel-translate-language-dropdowns') : __('Geen bekende meertalige conflictplugin actief.', 'webactueel-translate-language-dropdowns'),
            ],
            'debug' => [
                'label' => __('Debugmodus', 'webactueel-translate-language-dropdowns'),
                'status' => defined('WP_DEBUG') && WP_DEBUG ? 'info' : 'pass',
                'detail' => defined('WP_DEBUG') && WP_DEBUG ? __('WP_DEBUG staat aan.', 'webactueel-translate-language-dropdowns') : __('WP_DEBUG staat uit.', 'webactueel-translate-language-dropdowns'),
            ],
        ];

        $summary = ['pass' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
        foreach ($checks as $check) {
            $status = is_string($check['status']) ? $check['status'] : 'info';
            ++$summary[$status];
        }

        return [
            'ok' => $summary['fail'] === 0,
            'summary' => $summary,
            'checks' => array_values($checks),
            'schema_version' => get_option('wat_schema_version', ''),
            'cache_version' => get_option('wat_cache_version', '1'),
            'object_cache' => function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache(),
            'generated_at' => current_time('mysql'),
        ];
    }

    private function all_plugin_tables_exist(): bool
    {
        global $wpdb;

        foreach ([Tables::languages(), Tables::strings(), Tables::translations(), Tables::sources(), Tables::glossary(), Tables::logs(), Tables::jobs()] as $table) {
            $table_like = $wpdb->esc_like($table);
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
