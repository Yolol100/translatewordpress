<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport\Concerns;

if (! defined('ABSPATH')) {
    exit;
}

trait CsvPreviewReader
{
    /**
     * @return array<string, mixed>
     */
    public function preview_file(string $path, int $limit = 250): array
    {
        $delimiter = $this->detect_delimiter($path);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV stream handling needs native PHP streams.
        $handle = fopen($path, 'r');
        if (! $handle) {
            return ['valid' => false, 'errors' => [__('CSV kon niet geopend worden.', 'webactueel-translate-language-dropdowns')], 'warnings' => [], 'rows' => [], 'stats' => ['total' => 0, 'valid' => 0]];
        }
        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        if (! is_array($header)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing native CSV stream.
            fclose($handle);
            return ['valid' => false, 'errors' => [__('CSV header ontbreekt.', 'webactueel-translate-language-dropdowns')], 'warnings' => [], 'rows' => [], 'stats' => ['total' => 0, 'valid' => 0]];
        }
        $header = array_map(static function ($h): string {
            $h = trim((string) $h);
            return preg_replace('/^\xEF\xBB\xBF/', '', $h) ?: $h;
        }, $header);
        $missing = array_diff($this->required, $header);
        if ($missing) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing native CSV stream.
            fclose($handle);
            return ['valid' => false, 'errors' => [sprintf(__('CSV header mist: %s', 'webactueel-translate-language-dropdowns'), implode(', ', $missing))], 'warnings' => [], 'rows' => [], 'stats' => ['total' => 0, 'valid' => 0]];
        }
        $errors = [];
        $warnings = [];
        $rows = [];
        $total = 0;
        $valid = 0;
        $seen = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $total++;
            if ($total > $limit) {
                $warnings[] = 'Preview is beperkt tot ' . $limit . ' regels.';
                break;
            }
            if (count($row) !== count($header)) {
                $errors[] = 'Regel ' . ($total + 1) . ': kolommen komen niet overeen met header.';
                continue;
            }

            $data = array_combine($header, $row);
            if (! is_array($data)) {
                $errors[] = 'Regel ' . ($total + 1) . ': kolommen komen niet overeen met header.';
                continue;
            }
            $rowErrors = $this->validate_row($data, $total + 1, $seen);
            $safe = $data;
            foreach ($safe as $k => $v) {
                $safe[$k] = is_string($v) ? sanitize_text_field($v) : $v;
            }
            $safe['_errors'] = $rowErrors;
            $rows[] = $safe;
            if ($rowErrors) {
                $errors = array_merge($errors, $rowErrors);
            } else {
                $valid++;
            }
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing native CSV stream.
        fclose($handle);
        return [
            'valid' => empty($errors),
            'errors' => array_slice($errors, 0, 50),
            'warnings' => array_values(array_unique($warnings)),
            'rows' => $rows,
            'delimiter' => $delimiter,
            'stats' => ['total' => $total, 'valid' => $valid, 'invalid' => max(0, $total - $valid)],
        ];
    }

    private function detect_delimiter(string $path): string
    {
        if (! is_readable($path)) {
            return ',';
        }

        $line = file_get_contents($path, false, null, 0, 4096); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Small local CSV sniff only.
        $line = is_string($line) ? $line : '';
        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }
}
