<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Routing;

if (! defined('ABSPATH')) {
    exit;
}

final class LanguagePathExclusions
{
    public static function is_excluded_request_path(string $patterns = ''): bool
    {
        $uri = LanguageUrlBuilder::request_uri();
        $blocked = ['/wp-admin/', '/wp-login.php', '/wp-json/', '/xmlrpc.php', '/wp-cron.php', '/wp-comments-post.php', '/wc-api/', 'wc-ajax=', 'elementor-preview=', 'preview=true', 'customize.php'];
        foreach ($blocked as $part) {
            if (stripos($uri, $part) !== false) {
                return true;
            }
        }

        $patternsList = preg_split('/\r\n|\r|\n/', $patterns) ?: [];
        $patternsList = apply_filters('wat_excluded_paths', $patternsList);
        $patternsList = is_array($patternsList) ? $patternsList : [];
        foreach ($patternsList as $pattern) {
            if (! is_scalar($pattern)) {
                continue;
            }
            $pattern = trim((string) $pattern);
            if ($pattern !== '' && stripos($uri, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
