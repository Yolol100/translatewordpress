<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Cache\TranslationCache;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Support\Input;
use WP_Error;
use WP_REST_Request;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Authenticated REST endpoints use plugin-owned custom tables.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

trait LanguageEndpoints
{
    use LanguageEndpointTransactions;

    public function languages(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i ORDER BY is_default DESC, native_name ASC', Tables::languages()),
            ARRAY_A
        ) ?: [];
    }

    public function save_language(WP_REST_Request $request)
    {
        global $wpdb;

        $languages_table = Tables::languages();
        $id = absint($request['id'] ?? 0);
        $now = current_time('mysql');
        $data = $this->prepare_language_payload($request->get_params(), $now);
        if (is_wp_error($data)) {
            return $data;
        }

        $context = $this->get_language_save_context($id, $languages_table, $data['code']);
        if (is_wp_error($context)) {
            return $context;
        }

        $makeDefault = ! empty($data['is_default']) || ! empty($context['currently_default']);
        if ($makeDefault) {
            // Save the language first, then switch the default flag. This avoids
            // temporarily leaving the site without a default language if insert/update fails.
            $data['is_active'] = 1;
            $data['is_default'] = 0;
        }

        $transactionStarted = $this->start_language_transaction();
        $savedId = $this->persist_language($id, $data, $now, $transactionStarted);
        if (is_wp_error($savedId)) {
            return $savedId;
        }
        $id = $savedId;

        if ($makeDefault) {
            $defaultResult = $this->promote_default_language($id, $languages_table, $now, $transactionStarted);
            if (is_wp_error($defaultResult)) {
                return $defaultResult;
            }
        }

        $previousCode = Input::key($context['previous_code'] ?? '');
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

        $this->finalize_language_change();
        $saved = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d LIMIT 1", $languages_table, $id), ARRAY_A);
        return ['id' => $id, 'saved' => true, 'language' => $saved ?: $data];
    }



    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|WP_Error
     */
    private function prepare_language_payload(array $params, string $now)
    {
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

        return $data;
    }

    /**
     * @return array{previous_code: string, currently_default: bool}|WP_Error
     */
    private function get_language_save_context(int $id, string $languages_table, string $code)
    {
        global $wpdb;

        $existingId = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE code = %s LIMIT 1', $languages_table, $code));
        if ($existingId && $existingId !== $id) {
            return new WP_Error('wat_language_exists', __('Deze taalcode bestaat al.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        if (! $id) {
            return ['previous_code' => '', 'currently_default' => false];
        }

        $currentRow = $wpdb->get_row($wpdb->prepare('SELECT id, code, is_default FROM %i WHERE id = %d LIMIT 1', $languages_table, $id), ARRAY_A);
        if (! is_array($currentRow)) {
            return new WP_Error('wat_language_not_found', __('Taal niet gevonden.', 'webactueel-translate-language-dropdowns'), ['status' => 404]);
        }

        return [
            'previous_code' => Input::key($currentRow['code'] ?? ''),
            'currently_default' => ! empty($currentRow['is_default']),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return int|WP_Error
     */
    private function persist_language(int $id, array $data, string $now, bool $transactionStarted)
    {
        global $wpdb;

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

            return $id;
        }

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

        return (int) $wpdb->insert_id;
    }

    /**
     * @return true|WP_Error
     */
    private function promote_default_language(int $id, string $languages_table, string $now, bool $transactionStarted)
    {
        global $wpdb;

        $defaultResult = $wpdb->update(Tables::languages(), ['is_default' => 1, 'is_active' => 1, 'updated_at' => $now], ['id' => $id]);
        if ($defaultResult === false) {
            return $this->abort_language_transaction($transactionStarted, new WP_Error(
                'wat_language_default_failed',
                __('Standaardtaal instellen mislukt:', 'webactueel-translate-language-dropdowns') . ' ' .
                    ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.',
                ['status' => 500]
            ));
        }

        $cleanupResult = $wpdb->query(
            $wpdb->prepare(
                'UPDATE %i SET is_default = 0 WHERE id <> %d AND is_default = 1',
                $languages_table,
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

        return true;
    }

    public function delete_language(WP_REST_Request $request)
    {
        $id = absint($request['id']);
        $language = $this->load_language_for_delete($id);
        if (is_wp_error($language)) {
            return $language;
        }

        $transactionStarted = $this->start_language_transaction();
        $deleteResult = $this->delete_language_row($id, $transactionStarted);
        if (is_wp_error($deleteResult)) {
            return $deleteResult;
        }

        $relatedDelete = $this->delete_language_related_data(Input::key($language['code'] ?? ''), $transactionStarted);
        if (is_wp_error($relatedDelete)) {
            return $relatedDelete;
        }

        $commitResult = $this->commit_language_transaction($transactionStarted);
        if (is_wp_error($commitResult)) {
            return $commitResult;
        }

        $this->finalize_language_change();
        return ['deleted' => true, 'id' => $id];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function load_language_for_delete(int $id)
    {
        global $wpdb;

        $language = $wpdb->get_row($wpdb->prepare("SELECT id, code, is_default FROM %i WHERE id = %d LIMIT 1", Tables::languages(), $id), ARRAY_A);
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

        return $language;
    }

    /**
     * @return true|WP_Error
     */
    private function delete_language_row(int $id, bool $transactionStarted)
    {
        global $wpdb;

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

        return true;
    }

    /**
     * @return true|WP_Error
     */
    private function delete_language_related_data(string $code, bool $transactionStarted)
    {
        global $wpdb;

        if ($code === '') {
            return true;
        }

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

        return true;
    }

    private function finalize_language_change(): void
    {
        LanguageDetector::reset_cache();
        TranslationCache::bump();
        do_action('wat_language_routes_changed');
    }
}
