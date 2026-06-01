<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

if (! defined('ABSPATH')) {
    exit;
}

final class Formatting
{
    /**
     * Guard a CSV cell against spreadsheet formula injection.
     */
    public static function csv_cell(string $value): string
    {
        $trimmed = ltrim($value, " \t\r\n");
        if ($trimmed !== '' && preg_match('/^[=+\-@]/', $trimmed)) {
            return "'" . $value;
        }
        if ($value !== '' && preg_match('/^[\t\r\n]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    public static function base_url(string $url): string
    {
        $url = esc_url_raw(trim($url), ['http', 'https']);
        if ($url === '') {
            return '';
        }

        $parts = wp_parse_url($url);
        if (! is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return '';
        }

        $port = isset($parts['port']) ? ':' . absint($parts['port']) : '';
        $path = isset($parts['path']) ? '/' . trim((string) $parts['path'], '/') : '';

        return rtrim($scheme . '://' . $host . $port . $path, '/');
    }
}
