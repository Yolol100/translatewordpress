<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend;

use Webactueel\Translate\Frontend\Concerns\DetectsLanguage;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}


final class LanguageDetector
{
    use DetectsLanguage;

    private static ?string $defaultLanguageCache = null;
    private static ?array $activeLanguagesCache = null;
    private static array $languageExistsCache = [];

    public static function reset_cache(): void
    {
        self::$defaultLanguageCache = null;
        self::$activeLanguagesCache = null;
        self::$languageExistsCache = [];
    }

    public static function current_language(): string
    {
        $settings = Settings::all();
        $requested = self::requested_language();
        if ($requested !== '') {
            if (self::should_remember_language($settings)) {
                self::set_cookie($requested);
            }
            return self::filtered_language($requested);
        }

        $domainLanguage = LanguageDomainMapper::language_for_current_host();
        if ($domainLanguage !== '') {
            if (self::should_remember_language($settings)) {
                self::set_cookie($domainLanguage);
            }
            return self::filtered_language($domainLanguage);
        }

        // Prefix-free public URLs are the default language. This prevents a stale
        // English cookie from forcing English after the visitor switched back to
        // Nederlands. Non-default languages use their virtual prefix, e.g. /en/page/.
        if (! is_admin() && ! wp_doing_ajax() && ! (defined('REST_REQUEST') && REST_REQUEST)) {
            $pathLanguage = LanguageRouter::language_from_path();
            if ($pathLanguage === '') {
                return self::filtered_language(self::default_language());
            }
        }

        $cookie = Input::cookie_key('wat_language');
        if (self::should_remember_language($settings) && $cookie !== '' && self::language_exists($cookie)) {
            return self::filtered_language($cookie);
        }

        if (! empty($settings['browser_redirect'])) {
            $browser = self::browser_language();
            if ($browser !== '' && self::language_exists($browser)) {
                return self::filtered_language($browser);
            }
        }

        return self::default_language();
    }
}
