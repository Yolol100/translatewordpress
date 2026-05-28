<?php

declare(strict_types=1);

namespace Webactueel\Translate\ImportExport\Concerns;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;

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
        if ($hash === '' || strlen($hash) < 16) {
            // translators: Placeholder values are replaced with runtime details such as a row number, language name or count.
            $errors[] = sprintf(__('Regel %d: hash ontbreekt of is ongeldig.', 'webactueel-translate-language-dropdowns'), $line);
        }
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
        if ($status !== '' && ! in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review'], true)) {
            // translators: Placeholder values are replaced with runtime details such as a row number, language name or count.
            $errors[] = sprintf(__('Regel %d: status is ongeldig.', 'webactueel-translate-language-dropdowns'), $line);
        }
        if ($hash !== '' && strlen($hash) >= 16 && $lang !== '') {
            $key = $hash . ':' . $lang;
            if (isset($seen[$key])) {
                // translators: Placeholder values are replaced with runtime details such as a row number, language name or count.
                $errors[] = sprintf(__('Regel %d: dubbele hash/language combinatie.', 'webactueel-translate-language-dropdowns'), $line);
            }
            $seen[$key] = true;
        }
        return $errors;
    }
}
