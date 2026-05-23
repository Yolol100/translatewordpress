<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Routing;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\LanguageDomainMapper;
use Webactueel\Translate\Frontend\UrlMapping;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

trait RequestState
{
    public static function capture_request($wp): void
    {
        if (! is_object($wp) || ! isset($wp->query_vars) || ! is_array($wp->query_vars)) {
            return;
        }
        $language = self::language_from_query_vars($wp->query_vars);
        if ($language === '') {
            $language = self::language_from_path();
        }
        if ($language === '') {
            return;
        }
        self::$requestLanguage = $language;
        self::$requestPath = isset($wp->query_vars['wat_path']) ? trim(Input::text($wp->query_vars['wat_path']), '/') : self::strip_language_prefix(self::request_path());
    }

    public static function current_request_language(): string
    {
        if (self::$requestLanguage !== '' && LanguageDetector::language_exists(self::$requestLanguage)) {
            return self::$requestLanguage;
        }
        return self::language_from_path();
    }

    public static function current_base_path(): string
    {
        if (self::$requestPath !== '') {
            return self::$requestPath;
        }
        return self::strip_language_prefix(self::request_path());
    }

    public static function request_uri(): string
    {
        $uri = Input::server_text('REQUEST_URI', '/');
        return $uri !== '' ? $uri : '/';
    }

    public static function request_path(): string
    {
        $path = wp_parse_url(self::request_uri(), PHP_URL_PATH);
        return is_string($path) ? $path : '/';
    }

    public static function body_class(array $classes): array
    {
        $language = LanguageDetector::current_language();
        if ($language !== '') {
            $classes[] = 'wat-lang-' . sanitize_html_class($language);
        }
        return array_values(array_unique($classes));
    }
}
