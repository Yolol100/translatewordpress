<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Concerns;

if (! defined('ABSPATH')) {
    exit;
}

trait CookieHelpers
{
    /**
     * Build setcookie() options without passing an empty/false domain value.
     *
     * @return array<string, mixed>
     */
    private static function cookie_options(int $expires): array
    {
        $options = [
            'expires' => $expires,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        if (defined('COOKIE_DOMAIN') && is_string(COOKIE_DOMAIN) && COOKIE_DOMAIN !== '') {
            $options['domain'] = COOKIE_DOMAIN;
        }

        return $options;
    }

    private static function clear_language_cookie(): void
    {
        if (! headers_sent()) {
            setcookie('wat_language', '', self::cookie_options(time() - HOUR_IN_SECONDS));
        }

        unset($_COOKIE['wat_language']);
    }
}
