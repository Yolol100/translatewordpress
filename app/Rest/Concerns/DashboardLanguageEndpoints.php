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

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This plugin uses its own custom translation tables; queries are scoped and cache invalidation is handled by the plugin.

trait DashboardLanguageEndpoints
{
    use LanguageEndpoints;
    public function dashboard(): array
    {
        global $wpdb;

        $jobsTable = Tables::sql_identifier(Tables::jobs());
        $languagesTable = Tables::sql_identifier(Tables::languages());
        $stringsTable = Tables::sql_identifier(Tables::strings());
        $translationsTable = Tables::sql_identifier(Tables::translations());
        $latestJob = $wpdb->get_row("SELECT * FROM `{$jobsTable}` ORDER BY id DESC LIMIT 1", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
        $missingSql = "SELECT COUNT(*) FROM `{$stringsTable}` s "
            . "INNER JOIN `{$languagesTable}` l ON l.is_active = 1 AND l.is_default = 0 "
            . "LEFT JOIN `{$translationsTable}` t ON t.string_id = s.id "
            . 'AND t.language_code = l.code '
            . 'AND t.status IN ("published", "reviewed") '
            . 'AND t.translated_text <> "" '
            . 'WHERE t.id IS NULL';

        return [
            'activeLanguages' => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$languagesTable}` WHERE is_active = 1"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
            'totalStrings' => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$stringsTable}`"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
            'missingTranslations' => (int) $wpdb->get_var($missingSql), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic parts are escaped plugin-owned table names.
            'lastScan' => $latestJob ?: null,
            'settings' => Settings::all(),
            'cacheVersion' => (string) get_option('wat_cache_version', '1'),
            'compatibility' => CompatibilityRegistry::detected(),
            'multilingualConflict' => CompatibilityRegistry::has_multilingual_conflict(),
            'frontendLimited' => CompatibilityRegistry::should_disable_frontend_replacement(),
        ];
    }

}
