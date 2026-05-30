<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Compatibility\CompatibilityRegistry;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.

trait DashboardLanguageEndpoints
{
    use LanguageEndpoints;
    public function dashboard(): array
    {
        global $wpdb;

        $jobsTable = Tables::jobs();
        $languagesTable = Tables::languages();
        $stringsTable = Tables::strings();
        $translationsTable = Tables::translations();
        $latestJob = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM %i ORDER BY id DESC LIMIT 1', $jobsTable),
            ARRAY_A
        );
        return [
            'activeLanguages' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE is_active = 1', $languagesTable)),
            'totalStrings' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $stringsTable)),
            'missingTranslations' => (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i s '
                    . 'INNER JOIN %i l ON l.is_active = 1 AND l.is_default = 0 '
                    . 'LEFT JOIN %i t ON t.string_id = s.id '
                    . 'AND t.language_code = l.code '
                    . 'AND t.status IN ("published", "reviewed") '
                    . 'AND t.translated_text <> "" '
                    . 'WHERE t.id IS NULL',
                    $stringsTable,
                    $languagesTable,
                    $translationsTable
                )
            ),
            'lastScan' => $latestJob ?: null,
            'settings' => Settings::all(),
            'cacheVersion' => (string) get_option('wat_cache_version', '1'),
            'compatibility' => CompatibilityRegistry::detected(),
            'multilingualConflict' => CompatibilityRegistry::has_multilingual_conflict(),
            'frontendLimited' => CompatibilityRegistry::should_disable_frontend_replacement(),
        ];
    }
}
