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
            return $this->csv_preview_error(__('CSV kon niet geopend worden.', 'webactueel-translate-language-dropdowns'));
        }

        $header = $this->read_preview_header($handle, $delimiter);
        if ($header === []) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing native CSV stream.
            fclose($handle);
            return $this->csv_preview_error(__('CSV header ontbreekt.', 'webactueel-translate-language-dropdowns'));
        }

        $duplicates = array_unique(array_diff_assoc($header, array_unique($header)));
        if ($duplicates) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing native CSV stream.
            fclose($handle);
            return $this->csv_preview_error(sprintf(
                /* translators: %s: comma-separated duplicate CSV column names. */
                __('CSV header bevat dubbele kolommen: %s', 'webactueel-translate-language-dropdowns'),
                implode(', ', $duplicates)
            ));
        }

        $missing = array_diff($this->required, $header);
        if ($missing) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing native CSV stream.
            fclose($handle);
            return $this->csv_preview_error(sprintf(
                /* translators: %s: comma-separated missing CSV column names. */
                __('CSV header mist: %s', 'webactueel-translate-language-dropdowns'),
                implode(', ', $missing)
            ));
        }

        $preview = $this->read_preview_rows($handle, $header, $delimiter, $limit);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing native CSV stream.
        fclose($handle);

        return [
            'valid' => empty($preview['errors']),
            'errors' => array_slice($preview['errors'], 0, 50),
            'warnings' => array_values(array_unique($preview['warnings'])),
            'rows' => $preview['rows'],
            'delimiter' => $delimiter,
            'stats' => [
                'total' => $preview['total'],
                'valid' => $preview['valid'],
                'invalid' => max(0, $preview['total'] - $preview['valid']),
            ],
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

    /** @return array<int, string> */
    private function read_preview_header($handle, string $delimiter): array
    {
        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        if (! is_array($header)) {
            return [];
        }

        return array_map(static function ($h): string {
            $h = trim((string) $h);
            return preg_replace('/^\xEF\xBB\xBF/', '', $h) ?: $h;
        }, $header);
    }

    /**
     * @param array<int, string> $header
     * @return array{errors:array<int, string>,warnings:array<int, string>,rows:array<int, array<string, mixed>>,total:int,valid:int}
     */
    private function read_preview_rows($handle, array $header, string $delimiter, int $limit): array
    {
        $preview = ['errors' => [], 'warnings' => [], 'rows' => [], 'total' => 0, 'valid' => 0];
        $seen = [];

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $preview['total']++;
            if ($preview['total'] > $limit) {
                $preview['warnings'][] = sprintf(__('Preview is beperkt tot %d regels.', 'webactueel-translate-language-dropdowns'), $limit);
                break;
            }

            if (count($row) !== count($header)) {
                $preview['errors'][] = sprintf(__('Regel %d: kolommen komen niet overeen met header.', 'webactueel-translate-language-dropdowns'), $preview['total'] + 1);
                continue;
            }

            $rowResult = $this->preview_row($row, $header, $preview['total'] + 1, $seen);
            $preview['rows'][] = $rowResult['row'];
            if ($rowResult['errors']) {
                $preview['errors'] = array_merge($preview['errors'], $rowResult['errors']);
                continue;
            }

            $preview['valid']++;
        }

        return $preview;
    }

    /**
     * @param array<int, string> $row
     * @param array<int, string> $header
     * @param array<string, bool> $seen
     * @return array{row:array<string, mixed>,errors:array<int, string>}
     */
    private function preview_row(array $row, array $header, int $line, array &$seen): array
    {
        $data = array_combine($header, $row);
        $rowErrors = $this->validate_row($data, $line, $seen);
        $safe = $this->sanitize_preview_row($data);
        $safe['_errors'] = $rowErrors;

        return ['row' => $safe, 'errors' => $rowErrors];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function sanitize_preview_row(array $data): array
    {
        foreach ($data as $key => $value) {
            if (! is_string($value)) {
                continue;
            }
            $data[$key] = in_array((string) $key, ['original_text', 'translated_text'], true) ? wp_kses_post($value) : sanitize_text_field($value);
        }
        return $data;
    }

    /** @return array<string, mixed> */
    private function csv_preview_error(string $message): array
    {
        return [
            'valid' => false,
            'errors' => [$message],
            'warnings' => [],
            'rows' => [],
            'stats' => ['total' => 0, 'valid' => 0],
        ];
    }
}
