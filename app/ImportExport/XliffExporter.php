<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport;

use Webactueel\Translate\Database\Tables;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.

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
        $dom = $this->create_xliff_document();
        $xliff = $dom->documentElement;
        if (! $xliff instanceof \DOMElement) {
            return '';
        }

        $files = [];
        $sourceLanguage = $this->source_language();
        foreach ($this->rows($languages, $mode) as $row) {
            $language = sanitize_key((string) ($row['language_code'] ?? ''));
            if ($language === '') {
                continue;
            }

            $body = $this->file_body_for_language($dom, $xliff, $files, $language, $sourceLanguage);
            $body->appendChild($this->create_translation_unit($dom, $row, $language));
        }

        $xml = $dom->saveXML();
        return is_string($xml) ? $xml : '';
    }

    private function create_xliff_document(): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $xliff = $dom->createElement('xliff');
        $xliff->setAttribute('version', '1.2');
        $xliff->setAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');
        $dom->appendChild($xliff);

        return $dom;
    }

    private function file_body_for_language(\DOMDocument $dom, \DOMElement $xliff, array &$files, string $language, string $sourceLanguage): \DOMElement
    {
        if (isset($files[$language]) && $files[$language] instanceof \DOMElement) {
            return $files[$language];
        }

        $file = $dom->createElement('file');
        $file->setAttribute('original', 'webactueel-translate');
        $file->setAttribute('source-language', $sourceLanguage);
        $file->setAttribute('target-language', $language);
        $file->setAttribute('datatype', 'html');

        $body = $dom->createElement('body');
        $file->appendChild($body);
        $xliff->appendChild($file);
        $files[$language] = $body;

        return $body;
    }

    private function create_translation_unit(\DOMDocument $dom, array $row, string $language): \DOMElement
    {
        $hash = sanitize_text_field((string) ($row['hash'] ?? ''));
        $unit = $dom->createElement('trans-unit');
        $unit->setAttribute('id', $hash . ':' . $language);
        $unit->setAttribute('resname', $hash);
        $unit->setAttribute('restype', sanitize_key((string) ($row['source_type'] ?? '')) ?: 'string');
        $unit->setAttribute('translate', 'yes');

        $this->append_text_node($dom, $unit, 'source', (string) ($row['original_text'] ?? ''));
        $target = $this->append_text_node($dom, $unit, 'target', (string) ($row['translated_text'] ?? ''));
        $target->setAttribute('state', $this->map_status_to_state((string) ($row['status'] ?? '')));
        $this->append_text_node($dom, $unit, 'note', $this->translation_note($row));

        return $unit;
    }

    private function append_text_node(\DOMDocument $dom, \DOMElement $parent, string $name, string $value): \DOMElement
    {
        $element = $dom->createElement($name);
        $element->appendChild($dom->createTextNode($value));
        $parent->appendChild($element);

        return $element;
    }

    private function translation_note(array $row): string
    {
        $noteParts = array_filter([
            'context=' . sanitize_text_field((string) ($row['context'] ?? '')),
            'source_type=' . sanitize_key((string) ($row['source_type'] ?? '')),
            'source_id=' . absint($row['source_id'] ?? 0),
        ]);

        return implode('; ', $noteParts);
    }

    private function source_language(): string
    {
        global $wpdb;

        $sourceLanguage = (string) $wpdb->get_var(
            $wpdb->prepare('SELECT code FROM %i WHERE is_default = 1 ORDER BY id ASC LIMIT 1', Tables::languages())
        );

        $fallback = strtolower(substr((string) get_locale(), 0, 2)) ?: 'nl';
        return $sourceLanguage !== '' ? $sourceLanguage : $fallback;
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
