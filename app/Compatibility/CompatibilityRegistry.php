<?php

declare(strict_types=1);

namespace Webactueel\Translate\Compatibility;

use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Compatibility\Concerns\DetectsCompatibilityPlugins;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: custom prefixed tables and public wat_* hooks are intentional.

final class CompatibilityRegistry
{
    use DetectsCompatibilityPlugins;
    private const MULTILINGUAL = ['WPML', 'Polylang', 'TranslatePress', 'Weglot', 'GTranslate', 'MultilingualPress'];

    public static function detected(): array
    {
        $checks = self::plugin_checks();

        $out = [];
        foreach ($checks as $name => $active) {
            if (! $active) {
                continue;
            }
            $type = in_array($name, self::MULTILINGUAL, true) ? 'multilingual' : self::type_for($name);
            $out[] = [
                'name' => $name,
                'type' => $type,
                'status' => self::status_for($name, $type),
                'active' => true,
                'risk' => self::risk_for($type),
                'recommendation' => self::recommendation_for($type),
            ];
        }
        $filtered = apply_filters('wat_compatibility_plugins', $out);
        if (! is_array($filtered)) {
            return $out;
        }

        $clean = [];
        foreach ($filtered as $item) {
            if (is_array($item)) {
                $clean[] = $item;
            }
        }
        return $clean;
    }

    public static function has_multilingual_conflict(): bool
    {
        foreach (self::detected() as $plugin) {
            if (($plugin['type'] ?? '') === 'multilingual') {
                return true;
            }
        }
        return false;
    }

    public static function should_disable_frontend_replacement(): bool
    {
        $settings = Settings::all();
        return self::has_multilingual_conflict() && empty($settings['compatibility_override']);
    }
}
