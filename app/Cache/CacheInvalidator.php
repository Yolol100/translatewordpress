<?php

declare(strict_types=1);

namespace Webactueel\Translate\Cache;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: custom prefixed tables and public wat_* hooks are intentional.

final class CacheInvalidator
{
    public static function bump(): string
    {
        $current = (int) get_option('wat_cache_version', '0');
        $next = max($current + 1, time());
        $version = (string) $next;

        update_option('wat_cache_version', $version, false);
        do_action('wat_cache_cleared', $version);
        return $version;
    }
}
