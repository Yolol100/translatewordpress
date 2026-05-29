<?php

declare(strict_types=1);

namespace Webactueel\Translate\Database;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic parts are escaped plugin-owned table names.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

final class Schema
{
    public const DB_VERSION = '6';

    /**
     * Apply schema updates lazily from admin and REST requests.
     *
     * The transient lock prevents parallel requests from running dbDelta at the same time
     * during deployments or first requests after an update.
     */
    public static function maybe_install(): void
    {
        $installed = (string) get_option('wat_db_version', '');
        if ($installed === self::DB_VERSION) {
            return;
        }

        $lock = (int) get_transient('wat_schema_install_lock');
        if ($lock && $lock > (time() - 120)) {
            return;
        }

        set_transient('wat_schema_install_lock', time(), 120);
        self::install();
        delete_transient('wat_schema_install_lock');
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        dbDelta('CREATE TABLE ' . Tables::languages() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(12) NOT NULL,
            locale VARCHAR(20) NOT NULL,
            name VARCHAR(100) NOT NULL,
            native_name VARCHAR(100) NOT NULL,
            flag VARCHAR(20) DEFAULT '',
            is_default TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            is_rtl TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY is_active (is_active)
        ) {$charset};");

        dbDelta('CREATE TABLE ' . Tables::strings() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            hash CHAR(64) NOT NULL,
            original_text LONGTEXT NOT NULL,
            normalized_text LONGTEXT NOT NULL,
            context VARCHAR(191) DEFAULT '',
            source_type VARCHAR(50) DEFAULT '',
            source_id BIGINT UNSIGNED DEFAULT 0,
            source_key VARCHAR(191) DEFAULT '',
            status VARCHAR(20) DEFAULT 'new',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY hash (hash),
            KEY status (status),
            KEY source_type (source_type),
            KEY source_id (source_id),
            KEY last_seen_at (last_seen_at)
        ) {$charset};");

        dbDelta('CREATE TABLE ' . Tables::translations() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            string_id BIGINT UNSIGNED NOT NULL,
            language_code VARCHAR(12) NOT NULL,
            translated_text LONGTEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'published',
            origin VARCHAR(20) DEFAULT 'manual',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY string_language (string_id, language_code),
            KEY language_code (language_code),
            KEY status (status)
        ) {$charset};");

        dbDelta('CREATE TABLE ' . Tables::sources() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            string_id BIGINT UNSIGNED NOT NULL,
            url TEXT NULL,
            post_id BIGINT UNSIGNED DEFAULT 0,
            post_type VARCHAR(50) DEFAULT '',
            plugin VARCHAR(100) DEFAULT '',
            theme VARCHAR(100) DEFAULT '',
            selector VARCHAR(191) DEFAULT '',
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY string_id (string_id),
            KEY post_id (post_id),
            KEY post_type (post_type)
        ) {$charset};");

        dbDelta('CREATE TABLE ' . Tables::jobs() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_key VARCHAR(64) NOT NULL,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(20) DEFAULT 'queued',
            cursor_value BIGINT UNSIGNED DEFAULT 0,
            total_items INT DEFAULT 0,
            processed_items INT DEFAULT 0,
            found_strings INT DEFAULT 0,
            skipped_items INT DEFAULT 0,
            errors_count INT DEFAULT 0,
            assigned_user_id BIGINT UNSIGNED DEFAULT 0,
            due_at DATETIME NULL,
            options_json LONGTEXT NULL,
            message TEXT NULL,
            started_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_key (job_key),
            KEY status (status),
            KEY type (type),
            KEY assigned_user_id (assigned_user_id),
            KEY due_at (due_at)
        ) {$charset};");

        dbDelta('CREATE TABLE ' . Tables::glossary() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_term VARCHAR(191) NOT NULL,
            target_term VARCHAR(191) NOT NULL,
            language_code VARCHAR(12) NOT NULL,
            case_sensitive TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY language_code (language_code),
            KEY source_term (source_term)
        ) {$charset};");



        dbDelta('CREATE TABLE ' . Tables::ai_usage() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id BIGINT UNSIGNED DEFAULT 0,
            string_id BIGINT UNSIGNED DEFAULT 0,
            user_id BIGINT UNSIGNED DEFAULT 0,
            provider VARCHAR(30) NOT NULL,
            model VARCHAR(100) DEFAULT '',
            source_language VARCHAR(12) DEFAULT '',
            target_language VARCHAR(12) DEFAULT '',
            source_chars INT DEFAULT 0,
            output_chars INT DEFAULT 0,
            estimated_words INT DEFAULT 0,
            memory_reused TINYINT(1) DEFAULT 0,
            glossary_terms INT DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY string_id (string_id),
            KEY user_id (user_id),
            KEY provider (provider),
            KEY target_language (target_language),
            KEY created_at (created_at)
        ) {$charset};");

        dbDelta('CREATE TABLE ' . Tables::logs() . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            context LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY level (level),
            KEY created_at (created_at)
        ) {$charset};");

        update_option('wat_db_version', self::DB_VERSION, false);
        self::seed_default_language();
        if (get_option('wat_cache_version') === false) {
            add_option('wat_cache_version', '1', '', false);
        }
    }

    private static function seed_default_language(): void
    {
        global $wpdb;
        $table = Tables::sql_identifier(Tables::languages());
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the plugin-owned languages table helper.
        if ($count > 0) {
            return;
        }
        $locale = get_locale() ?: 'nl_NL';
        $code = strtolower(substr($locale, 0, 2));
        $now = current_time('mysql');
        $wpdb->insert($table, [
            'code' => sanitize_key($code),
            'locale' => sanitize_text_field($locale),
            'name' => $code === 'nl' ? 'Dutch' : strtoupper($code),
            'native_name' => $code === 'nl' ? 'Nederlands' : strtoupper($code),
            'is_default' => 1,
            'is_active' => 1,
            'is_rtl' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
