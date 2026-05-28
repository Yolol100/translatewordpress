<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Cache\CacheInvalidator;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Support\Input;
use WP_Error;
use WP_REST_Request;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned custom tables; table identifiers are normalized through Tables::sql_identifier().

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.

trait LanguageEndpoints
{
    public function languages(): array
    {
        global $wpdb;
        $languagesTable = Tables::sql_identifier(Tables::languages());
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
        return $wpdb->get_results(
            "SELECT * FROM `{$languagesTable}` ORDER BY is_default DESC, native_name ASC",
            ARRAY_A
        ) ?: [];
    }

    public function save_language(WP_REST_Request $request)
    {
        global $wpdb;
        $languages_table = Tables::sql_identifier(Tables::languages());
        $id = absint($request['id'] ?? 0);
        $params = $request->get_params();
        $now = current_time('mysql');
        $code = Input::key($params['code'] ?? '');
        if ($code !== '' && ! preg_match('/^[a-z]{2,3}(?:[-_][a-z0-9]{2,8})?$/', $code)) {
            return new WP_Error(
                'wat_invalid_language_code',
                __('Gebruik een geldige taalcode, bijvoorbeeld nl, en of de.', 'webactueel-translate-language-dropdowns'),
                ['status' => 400]
            );
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
            return new WP_Error(
                'wat_invalid_language',
                __('Code, locale en native naam zijn verplicht.', 'webactueel-translate-language-dropdowns'),
                ['status' => 400]
            );
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
        $previousCode = '';
        if ($id) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
            $currentRow = $wpdb->get_row($wpdb->prepare("SELECT id, code, is_default FROM `{$languages_table}` WHERE id = %d LIMIT 1", $id), ARRAY_A);
            if (! is_array($currentRow)) {
                return new WP_Error('wat_language_not_found', __('Taal niet gevonden.', 'webactueel-translate-language-dropdowns'), ['status' => 404]);
            }
            $previousCode = Input::key($currentRow['code'] ?? '');
            $currentlyDefault = ! empty($currentRow['is_default']);
        }

        $makeDefault = ! empty($data['is_default']) || $currentlyDefault;
        if ($makeDefault) {
            // Save the language first, then switch the default flag. This avoids
            // temporarily leaving the site without a default language if insert/update fails.
            $data['is_active'] = 1;
            $data['is_default'] = 0;
        }
        $transactionStarted = $this->start_language_transaction();
        if ($id) {
            $result = $wpdb->update(Tables::languages(), $data, ['id' => $id]);
            if ($result === false) {
                return $this->abort_language_transaction($transactionStarted, new WP_Error(
                    'wat_language_update_failed',
                    __('Taal opslaan mislukt:', 'webactueel-translate-language-dropdowns') . ' ' .
                        ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.',
                    ['status' => 500]
                ));
            }
        } else {
            $data['created_at'] = $now;
            $result = $wpdb->insert(Tables::languages(), $data);
            if ($result === false || ! $wpdb->insert_id) {
                return $this->abort_language_transaction($transactionStarted, new WP_Error(
                    'wat_language_insert_failed',
                    __('Taal toevoegen mislukt:', 'webactueel-translate-language-dropdowns') . ' ' .
                        ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.',
                    ['status' => 500]
                ));
            }
            $id = (int) $wpdb->insert_id;
        }
        if ($makeDefault) {
            $defaultResult = $wpdb->update(Tables::languages(), ['is_default' => 1, 'is_active' => 1, 'updated_at' => $now], ['id' => $id]);
            if ($defaultResult === false) {
                return $this->abort_language_transaction($transactionStarted, new WP_Error(
                    'wat_language_default_failed',
                    __('Standaardtaal instellen mislukt:', 'webactueel-translate-language-dropdowns') . ' ' .
                        ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.',
                    ['status' => 500]
                ));
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
            $cleanupResult = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE `{$languages_table}` SET is_default = 0 WHERE id <> %d AND is_default = 1",
                    $id
                )
            );
            if ($cleanupResult === false) {
                return $this->abort_language_transaction($transactionStarted, new WP_Error(
                    'wat_language_default_cleanup_failed',
                    __('Oude standaardtaal opschonen mislukt:', 'webactueel-translate-language-dropdowns') . ' ' .
                        ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.',
                    ['status' => 500]
                ));
            }
        }

        if ($previousCode !== '' && $previousCode !== $data['code']) {
            $migrationResult = $this->migrate_language_code_references($previousCode, $data['code']);
            if (is_wp_error($migrationResult)) {
                return $this->abort_language_transaction($transactionStarted, $migrationResult);
            }
        }

        $commitResult = $this->commit_language_transaction($transactionStarted);
        if (is_wp_error($commitResult)) {
            return $commitResult;
        }

        LanguageDetector::reset_cache();
        CacheInvalidator::bump();
        do_action('wat_language_routes_changed');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
        $saved = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$languages_table}` WHERE id = %d LIMIT 1", $id), ARRAY_A);
        return ['id' => $id, 'saved' => true, 'language' => $saved ?: $data];
    }

    private function start_language_transaction(): bool
    {
        global $wpdb;

        return $wpdb->query('START TRANSACTION') !== false; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Transaction control statement contains no user input.
    }

    /**
     * @return true|WP_Error
     */
    private function commit_language_transaction(bool $started)
    {
        global $wpdb;

        if (! $started) {
            return true;
        }

        if ($wpdb->query('COMMIT') !== false) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Transaction control statement contains no user input.
            return true;
        }

        $this->rollback_language_transaction(true);
        return new WP_Error(
            'wat_language_transaction_commit_failed',
            __('Taalwijziging bevestigen mislukt:', 'webactueel-translate-language-dropdowns') . ' ' .
                ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.',
            ['status' => 500]
        );
    }

    private function abort_language_transaction(bool $started, WP_Error $error): WP_Error
    {
        $this->rollback_language_transaction($started);
        return $error;
    }

    private function rollback_language_transaction(bool $started): void
    {
        global $wpdb;

        if ($started) {
            $wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Transaction control statement contains no user input.
        }
    }

    /**
     * Keep language-owned data attached when an existing language code changes.
     *
     * @return true|WP_Error
     */
    private function migrate_language_code_references(string $previousCode, string $newCode)
    {
        global $wpdb;

        $translationsTable = Tables::sql_identifier(Tables::translations());
        $glossaryTable = Tables::sql_identifier(Tables::glossary());
        $migrationQueries = [
            $wpdb->prepare(
                "UPDATE `{$translationsTable}` SET language_code = %s WHERE language_code = %s",
                $newCode,
                $previousCode
            ),
            $wpdb->prepare(
                "UPDATE `{$glossaryTable}` SET language_code = %s WHERE language_code = %s",
                $newCode,
                $previousCode
            ),
        ];

        foreach ($migrationQueries as $query) {
            if ($wpdb->query($query) === false) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with plugin-owned table names.
                return new WP_Error(
                    'wat_language_code_migration_failed',
                    __('Taalcode-referenties bijwerken mislukt:', 'webactueel-translate-language-dropdowns') . ' ' .
                        ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.',
                    ['status' => 500]
                );
            }
        }

        return true;
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
            return new WP_Error(
                'wat_default_language_delete_forbidden',
                __('De standaardtaal kan niet worden verwijderd.', 'webactueel-translate-language-dropdowns'),
                ['status' => 400]
            );
        }

        $transactionStarted = $this->start_language_transaction();
        $deleted = $wpdb->delete(Tables::languages(), ['id' => $id, 'is_default' => 0]);
        if ($deleted === false) {
            return $this->abort_language_transaction($transactionStarted, new WP_Error(
                'wat_language_delete_failed',
                __('Taal verwijderen mislukt:', 'webactueel-translate-language-dropdowns') . ' ' .
                    ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.',
                ['status' => 500]
            ));
        }
        if ($deleted < 1) {
            return $this->abort_language_transaction($transactionStarted, new WP_Error(
                'wat_language_delete_unchanged',
                __('Taal is niet verwijderd.', 'webactueel-translate-language-dropdowns'),
                ['status' => 409]
            ));
        }

        $code = Input::key($language['code'] ?? '');
        if ($code !== '') {
            $translationDelete = $wpdb->delete(Tables::translations(), ['language_code' => $code]);
            $glossaryDelete = $wpdb->delete(Tables::glossary(), ['language_code' => $code]);
            if ($translationDelete === false || $glossaryDelete === false) {
                return $this->abort_language_transaction($transactionStarted, new WP_Error(
                    'wat_language_related_delete_failed',
                    __('Gekoppelde vertalingen verwijderen mislukt:', 'webactueel-translate-language-dropdowns') . ' ' .
                        ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.',
                    ['status' => 500]
                ));
            }
        }

        $commitResult = $this->commit_language_transaction($transactionStarted);
        if (is_wp_error($commitResult)) {
            return $commitResult;
        }

        LanguageDetector::reset_cache();
        CacheInvalidator::bump();
        do_action('wat_language_routes_changed');
        return ['deleted' => true, 'id' => $id];
    }
}
