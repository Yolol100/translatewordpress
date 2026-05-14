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
            $errors[] = sprintf(__('Regel %d: hash ontbreekt of is ongeldig.', 'webactueel-translate-language-dropdowns'), $line);
        }
        if ($lang === '') {
            $errors[] = "Regel {$line}: language_code ontbreekt.";
        } elseif (! $this->is_translatable_language($lang)) {
            $errors[] = "Regel {$line}: taal {$lang} is geen actieve vertaaltaal.";
        }
        if (trim(Input::scalar_string($data['original_text'] ?? '')) === '') {
            $errors[] = "Regel {$line}: original_text ontbreekt.";
        }
        if (trim(Input::scalar_string($data['translated_text'] ?? '')) === '') {
            $errors[] = "Regel {$line}: translated_text ontbreekt.";
        }
        $status = Input::key($data['status'] ?? '');
        if ($status !== '' && ! in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review'], true)) {
            $errors[] = sprintf(__('Regel %d: status is ongeldig.', 'webactueel-translate-language-dropdowns'), $line);
        }
        if ($hash !== '' && strlen($hash) >= 16 && $lang !== '') {
            $key = $hash . ':' . $lang;
            if (isset($seen[$key])) {
                $errors[] = "Regel {$line}: dubbele hash/language combinatie.";
            }
            $seen[$key] = true;
        }
        return $errors;
    }

}
