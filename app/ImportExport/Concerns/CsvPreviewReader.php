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
            // translators: Placeholder values are replaced with runtime details such as a row number, language name or count.
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
                // translators: Placeholder values are replaced with runtime details such as a row number, language name or count.
                $warnings[] = sprintf(__('Preview is beperkt tot %d regels.', 'webactueel-translate-language-dropdowns'), $limit);
                break;
            }
            if (count($row) !== count($header)) {
                // translators: Placeholder values are replaced with runtime details such as a row number, language name or count.
                $errors[] = sprintf(__('Regel %d: kolommen komen niet overeen met header.', 'webactueel-translate-language-dropdowns'), $total + 1);
                continue;
            }

            $data = array_combine($header, $row);
            $rowErrors = $this->validate_row($data, $total + 1, $seen);
            $safe = $data;
            foreach ($safe as $k => $v) {
                if (! is_string($v)) {
                    continue;
                }
                $safe[$k] = in_array((string) $k, ['original_text', 'translated_text'], true) ? wp_kses_post($v) : sanitize_text_field($v);
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
