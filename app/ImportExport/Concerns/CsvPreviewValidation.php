<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport\Concerns;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Translation\StringNormalizer;

if (! defined('ABSPATH')) {
    exit;
}

trait CsvPreviewValidation
{
    use ValidatesLanguages;
    /**
     * @param array<string, mixed> $data
     * @param array<string, bool>  $seen
     * @return array<int, string>
     */
    private function validate_row(array $data, int $line, array &$seen): array
    {
        $errors = [];
        $hash = Input::text($data['hash'] ?? '');
        $lang = Input::key($data['language_code'] ?? '');
        if ($lang === '') {
            // translators: Placeholder values are replaced with runtime details such as a row number, language name or count.
            $errors[] = sprintf(__('Regel %d: language_code ontbreekt.', 'webactueel-translate-language-dropdowns'), $line);
        } elseif (! $this->is_translatable_language($lang)) {
            // translators: Placeholder values are replaced with runtime details such as a row number, language name or count.
            $errors[] = sprintf(__('Regel %1$d: taal %2$s is geen actieve vertaaltaal.', 'webactueel-translate-language-dropdowns'), $line, $lang);
        }
        if (trim(Input::scalar_string($data['original_text'] ?? '')) === '') {
            // translators: Placeholder values are replaced with runtime details such as a row number, language name or count.
            $errors[] = sprintf(__('Regel %d: original_text ontbreekt.', 'webactueel-translate-language-dropdowns'), $line);
        }
        if (trim(Input::scalar_string($data['translated_text'] ?? '')) === '') {
            // translators: Placeholder values are replaced with runtime details such as a row number, language name or count.
            $errors[] = sprintf(__('Regel %d: translated_text ontbreekt.', 'webactueel-translate-language-dropdowns'), $line);
        }
        $status = Input::key($data['status'] ?? '');
        if ($status !== '' && ! in_array($status, ['new', 'missing', 'draft', 'reviewed', 'published', 'ignored', 'needs_review', 'outdated'], true)) {
            // translators: Placeholder values are replaced with runtime details such as a row number, language name or count.
            $errors[] = sprintf(__('Regel %d: status is ongeldig.', 'webactueel-translate-language-dropdowns'), $line);
        }
        $key = $this->csv_preview_row_key($data, $hash, $lang);
        if ($key !== '') {
            if (isset($seen[$key])) {
                // translators: Placeholder values are replaced with runtime details such as a row number, language name or count.
                $errors[] = sprintf(__('Regel %d: dubbele importregel voor dezelfde doelstring/taal.', 'webactueel-translate-language-dropdowns'), $line);
            }
            $seen[$key] = true;
        }
        return $errors;
    }

    /** @param array<string, mixed> $data */
    private function csv_preview_row_key(array $data, string $hash, string $lang): string
    {
        if ($lang === '') {
            return '';
        }

        if (StringNormalizer::is_hash($hash)) {
            return 'hash:' . strtolower($hash) . ':' . $lang;
        }

        $original = trim(wp_kses_post(Input::scalar_string($data['original_text'] ?? '')));
        if ($original === '') {
            return '';
        }

        return 'fallback:' . hash('sha256', StringNormalizer::normalize($original)) . ':'
            . Input::key($data['source_type'] ?? '') . ':'
            . Input::absint($data['source_id'] ?? 0) . ':'
            . hash('sha256', Input::text($data['context'] ?? '')) . ':'
            . $lang;
    }
}
