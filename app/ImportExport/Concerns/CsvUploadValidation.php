<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport\Concerns;

use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

trait CsvUploadValidation
{
    /**
     * @param array<string, mixed> $file Upload array from $_FILES.
     */
    public function validate_upload(array $file): string
    {
        $uploadError = Input::absint($file['error'] ?? UPLOAD_ERR_OK);
        if ($uploadError !== UPLOAD_ERR_OK) {
            return self::upload_error_message($uploadError);
        }

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

    /**
     * Validate CSV uploads using WordPress extension checks plus MIME sniffing.
     *
     * CSV files are often reported as text/plain or Excel MIME types by browsers, so the
     * allow-list is intentionally broader than a single `text/csv` value.
     */
    private function has_allowed_csv_type(string $tmpPath, string $name): bool
    {
        $allowedMimes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
            'text/comma-separated-values',
            'text/x-csv',
            'application/x-csv',
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

    private static function upload_error_message(int $error): string
    {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('CSV bestand is groter dan de toegestane uploadlimiet.', 'webactueel-translate-language-dropdowns');
            case UPLOAD_ERR_PARTIAL:
                return __('CSV upload is afgebroken. Probeer het bestand opnieuw te uploaden.', 'webactueel-translate-language-dropdowns');
            case UPLOAD_ERR_NO_FILE:
                return __('Geen CSV bestand ontvangen.', 'webactueel-translate-language-dropdowns');
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                return __('CSV upload kon niet veilig worden verwerkt door de server.', 'webactueel-translate-language-dropdowns');
            default:
                return __('CSV upload is mislukt.', 'webactueel-translate-language-dropdowns');
        }
    }
}
