<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport\Concerns;

use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

trait CsvUploadStorage
{
    /**
     * @param array<string, mixed> $file Upload array from $_FILES.
     */
    public function validate_upload(array $file): string
    {
        $tmpName = $file['tmp_name'] ?? '';
        if (! is_scalar($tmpName) || $tmpName === '' || ! is_uploaded_file((string) $tmpName)) {
            return __('Geen geldig uploadbestand.', 'webactueel-translate-language-dropdowns');
        }
        $name = sanitize_file_name(Input::scalar_string($file['name'] ?? ''));
        if ($name && strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'csv') {
            return __('Upload een .csv bestand.', 'webactueel-translate-language-dropdowns');
        }
        $size = Input::absint($file['size'] ?? 0);
        if ($size > 10 * MB_IN_BYTES) {
            return __('CSV bestand is groter dan 10 MB.', 'webactueel-translate-language-dropdowns');
        }
        if (! $this->has_allowed_csv_type((string) $tmpName, $name)) {
            return __('Upload een geldig CSV- of tekstbestand.', 'webactueel-translate-language-dropdowns');
        }
        return '';
    }

    private function has_allowed_csv_type(string $tmpPath, string $name): bool
    {
        $allowedMimes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
            'text/comma-separated-values',
        ];

        if (function_exists('wp_check_filetype_and_ext') && $name !== '') {
            $checked = wp_check_filetype_and_ext($tmpPath, $name, ['csv' => 'text/csv']);
            if (! empty($checked['ext']) && $checked['ext'] === 'csv' && (empty($checked['type']) || in_array((string) $checked['type'], $allowedMimes, true))) {
                return true;
            }
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string) finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
                if ($mime !== '' && ! in_array($mime, $allowedMimes, true)) {
                    return false;
                }
            }
        }

        return is_readable($tmpPath);
    }

    public static function temp_dir(): string
    {
        $default = trailingslashit(get_temp_dir()) . 'webactueel-translate-language-dropdowns';
        $filteredDir = apply_filters('wat_csv_temp_dir', $default);
        $dir = Input::scalar_string($filteredDir, $default);
        $dir = trim(wp_normalize_path($dir));

        if ($dir === '' || strpos($dir, "\0") !== false) {
            $dir = wp_normalize_path($default);
        }

        return untrailingslashit($dir);
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

    private function copy_to_temp(string $source, string $name): string
    {
        $dir = self::temp_dir();
        if (! wp_mkdir_p($dir)) {
            return '';
        }

        $realDir = realpath($dir);
        if (! is_string($realDir)) {
            return '';
        }
        $dir = wp_normalize_path($realDir);

        if (! self::directory_is_writable($dir)) {
            return '';
        }

        $this->protect_temp_dir($dir);
        self::set_private_permissions($dir, 0700);

        $filename = sanitize_file_name($name ?: 'import.csv');
        if ($filename === '' || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'csv') {
            $filename = 'import.csv';
        }

        $target = trailingslashit($dir) . time() . '-' . wp_generate_password(16, false, false) . '-' . $filename;
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Required to securely move a verified PHP upload into the plugin-owned temporary preview directory.
        if (! is_readable($source) || ! move_uploaded_file($source, $target)) {
            return '';
        }

        self::set_private_permissions($target, 0600);
        if (! self::is_preview_path($target)) {
            wp_delete_file($target);
            return '';
        }

        return $target;
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
