<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport\Concerns;

use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

trait CsvUploadTempDirectory
{
    public static function temp_dir(): string
    {
        $default = trailingslashit(get_temp_dir()) . 'webactueel-translate-language-dropdowns';
        $filteredDir = apply_filters('wat_csv_temp_dir', $default);
        $dir = self::normalize_temp_dir(Input::scalar_string($filteredDir, $default), $default);

        return untrailingslashit($dir);
    }

    private static function normalize_temp_dir(string $dir, string $default): string
    {
        $default = trim(wp_normalize_path($default));
        $dir = trim(wp_normalize_path($dir));

        if ($dir === '' || strpos($dir, "\0") !== false || self::path_contains_traversal($dir) || ! self::path_is_absolute($dir)) {
            return $default;
        }

        if (! self::path_is_within_allowed_temp_base($dir)) {
            do_action('wat_log', 'warning', 'CSV temporary directory filter was ignored because it is outside the allowed base directories.');
            return $default;
        }

        return $dir;
    }

    private static function path_contains_traversal(string $path): bool
    {
        $parts = explode('/', str_replace('\\', '/', $path));
        return in_array('..', $parts, true);
    }

    private static function path_is_absolute(string $path): bool
    {
        return preg_match('#^(?:/|[A-Za-z]:/)#', $path) === 1;
    }

    private static function path_is_within_allowed_temp_base(string $path): bool
    {
        $path = untrailingslashit(wp_normalize_path($path));
        foreach (self::allowed_temp_bases() as $base) {
            $base = untrailingslashit(wp_normalize_path($base));
            if ($base === '') {
                continue;
            }
            if ($path === $base || strpos(trailingslashit($path), trailingslashit($base)) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function allowed_temp_bases(): array
    {
        $bases = [get_temp_dir()];
        $uploads = function_exists('wp_upload_dir') ? wp_upload_dir(null, false) : [];
        if (is_array($uploads) && ! empty($uploads['basedir']) && is_scalar($uploads['basedir'])) {
            $bases[] = (string) $uploads['basedir'];
        }

        $filtered = apply_filters('wat_csv_temp_dir_allowed_bases', $bases);
        if (! is_array($filtered)) {
            $filtered = $bases;
        }

        $allowed = [];
        foreach ($filtered as $base) {
            if (! is_scalar($base)) {
                continue;
            }
            $base = trim(wp_normalize_path((string) $base));
            if ($base === '' || strpos($base, "\0") !== false || self::path_contains_traversal($base) || ! self::path_is_absolute($base)) {
                continue;
            }
            $allowed[] = untrailingslashit($base);
        }

        return array_values(array_unique($allowed));
    }

    public static function is_preview_path(string $path): bool
    {
        $base = realpath(self::temp_dir());
        $file = realpath($path);
        if (! is_string($base) || ! is_string($file)) {
            return false;
        }

        $base = trailingslashit(wp_normalize_path($base));
        $file = wp_normalize_path($file);

        return strpos($file, $base) === 0;
    }

    private static function set_private_permissions(string $path, int $mode): void
    {
        if (! file_exists($path)) {
            return;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort hardening for plugin-owned temporary CSV files/directories.
        chmod($path, $mode);
    }

    private static function directory_is_writable(string $dir): bool
    {
        if (! function_exists('wp_is_writable')) {
            $file = defined('ABSPATH') ? ABSPATH . 'wp-admin/includes/file.php' : '';
            if ($file !== '' && file_exists($file)) {
                require_once $file;
            }
        }

        return function_exists('wp_is_writable') ? wp_is_writable($dir) : false;
    }

    private function protect_temp_dir(string $dir): void
    {
        $index = trailingslashit($dir) . 'index.html';
        if (! self::directory_is_writable($dir)) {
            return;
        }

        if (! file_exists($index)) {
            self::write_protection_file($index, '');
        }

        $htaccess = trailingslashit($dir) . '.htaccess';
        if (! file_exists($htaccess)) {
            self::write_protection_file($htaccess, "Options -Indexes\nRequire all denied\nDeny from all\n");
        }

        $webConfig = trailingslashit($dir) . 'web.config';
        if (! file_exists($webConfig)) {
            self::write_protection_file($webConfig, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><requestFiltering><hiddenSegments><add segment=\"webactueel-translate-language-dropdowns\" /></hiddenSegments></requestFiltering></security></system.webServer></configuration>\n");
        }
    }

    private static function write_protection_file(string $path, string $contents): void
    {
        if (file_exists($path)) {
            return;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Best-effort protection file in plugin-owned temporary directory.
        file_put_contents($path, $contents, LOCK_EX);
    }

    private function cleanup_temp_files(): void
    {
        $dir = self::temp_dir();
        if (! is_dir($dir)) {
            return;
        }

        $cutoff = time() - HOUR_IN_SECONDS;
        foreach ((array) glob(trailingslashit($dir) . '*.csv') as $file) {
            if (is_string($file) && is_file($file) && filemtime($file) !== false && filemtime($file) < $cutoff) {
                wp_delete_file($file);
            }
        }
    }
}
