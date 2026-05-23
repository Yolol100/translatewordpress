<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Concerns;

use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Frontend\LanguageDomainMapper;
use Webactueel\Translate\Frontend\LanguageRouter;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This plugin uses its own custom translation tables; queries are scoped and cache invalidation is handled by the plugin.

trait DetectsLanguage
{
    use CookieHelpers;
    public static function default_language(): string
    {
        if (self::$defaultLanguageCache !== null) {
            return self::$defaultLanguageCache;
        }

        global $wpdb;
        $languages_table = Tables::sql_identifier(Tables::languages());
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
        $code = (string) $wpdb->get_var("SELECT code FROM `{$languages_table}` WHERE is_default = 1 LIMIT 1");
        self::$defaultLanguageCache = $code ?: strtolower(substr(get_locale() ?: 'nl_NL', 0, 2));
        return self::$defaultLanguageCache;
    }

    public static function is_default_language(string $code): bool
    {
        return sanitize_key($code) === self::default_language();
    }

    public static function language_exists(string $code): bool
    {
        global $wpdb;
        $code = sanitize_key($code);
        if ($code === '') {
            return false;
        }
        if (array_key_exists($code, self::$languageExistsCache)) {
            return self::$languageExistsCache[$code];
        }
        $languages_table = Tables::sql_identifier(Tables::languages());
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
        self::$languageExistsCache[$code] = (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$languages_table}` WHERE code = %s AND is_active = 1", $code));
        return self::$languageExistsCache[$code];
    }

    public static function active_languages(): array
    {
        if (self::$activeLanguagesCache !== null) {
            return self::$activeLanguagesCache;
        }

        global $wpdb;
        $languages_table = Tables::sql_identifier(Tables::languages());
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name only.
        self::$activeLanguagesCache = $wpdb->get_results("SELECT * FROM `{$languages_table}` WHERE is_active = 1 ORDER BY is_default DESC, native_name ASC", ARRAY_A) ?: [];
        return self::$activeLanguagesCache;
    }

    private static function requested_language(): string
    {
        foreach (['wat_switch_lang', 'wat_lang'] as $key) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public language selection does not modify site data.
            $query = Input::get_key($key);
            if ($query !== '' && self::language_exists($query)) {
                return $query;
            }
        }

        $routed = LanguageRouter::current_request_language();
        if ($routed !== '' && self::language_exists($routed)) {
            return $routed;
        }

        $pathLanguage = LanguageRouter::language_from_path();
        if ($pathLanguage !== '' && self::language_exists($pathLanguage)) {
            return $pathLanguage;
        }

        return '';
    }

    private static function browser_language(): string
    {
        $header = Input::server_text('HTTP_ACCEPT_LANGUAGE');
        if ($header === '') {
            return '';
        }

        foreach (explode(',', $header) as $part) {
            $code = sanitize_key(strtolower(substr(trim($part), 0, 2)));
            if ($code !== '') {
                return $code;
            }
        }

        return '';
    }

    private static function filtered_language(string $code): string
    {
        $filtered = apply_filters('wat_detected_language', sanitize_key($code));
        $filtered = is_scalar($filtered) ? sanitize_key((string) $filtered) : sanitize_key($code);
        return $filtered !== '' && self::language_exists($filtered) ? $filtered : sanitize_key($code);
    }

    private static function should_remember_language(array $settings): bool
    {
        return ! empty($settings['remember_language']);
    }

    private static function set_cookie(string $code): void
    {
        if (! headers_sent()) {
            setcookie('wat_language', sanitize_key($code), self::cookie_options(time() + MONTH_IN_SECONDS));
        }
    }
}
