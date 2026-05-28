<?php

declare(strict_types=1);

namespace Webactueel\Translate\Database;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.

final class Tables
{
    public static function languages(): string { global $wpdb; return $wpdb->prefix . 'wat_languages'; }
    public static function strings(): string { global $wpdb; return $wpdb->prefix . 'wat_strings'; }
    public static function translations(): string { global $wpdb; return $wpdb->prefix . 'wat_translations'; }
    public static function sources(): string { global $wpdb; return $wpdb->prefix . 'wat_string_sources'; }
    public static function jobs(): string { global $wpdb; return $wpdb->prefix . 'wat_scan_jobs'; }
    public static function logs(): string { global $wpdb; return $wpdb->prefix . 'wat_logs'; }
    public static function glossary(): string { global $wpdb; return $wpdb->prefix . 'wat_glossary'; }

    /**
     * Return a plugin-owned SQL identifier escaped for safe interpolation.
     *
     * WordPress placeholders cannot be used for identifiers such as table names,
     * so all direct custom-table queries should pass Tables::* values through this
     * helper before interpolating them into SQL.
     */
    public static function sql_identifier(string $table): string
    {
        return esc_sql($table);
    }
}

