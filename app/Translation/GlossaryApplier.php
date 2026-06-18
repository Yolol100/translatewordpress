<?php

declare(strict_types=1);

namespace Webactueel\Translate\Translation;

use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

final class GlossaryApplier
{
    /**
     * @param array<int, array<string, mixed>> $terms
     */
    public function apply(string $text, array $terms): string
    {
        foreach ($terms as $term) {
            $source = Input::scalar_string($term['source_term'] ?? '');
            $target = Input::scalar_string($term['target_term'] ?? '');
            if ($source === '' || $target === '') {
                continue;
            }

            $text = ! empty($term['case_sensitive']) ? str_replace($source, $target, $text) : str_ireplace($source, $target, $text);
        }

        return $text;
    }
}
