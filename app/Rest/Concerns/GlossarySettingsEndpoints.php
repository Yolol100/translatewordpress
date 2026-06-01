<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Cache\TranslationCache;
use Webactueel\Translate\Compatibility\CompatibilityRegistry;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Logger;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Translation\GlossaryRepository;
use WP_Error;
use WP_REST_Request;

if (! defined('ABSPATH')) {
    exit;
}

trait GlossarySettingsEndpoints
{
    public function glossary(WP_REST_Request $request): array
    {
        $language = Input::key($request->get_param('language') ?: '');
        return (new GlossaryRepository())->all($language);
    }

    public function save_glossary(WP_REST_Request $request)
    {
        $params = $request->get_params();
        $language = Input::key($params['language_code'] ?? '');
        if (! $this->is_translatable_language($language)) {
            return new WP_Error('wat_invalid_glossary_language', __('Kies een actieve niet-standaardtaal voor woordenlijst-items.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }
        $result = (new GlossaryRepository())->save($params);
        if (! empty($result['error'])) {
            return new WP_Error('wat_glossary_save_failed', Input::scalar_string($result['error'], __('Woordenlijst opslaan mislukt.', 'webactueel-translate-language-dropdowns')), ['status' => 400]);
        }
        return $result;
    }

    public function delete_glossary(WP_REST_Request $request): array
    {
        $deleted = (new GlossaryRepository())->delete(absint($request['id']));
        if (! $deleted) {
            return ['deleted' => false, 'id' => absint($request['id'])];
        }
        return ['deleted' => true, 'id' => absint($request['id'])];
    }

    public function settings(): array
    {
        return Settings::all();
    }

    public function save_settings(WP_REST_Request $request): array
    {
        $params = $request->get_params();
        return Settings::update($params);
    }

    public function compatibility(): array
    {
        return [
            'plugins' => CompatibilityRegistry::detected(),
            'multilingualConflict' => CompatibilityRegistry::has_multilingual_conflict(),
            'frontendLimited' => CompatibilityRegistry::should_disable_frontend_replacement(),
        ];
    }

    public function cache_clear(): array
    {
        return ['cacheVersion' => TranslationCache::bump(), 'cleared' => true];
    }

    public function preferences(): array
    {
        $prefs = get_user_meta(get_current_user_id(), 'wat_admin_preferences', true);
        return is_array($prefs) ? $prefs : [];
    }

    public function save_preferences(WP_REST_Request $request): array
    {
        $params = $request->get_params();
        $allowed = ['languages', 'scan', 'translate', 'switcher'];
        $order = [];
        if (isset($params['dashboard_order']) && is_array($params['dashboard_order'])) {
            $requested = array_map('sanitize_key', $params['dashboard_order']);
            foreach ($requested as $key) {
                if (in_array($key, $allowed, true) && ! in_array($key, $order, true)) {
                    $order[] = $key;
                }
            }
        }
        if (empty($order)) {
            $order = $allowed;
        }
        $prefs = ['dashboard_order' => $order];
        update_user_meta(get_current_user_id(), 'wat_admin_preferences', $prefs);
        return $prefs;
    }

    public function logs(): array
    {
        return Logger::latest(100);
    }

    public function clear_logs(): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin log cleanup targets one plugin-owned custom table.
        $result = $wpdb->query(
            $wpdb->prepare('DELETE FROM %i', Tables::logs())
        );
        return ['cleared' => $result !== false];
    }
}
