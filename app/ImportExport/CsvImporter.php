<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport;

use Webactueel\Translate\ImportExport\CsvPreviewer;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\ImportExport\Concerns\ImportsCsvFiles;

if (! defined('ABSPATH')) {
    exit;
}


final class CsvImporter
{
    use ImportsCsvFiles;
    public function import_token(string $token, array $languages = []): array
    {
        $token = preg_replace('/[^a-zA-Z0-9]/', '', $token);
        if ($token === '') {
            return ['imported' => 0, 'errors' => [__('CSV import vereist een geldige preview token.', 'webactueel-translate-language-dropdowns')]];
        }
        $meta = get_transient('wat_csv_preview_' . $token);
        if (! is_array($meta)) {
            return ['imported' => 0, 'errors' => [__('CSV preview is verlopen. Maak opnieuw een preview.', 'webactueel-translate-language-dropdowns')]];
        }
        $path = Input::scalar_string($meta['path'] ?? '');
        if ($path === '' || ! is_readable($path) || ! CsvPreviewer::is_preview_path($path)) {
            return ['imported' => 0, 'errors' => [__('CSV preview is verlopen. Maak opnieuw een preview.', 'webactueel-translate-language-dropdowns')]];
        }

        $previewUserId = Input::absint($meta['user_id'] ?? 0);
        if ($previewUserId > 0 && get_current_user_id() !== $previewUserId) {
            return ['imported' => 0, 'errors' => [__('CSV preview hoort bij een andere beheerderssessie. Maak opnieuw een preview.', 'webactueel-translate-language-dropdowns')]];
        }
        $delimiter = Input::scalar_string($meta['delimiter'] ?? ',', ',');
        $result = $this->import_file($path, $delimiter, $languages);
        wp_delete_file($path);
        delete_transient('wat_csv_preview_' . $token);
        return $result;
    }
}
