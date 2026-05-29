<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport;

use Webactueel\Translate\Cache\CacheInvalidator;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Translation\TranslationRepository;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic parts are escaped plugin-owned table names.

final class XliffImporter
{
    use ValidatesLanguages;

    /**
     * @param array<string, mixed> $file Uploaded file array from REST.
     * @param array<int, string> $languages Optional target language allow-list.
     * @return array<string, mixed>
     */
    public function import_uploaded(array $file, array $languages = []): array
    {
        $validation = $this->validate_upload($file);
        if ($validation !== '') {
            return ['imported' => 0, 'skipped' => 0, 'errors' => [$validation]];
        }

        $tmpName = Input::scalar_string($file['tmp_name'] ?? '');
        return $this->import_file($tmpName, $languages);
    }

    /**
     * @param array<int, string> $languages
     * @return array<string, mixed>
     */
    public function import_file(string $path, array $languages = []): array
    {
        global $wpdb;

        if (! is_readable($path)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => [__('XLIFF bestand kon niet gelezen worden.', 'webactueel-translate-language-dropdowns')]];
        }

        $settings = Settings::all();
        $maxRows = isset($settings['csv_import_max_rows']) ? Input::absint($settings['csv_import_max_rows'], 10000) : 10000;
        $maxRows = (int) apply_filters('wat_xliff_import_max_units', max(1, min(50000, $maxRows)));
        $maxRows = max(1, min(50000, $maxRows));
        $languages = array_values(array_unique(array_filter(array_map('sanitize_key', $languages))));

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->load($path, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => [__('XLIFF XML is ongeldig of kon niet veilig worden verwerkt.', 'webactueel-translate-language-dropdowns')],
                'xml_errors' => array_slice(array_map(static fn($error): string => trim((string) $error->message), $errors), 0, 5),
            ];
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
        $units = $xpath->query('//*[local-name()="trans-unit"]');
        if (! $units instanceof \DOMNodeList || $units->length === 0) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => [__('XLIFF bevat geen trans-unit regels.', 'webactueel-translate-language-dropdowns')]];
        }

        $repo = new TranslationRepository();
        $imported = 0;
        $skipped = 0;
        $lineErrors = [];
        $seen = [];
        $truncated = false;
        $count = 0;

        foreach ($units as $unit) {
            if (! $unit instanceof \DOMElement) {
                continue;
            }
            $count++;
            if ($count > $maxRows) {
                $truncated = true;
                $lineErrors[] = sprintf(__('XLIFF import gestopt na %d units. Verdeel grotere imports in kleinere bestanden.', 'webactueel-translate-language-dropdowns'), $maxRows);
                break;
            }

            $file = $this->closest_file($unit);
            $language = $file instanceof \DOMElement ? sanitize_key((string) $file->getAttribute('target-language')) : '';
            $language = $language !== '' ? $language : sanitize_key((string) $unit->getAttribute('target-language'));
            if ($language === '' || ($languages && ! in_array($language, $languages, true)) || ! $this->is_translatable_language($language)) {
                $skipped++;
                continue;
            }

            $hash = sanitize_text_field((string) $unit->getAttribute('resname'));
            if ($hash === '') {
                $id = sanitize_text_field((string) $unit->getAttribute('id'));
                $hash = preg_replace('/:.+$/', '', $id) ?: '';
            }
            $source = $this->child_text($unit, 'source');
            $target = trim(wp_kses_post($this->child_text($unit, 'target')));
            if ($target === '') {
                $skipped++;
                continue;
            }

            $rowKey = $hash . ':' . $language;
            if ($hash !== '' && isset($seen[$rowKey])) {
                $skipped++;
                continue;
            }
            if ($hash !== '') {
                $seen[$rowKey] = true;
            }

            $stringId = 0;
            if ($hash !== '' && strlen($hash) >= 16) {
                $stringId = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM `' . Tables::sql_identifier(Tables::strings()) . '` WHERE hash = %s', $hash));
            }
            if (! $stringId && trim($source) !== '') {
                $context = $this->extract_note_value($unit, 'context');
                $sourceType = sanitize_key($this->extract_note_value($unit, 'source_type'));
                $sourceId = absint($this->extract_note_value($unit, 'source_id'));
                $stringId = $repo->upsert_string(wp_kses_post($source), $sourceType, $sourceId, $context);
            }
            if (! $stringId) {
                $skipped++;
                $lineErrors[] = __('XLIFF unit overgeslagen: geen herkenbare hash of brontekst.', 'webactueel-translate-language-dropdowns');
                continue;
            }

            $status = $this->map_state_to_status($this->target_state($unit));
            if ($repo->save_translation($stringId, $language, $target, $status, 'xliff')) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        CacheInvalidator::bump();
        do_action('wat_after_xliff_import', $imported, $lineErrors);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'max_units' => $maxRows,
            'truncated' => $truncated,
            'errors' => array_slice($lineErrors, 0, 50),
        ];
    }

    private function validate_upload(array $file): string
    {
        $tmpName = Input::scalar_string($file['tmp_name'] ?? '');
        $name = Input::scalar_string($file['name'] ?? '');
        $error = isset($file['error']) ? absint($file['error']) : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            return __('Geen geldig XLIFF uploadbestand ontvangen.', 'webactueel-translate-language-dropdowns');
        }
        if ($tmpName === '' || ! is_uploaded_file($tmpName)) {
            return __('XLIFF upload kon niet veilig worden verwerkt door de server.', 'webactueel-translate-language-dropdowns');
        }
        $size = isset($file['size']) ? absint($file['size']) : (is_readable($tmpName) ? (int) filesize($tmpName) : 0);
        $maxSize = (int) apply_filters('wat_xliff_import_max_bytes', 5 * 1024 * 1024);
        $maxSize = max(1024, min(20 * 1024 * 1024, $maxSize));
        if ($size <= 0 || $size > $maxSize) {
            return sprintf(
                /* translators: %s: maximum upload size. */
                __('XLIFF bestand is te groot of leeg. Maximale grootte: %s.', 'webactueel-translate-language-dropdowns'),
                size_format($maxSize)
            );
        }
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (! in_array($extension, ['xlf', 'xliff'], true)) {
            return __('Alleen .xlf en .xliff bestanden zijn toegestaan.', 'webactueel-translate-language-dropdowns');
        }
        if (function_exists('wp_check_filetype_and_ext')) {
            $checked = wp_check_filetype_and_ext($tmpName, $name, [
                'xlf' => 'application/x-xliff+xml',
                'xliff' => 'application/x-xliff+xml',
            ]);
            if (! empty($checked['ext']) && in_array((string) $checked['ext'], ['xlf', 'xliff'], true)) {
                return '';
            }
        }
        $head = file_get_contents($tmpName, false, null, 0, 512); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Small upload sniff for XML root only.
        if (! is_string($head) || stripos($head, '<xliff') === false) {
            return __('Upload lijkt geen XLIFF XML bestand te zijn.', 'webactueel-translate-language-dropdowns');
        }
        return '';
    }

    private function child_text(\DOMElement $unit, string $name): string
    {
        foreach ($unit->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                return (string) $child->textContent;
            }
        }
        return '';
    }

    private function target_state(\DOMElement $unit): string
    {
        foreach ($unit->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'target') {
                return sanitize_key((string) $child->getAttribute('state'));
            }
        }
        return '';
    }

    private function map_state_to_status(string $state): string
    {
        return match (sanitize_key($state)) {
            'final', 'signed-off' => 'published',
            'translated', 'needs-review-adaptation', 'needs-review-l10n', 'needs-review-translation' => 'needs_review',
            default => 'needs_review',
        };
    }

    private function closest_file(\DOMElement $node): ?\DOMElement
    {
        $parent = $node->parentNode;
        while ($parent instanceof \DOMElement) {
            if ($parent->localName === 'file') {
                return $parent;
            }
            $parent = $parent->parentNode;
        }
        return null;
    }

    private function extract_note_value(\DOMElement $unit, string $key): string
    {
        foreach ($unit->childNodes as $child) {
            if (! $child instanceof \DOMElement || $child->localName !== 'note') {
                continue;
            }
            $text = (string) $child->textContent;
            if (preg_match('/(?:^|;\s*)' . preg_quote($key, '/') . '=([^;]*)/', $text, $matches)) {
                return sanitize_text_field($matches[1]);
            }
        }
        return '';
    }
}
