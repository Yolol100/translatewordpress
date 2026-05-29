<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport;

use Webactueel\Translate\Database\Tables;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic parts are escaped plugin-owned table names.

final class XliffExporter
{
    private function normalize_languages(array $languages): array
    {
        $languages = array_map('sanitize_key', $languages);
        return array_values(array_unique(array_filter($languages)));
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function rows(array $languages = [], string $mode = 'all'): array
    {
        return (new CsvExporter())->rows($this->normalize_languages($languages), $mode);
    }

    public function xliff_string(array $languages = [], string $mode = 'all'): string
    {
        global $wpdb;

        $sourceLanguage = (string) $wpdb->get_var(
            'SELECT code FROM `' . Tables::sql_identifier(Tables::languages()) . '` WHERE is_default = 1 ORDER BY id ASC LIMIT 1'
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if ($sourceLanguage === '') {
            $sourceLanguage = strtolower(substr((string) get_locale(), 0, 2)) ?: 'nl';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $xliff = $dom->createElement('xliff');
        $xliff->setAttribute('version', '1.2');
        $xliff->setAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');
        $dom->appendChild($xliff);

        $files = [];
        foreach ($this->rows($languages, $mode) as $row) {
            $language = sanitize_key((string) ($row['language_code'] ?? ''));
            if ($language === '') {
                continue;
            }
            if (! isset($files[$language])) {
                $file = $dom->createElement('file');
                $file->setAttribute('original', 'webactueel-translate');
                $file->setAttribute('source-language', $sourceLanguage);
                $file->setAttribute('target-language', $language);
                $file->setAttribute('datatype', 'html');
                $body = $dom->createElement('body');
                $file->appendChild($body);
                $xliff->appendChild($file);
                $files[$language] = $body;
            }

            $hash = sanitize_text_field((string) ($row['hash'] ?? ''));
            $unit = $dom->createElement('trans-unit');
            $unit->setAttribute('id', $hash . ':' . $language);
            $unit->setAttribute('resname', $hash);
            $unit->setAttribute('restype', sanitize_key((string) ($row['source_type'] ?? '')) ?: 'string');
            $unit->setAttribute('translate', 'yes');

            $source = $dom->createElement('source');
            $source->appendChild($dom->createTextNode((string) ($row['original_text'] ?? '')));
            $unit->appendChild($source);

            $target = $dom->createElement('target');
            $target->setAttribute('state', $this->map_status_to_state((string) ($row['status'] ?? '')));
            $target->appendChild($dom->createTextNode((string) ($row['translated_text'] ?? '')));
            $unit->appendChild($target);

            $noteParts = array_filter([
                'context=' . sanitize_text_field((string) ($row['context'] ?? '')),
                'source_type=' . sanitize_key((string) ($row['source_type'] ?? '')),
                'source_id=' . absint($row['source_id'] ?? 0),
            ]);
            $note = $dom->createElement('note');
            $note->appendChild($dom->createTextNode(implode('; ', $noteParts)));
            $unit->appendChild($note);

            $files[$language]->appendChild($unit);
        }

        $xml = $dom->saveXML();
        return is_string($xml) ? $xml : '';
    }

    private function map_status_to_state(string $status): string
    {
        $status = sanitize_key($status);
        return match ($status) {
            'published', 'reviewed' => 'final',
            'needs_review', 'draft', 'outdated', 'new' => 'needs-review-translation',
            default => 'new',
        };
    }
}
