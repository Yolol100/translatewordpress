<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support\Concerns;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

trait ValidatesLanguages
{
    private function is_translatable_language(string $languageCode): bool
    {
        $languageCode = sanitize_key($languageCode);
        if ($languageCode === '') {
            return false;
        }

        foreach (LanguageDetector::active_languages() as $language) {
            if (Input::key($language['code'] ?? '') === $languageCode) {
                return empty($language['is_default']);
            }
        }

        return false;
    }
}
