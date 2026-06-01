<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport\Concerns;

use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.

trait SharedImportHelpers
{
    private function import_row_limit(string $filter_name): int
    {
        $settings = Settings::all();
        $maxRows = isset($settings['csv_import_max_rows']) ? Input::absint($settings['csv_import_max_rows'], 10000) : 10000;
        $maxRows = (int) apply_filters($filter_name, max(1, min(50000, $maxRows)));
        return max(1, min(50000, $maxRows));
    }

    /**
     * @param array<int, string> $languages
     * @return array<int, string>
     */
    private function normalize_import_languages(array $languages): array
    {
        return array_values(array_unique(array_filter(array_map('sanitize_key', $languages))));
    }

    private function find_string_id_by_hash(string $hash): int
    {
        if ($hash === '' || strlen($hash) < 16) {
            return 0;
        }

        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare('SELECT id FROM %i WHERE hash = %s LIMIT 1', Tables::strings(), $hash)
        );
    }
}
