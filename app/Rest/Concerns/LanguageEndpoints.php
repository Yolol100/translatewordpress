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

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This plugin uses its own custom translation tables; queries are scoped and cache invalidation is handled by the plugin.

trait LanguageEndpoints
{
    public function languages(): array
    {
        global $wpdb;
        $languagesTable = Tables::sql_identifier(Tables::languages());
        return $wpdb->get_results("SELECT * FROM `{$languagesTable}` ORDER BY is_default DESC, native_name ASC", ARRAY_A) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
    }

    public function save_language(WP_REST_Request $request)
    {
        global $wpdb;
        $languages_table = Tables::sql_identifier(Tables::languages());
        $id = absint($request['id'] ?? 0);
        $params = $request->get_json_params() ?: $request->get_params();
        $now = current_time('mysql');
        $code = Input::key($params['code'] ?? '');
        if ($code !== '' && ! preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/', $code)) {
            return new WP_Error('wat_invalid_language_code', __('Gebruik een geldige taalcode, bijvoorbeeld nl, en of de.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }
        $locale = preg_replace('/[^A-Za-z0-9_]/', '', str_replace('-', '_', Input::text($params['locale'] ?? ''))) ?: '';
        if (preg_match('/^([a-z]{2,3})_([a-z]{2})$/i', $locale, $localeMatch)) {
            $locale = strtolower($localeMatch[1]) . '_' . strtoupper($localeMatch[2]);
        }
        $nativeName = Input::text($params['native_name'] ?? '');
        $data = [
            'code' => $code,
            'locale' => $locale,
            'name' => Input::text($params['name'] ?? ''),
            'native_name' => $nativeName,
            'flag' => Input::text($params['flag'] ?? $code),
            'is_default' => ! empty($params['is_default']) ? 1 : 0,
            'is_active' => array_key_exists('is_active', $params) ? (! empty($params['is_active']) ? 1 : 0) : 1,
            'is_rtl' => ! empty($params['is_rtl']) ? 1 : 0,
            'updated_at' => $now,
        ];
        if ($data['code'] === '' || $data['locale'] === '' || $data['native_name'] === '') {
            return new WP_Error('wat_invalid_language', __('Code, locale en native naam zijn verplicht.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }
        if ($data['name'] === '') {
            $data['name'] = $data['native_name'];
        }
        if ($data['flag'] === '') {
            $data['flag'] = $data['code'];
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
        $existingId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$languages_table}` WHERE code = %s LIMIT 1", $data['code']));
        if ($existingId && $existingId !== $id) {
            return new WP_Error('wat_language_exists', __('Deze taalcode bestaat al.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        $currentlyDefault = false;
        if ($id) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
            $currentRow = $wpdb->get_row($wpdb->prepare("SELECT id, is_default FROM `{$languages_table}` WHERE id = %d LIMIT 1", $id), ARRAY_A);
            if (! is_array($currentRow)) {
                return new WP_Error('wat_language_not_found', __('Taal niet gevonden.', 'webactueel-translate-language-dropdowns'), ['status' => 404]);
            }
            $currentlyDefault = ! empty($currentRow['is_default']);
        }

        $makeDefault = ! empty($data['is_default']) || $currentlyDefault;
        if ($makeDefault) {
            // Save the language first, then switch the default flag. This avoids
            // temporarily leaving the site without a default language if insert/update fails.
            $data['is_active'] = 1;
            $data['is_default'] = 0;
        }
        if ($id) {
            $result = $wpdb->update(Tables::languages(), $data, ['id' => $id]);
            if ($result === false) {
                return new WP_Error('wat_language_update_failed', __('Taal opslaan mislukt:', 'webactueel-translate-language-dropdowns') . ' ' . ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.', ['status' => 500]);
            }
        } else {
            $data['created_at'] = $now;
            $result = $wpdb->insert(Tables::languages(), $data);
            if ($result === false || ! $wpdb->insert_id) {
                return new WP_Error('wat_language_insert_failed', __('Taal toevoegen mislukt:', 'webactueel-translate-language-dropdowns') . ' ' . ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.', ['status' => 500]);
            }
            $id = (int) $wpdb->insert_id;
        }
        if ($makeDefault) {
            $defaultResult = $wpdb->update(Tables::languages(), ['is_default' => 1, 'is_active' => 1, 'updated_at' => $now], ['id' => $id]);
            if ($defaultResult === false) {
                return new WP_Error('wat_language_default_failed', __('Standaardtaal instellen mislukt:', 'webactueel-translate-language-dropdowns') . ' ' . ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.', ['status' => 500]);
            }

            $cleanupResult = $wpdb->query($wpdb->prepare("UPDATE `{$languages_table}` SET is_default = 0 WHERE id <> %d AND is_default = 1", $id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name is escaped via esc_sql().
            if ($cleanupResult === false) {
                return new WP_Error('wat_language_default_cleanup_failed', __('Oude standaardtaal opschonen mislukt:', 'webactueel-translate-language-dropdowns') . ' ' . ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.', ['status' => 500]);
            }
        }

        LanguageDetector::reset_cache();
        CacheInvalidator::bump();
        do_action('wat_language_routes_changed');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
        $saved = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$languages_table}` WHERE id = %d LIMIT 1", $id), ARRAY_A);
        return ['id' => $id, 'saved' => true, 'language' => $saved ?: $data];
    }

    public function delete_language(WP_REST_Request $request)
    {
        global $wpdb;
        $languages_table = Tables::sql_identifier(Tables::languages());
        $id = absint($request['id']);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
        $language = $wpdb->get_row($wpdb->prepare("SELECT id, code, is_default FROM `{$languages_table}` WHERE id = %d LIMIT 1", $id), ARRAY_A);
        if (! is_array($language)) {
            return new WP_Error('wat_language_not_found', __('Taal niet gevonden.', 'webactueel-translate-language-dropdowns'), ['status' => 404]);
        }
        if (! empty($language['is_default'])) {
            return new WP_Error('wat_default_language_delete_forbidden', __('De standaardtaal kan niet worden verwijderd.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        $deleted = $wpdb->delete(Tables::languages(), ['id' => $id, 'is_default' => 0]);
        if ($deleted === false) {
            return new WP_Error('wat_language_delete_failed', __('Taal verwijderen mislukt:', 'webactueel-translate-language-dropdowns') . ' ' . ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.', ['status' => 500]);
        }
        if ($deleted < 1) {
            return new WP_Error('wat_language_delete_unchanged', __('Taal is niet verwijderd.', 'webactueel-translate-language-dropdowns'), ['status' => 409]);
        }

        $code = Input::key($language['code'] ?? '');
        if ($code !== '') {
            $wpdb->delete(Tables::translations(), ['language_code' => $code]);
            $wpdb->delete(Tables::glossary(), ['language_code' => $code]);
        }

        LanguageDetector::reset_cache();
        CacheInvalidator::bump();
        do_action('wat_language_routes_changed');
        return ['deleted' => true, 'id' => $id];
    }
}
