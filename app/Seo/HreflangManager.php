<?php

declare(strict_types=1);

namespace Webactueel\Translate\Seo;

use Webactueel\Translate\Compatibility\CompatibilityRegistry;
use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\LanguageSwitcher;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
// Reviewed: custom prefixed tables and public wat_* hooks are intentional.

final class HreflangManager
{
    public static function enabled(): bool
    {
        $settings = Settings::all();
        if (empty($settings['hreflang_enabled'])) {
            return false;
        }
        if (CompatibilityRegistry::has_multilingual_conflict() && empty($settings['hreflang_force'])) {
            return false;
        }
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || is_feed() || is_robots()) {
            return false;
        }
        if (function_exists('is_checkout') && (is_checkout() || is_cart() || is_account_page())) {
            return false;
        }
        return (bool) apply_filters('wat_hreflang_enabled', true);
    }

    public static function tags(): array
    {
        if (! self::enabled()) {
            return [];
        }
        $tags = [];
        $seen = [];
        foreach (LanguageDetector::active_languages() as $language) {
            $code = Input::key($language['code'] ?? '');
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $href = LanguageSwitcher::url_for($code);
            if ($href === '') {
                continue;
            }
            $tags[] = ['hreflang' => $code, 'href' => $href];
            $seen[$code] = true;
        }
        if (! empty(Settings::all()['x_default_enabled'])) {
            $defaultHref = LanguageSwitcher::url_for(LanguageDetector::default_language());
            if ($defaultHref !== '') {
                $tags[] = ['hreflang' => 'x-default', 'href' => $defaultHref];
            }
        }
        return array_values((array) apply_filters('wat_hreflang_tags', $tags));
    }
}
