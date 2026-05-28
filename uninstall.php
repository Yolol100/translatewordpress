<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// Uninstall intentionally removes this plugin's own prefixed custom tables and options only.

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Data deletion is intentionally opt-in. Normal uninstall keeps translations/settings
// available for reinstall or migration unless the site owner enabled permanent cleanup.
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
    'wat_ai_credentials',
    'wat_flush_rewrite_rules',
    'wat_performance_snapshot',
    'wat_replaced_plugins',
    'wat_replacement_cleanup_error',
    'wat_replacement_cleanup_targets',
    'wat_setup_state',
];
foreach ($wat_options as $wat_option) {
    delete_option($wat_option);
}

if (function_exists('wp_roles')) {
    foreach (array_keys(wp_roles()->roles) as $wat_role_name) {
        $wat_role = get_role((string) $wat_role_name);
        if ($wat_role && $wat_role->has_cap('wat_manage_translations')) {
            $wat_role->remove_cap('wat_manage_translations');
        }
    }
}
remove_role('wat_translator');

$wat_option_table = esc_sql($wpdb->options);
foreach (['wat_csv_preview_', 'wat_ai_rate_'] as $wat_transient_prefix) {
    $wpdb->query(
        $wpdb->prepare(
            'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
            $wat_option_table,
            $wpdb->esc_like('_transient_' . $wat_transient_prefix) . '%',
            $wpdb->esc_like('_transient_timeout_' . $wat_transient_prefix) . '%'
        )
    ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Identifier placeholder is prepared and prefix is controlled by this plugin.
}

$wat_temp_dir = trailingslashit(get_temp_dir()) . 'webactueel-translate-language-dropdowns';
if (is_dir($wat_temp_dir)) {
    $wat_files = array_merge(
        (array) glob(trailingslashit($wat_temp_dir) . '*'),
        (array) glob(trailingslashit($wat_temp_dir) . '.*')
    );
    foreach ($wat_files as $wat_file) {
        if (! is_string($wat_file) || in_array(basename($wat_file), ['.', '..'], true)) {
            continue;
        }
        if (is_file($wat_file)) {
            wp_delete_file($wat_file);
        }
    }

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_scandir -- Best-effort check before removing this plugin's own temporary directory.
    $wat_remaining_files = array_diff(scandir($wat_temp_dir) ?: [], ['.', '..']);
    if ($wat_remaining_files === []) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Best-effort removal of this plugin's own temporary directory after deleting contained files.
        rmdir($wat_temp_dir);
    }
}
