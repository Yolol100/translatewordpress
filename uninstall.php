<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Uninstall intentionally removes this plugin's own prefixed custom tables and options only.

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$wat_delete = get_option('wat_delete_data_on_uninstall', '0');
if ($wat_delete !== '1') {
    return;
}

global $wpdb;
$wat_tables = [
    $wpdb->prefix . 'wat_languages',
    $wpdb->prefix . 'wat_strings',
    $wpdb->prefix . 'wat_translations',
    $wpdb->prefix . 'wat_string_sources',
    $wpdb->prefix . 'wat_scan_jobs',
    $wpdb->prefix . 'wat_logs',
    $wpdb->prefix . 'wat_glossary',
];
foreach ($wat_tables as $wat_table) {
    $wat_table = (string) $wat_table;
    if (strpos($wat_table, $wpdb->prefix . 'wat_') !== 0) {
        continue;
    }

    $wat_table = esc_sql($wat_table);
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wat_table)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Identifier placeholder is prepared and table prefix is validated above.
}

$wat_options = [
    'wat_db_version',
    'wat_settings',
    'wat_delete_data_on_uninstall',
    'wat_cache_version',
];
foreach ($wat_options as $wat_option) {
    delete_option($wat_option);
}
