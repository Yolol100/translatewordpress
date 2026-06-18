<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport;

use Webactueel\Translate\Cache\TranslationCache;
use Webactueel\Translate\ImportExport\Concerns\SharedImportHelpers;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Translation\StringNormalizer;
use Webactueel\Translate\Translation\TranslationRepository;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic parts are escaped plugin-owned table names.

final class XliffImporter
{
    use ValidatesLanguages;
    use SharedImportHelpers;

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
        if (! is_readable($path)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => [__('XLIFF bestand kon niet gelezen worden.', 'webactueel-translate-language-dropdowns')]];
        }

        $loaded = $this->load_xliff_units($path);
        if (isset($loaded['error'])) {
            return $loaded['error'];
        }

        $maxRows = $this->import_row_limit('wat_xliff_import_max_units');
        $languages = $this->normalize_import_languages($languages);
        $repo = new TranslationRepository();
        $result = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
            'truncated' => false,
        ];
        $seen = [];
        $count = 0;

        foreach ($loaded['units'] as $unit) {
            if (! $unit instanceof \DOMElement) {
                continue;
            }
            $count++;
            if ($count > $maxRows) {
                $result['truncated'] = true;
                $result['errors'][] = sprintf(__('XLIFF import gestopt na %d units. Verdeel grotere imports in kleinere bestanden.', 'webactueel-translate-language-dropdowns'), $maxRows);
                break;
            }

            $unitResult = $this->import_xliff_unit($unit, $languages, $repo, $seen);
            $result['imported'] += $unitResult['imported'];
            $result['skipped'] += $unitResult['skipped'];
            $result['errors'] = array_merge($result['errors'], $unitResult['errors']);
        }

        TranslationCache::bump();
        do_action('wat_after_xliff_import', $result['imported'], $result['errors']);

        return [
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'max_units' => $maxRows,
            'truncated' => $result['truncated'],
            'errors' => array_slice($result['errors'], 0, 50),
        ];
    }

    /**
     * @return array{units:\DOMNodeList}|array{error:array<string, mixed>}
     */
    private function load_xliff_units(string $path): array
    {
        if (! class_exists('DOMDocument') || ! class_exists('DOMXPath') || ! extension_loaded('libxml')) {
            return [
                'error' => [
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => [__('XLIFF import vereist de PHP DOM/ext-xml extensie.', 'webactueel-translate-language-dropdowns')],
                ],
            ];
        }

        if ($this->xliff_contains_disallowed_dtd($path)) {
            return [
                'error' => [
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => [__('XLIFF XML bevat een DTD of entity-definitie en is geblokkeerd.', 'webactueel-translate-language-dropdowns')],
                ],
            ];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        try {
            $loaded = $dom->load($path, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            return [
                'error' => [
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => [__('XLIFF XML is ongeldig of kon niet veilig worden verwerkt.', 'webactueel-translate-language-dropdowns')],
                    'xml_errors' => array_slice(array_map(static fn($error): string => trim((string) $error->message), $errors), 0, 5),
                ],
            ];
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
        $units = $xpath->query('//*[local-name()="trans-unit"]');
        if (! $units instanceof \DOMNodeList || $units->length === 0) {
            return [
                'error' => [
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => [__('XLIFF bevat geen trans-unit regels.', 'webactueel-translate-language-dropdowns')],
                ],
            ];
        }

        return ['units' => $units];
    }

    private function xliff_contains_disallowed_dtd(string $path): bool
    {
        // DTDs and entity declarations are not needed for the plugin-created XLIFF
        // roundtrip and create avoidable parser/resource-risk in uploaded XML.
        $handle = fopen($path, 'rb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Local uploaded/imported XML scan before DOM parsing.
        if (! is_resource($handle)) {
            return true;
        }

        $buffer = '';
        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 8192); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Local uploaded/imported XML scan before DOM parsing.
                if (! is_string($chunk)) {
                    return true;
                }

                $buffer .= $chunk;
                if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $buffer) === 1) {
                    return true;
                }

                $buffer = substr($buffer, -32);
            }
        } finally {
            fclose($handle);
        }

        return false;
    }

    /**
     * @param array<int, string> $languages
     * @param array<string, bool> $seen
     * @return array{imported:int,skipped:int,errors:array<int, string>}
     */
    private function import_xliff_unit(\DOMElement $unit, array $languages, TranslationRepository $repo, array &$seen): array
    {
        $language = $this->xliff_unit_language($unit);
        if ($language === '' || ($languages && ! in_array($language, $languages, true)) || ! $this->is_translatable_language($language)) {
            return ['imported' => 0, 'skipped' => 1, 'errors' => []];
        }

        $hash = $this->xliff_unit_hash($unit);
        $source = $this->child_text($unit, 'source');
        $target = trim(wp_kses_post($this->child_text($unit, 'target')));
        if ($target === '') {
            return ['imported' => 0, 'skipped' => 1, 'errors' => []];
        }

        $rowKey = $hash . ':' . $language;
        if ($hash !== '' && isset($seen[$rowKey])) {
            return ['imported' => 0, 'skipped' => 1, 'errors' => []];
        }
        if ($hash !== '') {
            $seen[$rowKey] = true;
        }

        $stringId = $this->xliff_unit_string_id($unit, $hash, $source, $repo);
        if (! $stringId) {
            return [
                'imported' => 0,
                'skipped' => 1,
                'errors' => [__('XLIFF unit overgeslagen: geen herkenbare hash of brontekst.', 'webactueel-translate-language-dropdowns')],
            ];
        }

        $status = $this->apply_import_review_policy($this->map_state_to_status($this->target_state($unit)));
        if ($repo->save_translation($stringId, $language, $target, $status, 'xliff')) {
            return ['imported' => 1, 'skipped' => 0, 'errors' => []];
        }

        return [
            'imported' => 0,
            'skipped' => 1,
            'errors' => [__('XLIFF unit overgeslagen: vertaling kon niet worden opgeslagen.', 'webactueel-translate-language-dropdowns')],
        ];
    }

    private function apply_import_review_policy(string $status): string
    {
        $settings = Settings::all();
        if (! current_user_can('manage_options') && ! empty($settings['translator_review_required']) && in_array($status, ['published', 'reviewed'], true)) {
            return 'needs_review';
        }

        return $status;
    }

    private function xliff_unit_language(\DOMElement $unit): string
    {
        $file = $this->closest_file($unit);
        $language = $file instanceof \DOMElement ? sanitize_key((string) $file->getAttribute('target-language')) : '';
        return $language !== '' ? $language : sanitize_key((string) $unit->getAttribute('target-language'));
    }

    private function xliff_unit_hash(\DOMElement $unit): string
    {
        $hash = sanitize_text_field((string) $unit->getAttribute('resname'));
        if (StringNormalizer::is_hash($hash)) {
            return $hash;
        }

        $id = sanitize_text_field((string) $unit->getAttribute('id'));
        $hash = preg_replace('/:.+$/', '', $id) ?: '';
        return StringNormalizer::is_hash($hash) ? $hash : '';
    }

    private function xliff_unit_string_id(\DOMElement $unit, string $hash, string $source, TranslationRepository $repo): int
    {
        $stringId = $this->find_string_id_by_hash($hash);
        if ($stringId || trim($source) === '') {
            return $stringId;
        }

        $context = $this->extract_note_value($unit, 'context');
        $sourceType = sanitize_key($this->extract_note_value($unit, 'source_type'));
        $sourceId = absint($this->extract_note_value($unit, 'source_id'));
        return $repo->upsert_string(wp_kses_post($source), $sourceType, $sourceId, $context);
    }

    private function validate_upload(array $file): string
    {
        $tmpName = Input::scalar_string($file['tmp_name'] ?? '');
        $name = sanitize_file_name(Input::scalar_string($file['name'] ?? ''));
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
        $sniffBytes = (int) apply_filters('wat_xliff_import_sniff_bytes', 16 * 1024);
        $sniffBytes = max(512, min(128 * 1024, $sniffBytes));
        $head = file_get_contents($tmpName, false, null, 0, $sniffBytes); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bounded upload sniff for XML root only.
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
