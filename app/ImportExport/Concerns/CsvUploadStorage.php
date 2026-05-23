<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport\Concerns;

if (! defined('ABSPATH')) {
    exit;
}

trait CsvUploadStorage
{
    use CsvUploadTempDirectory;
    use CsvUploadValidation;

    /**
     * Move a verified browser upload into the private CSV preview directory.
     *
     * The generated target path is revalidated after the move so later preview/import
     * steps only read files owned by this plugin workflow.
     */
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
}
