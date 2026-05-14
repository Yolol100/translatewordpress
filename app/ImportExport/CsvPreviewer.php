<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport;

use Webactueel\Translate\ImportExport\Concerns\CsvPreviewReader;
use Webactueel\Translate\ImportExport\Concerns\CsvPreviewValidation;
use Webactueel\Translate\ImportExport\Concerns\CsvUploadStorage;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: custom prefixed tables, native CSV streams and public wat_* hooks are intentional.

final class CsvPreviewer
{
    use CsvPreviewReader;
    use CsvPreviewValidation;
    use CsvUploadStorage;

    /**
     * @var array<int, string>
     */
    private array $required = ['hash', 'source_type', 'source_id', 'context', 'original_text', 'language_code', 'translated_text', 'status'];

    /**
     * Validate, copy and preview an uploaded CSV file.
     *
     * @param array<string, mixed> $file Upload array from $_FILES.
     * @return array<string, mixed>
     */
    public function preview_uploaded(array $file, int $limit = 250): array
    {
        $error = $this->validate_upload($file);
        if ($error) {
            return ['valid' => false, 'errors' => [$error], 'warnings' => [], 'rows' => [], 'stats' => ['total' => 0, 'valid' => 0]];
        }

        $this->cleanup_temp_files();
        $tmpPath = $this->copy_to_temp(Input::scalar_string($file['tmp_name'] ?? ''), Input::scalar_string($file['name'] ?? 'import.csv', 'import.csv'));
        if ($tmpPath === '') {
            return ['valid' => false, 'errors' => [__('CSV kon niet veilig tijdelijk worden opgeslagen.', 'webactueel-translate-language-dropdowns')], 'warnings' => [], 'rows' => [], 'stats' => ['total' => 0, 'valid' => 0]];
        }

        $preview = $this->preview_file($tmpPath, $limit);
        if (empty($preview['valid'])) {
            wp_delete_file($tmpPath);
            return $preview;
        }

        $token = wp_generate_password(32, false, false);
        set_transient('wat_csv_preview_' . $token, [
            'path' => $tmpPath,
            'delimiter' => (string) ($preview['delimiter'] ?? ','),
            'created_at' => time(),
            'user_id' => get_current_user_id(),
        ], HOUR_IN_SECONDS);

        $preview['preview_token'] = $token;
        return $preview;
    }
}
