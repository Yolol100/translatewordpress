<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Concerns;

use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

trait OutputBufferExclusions
{
    private function xpath_exclusion_predicates(): string
    {
        $predicates = [
            'ancestor-or-self::*[@translate="no"]',
            'ancestor-or-self::*[@id="wpadminbar"]',
            'ancestor-or-self::*[contains(concat(" ", normalize-space(@class), " "), " notranslate ")]',
            'ancestor-or-self::*[contains(concat(" ", normalize-space(@class), " "), " wat-language-switcher ")]',
            'ancestor-or-self::*[contains(concat(" ", normalize-space(@class), " "), " grecaptcha-badge ")]',
        ];
        $selectors = preg_split('/\r\n|\r|\n/', Input::scalar_string($this->settings['exclude_selectors'] ?? '')) ?: [];
        foreach ($selectors as $selector) {
            $predicate = $this->selector_to_xpath_predicate(trim((string) $selector));
            if ($predicate !== '') {
                $predicates[] = $predicate;
            }
        }

        $predicates = array_values(array_unique($predicates));
        return ' and not(' . implode(') and not(', $predicates) . ')';
    }

    private function selector_to_xpath_predicate(string $selector): string
    {
        if ($selector === '') {
            return '';
        }
        if (preg_match('/^\.([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            return 'ancestor-or-self::*[contains(concat(" ", normalize-space(@class), " "), ' . $this->xpath_literal(' ' . $matches[1] . ' ') . ')]';
        }
        if (preg_match('/^#([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            return 'ancestor-or-self::*[@id=' . $this->xpath_literal($matches[1]) . ']';
        }
        if (preg_match('/^\[([a-zA-Z0-9_:-]+)(?:=["\']?([^"\']+)["\']?)?\]$/', $selector, $matches)) {
            $attribute = preg_replace('/[^a-zA-Z0-9_:\-]/', '', $matches[1]);
            if ($attribute === '') {
                return '';
            }
            if (isset($matches[2])) {
                return 'ancestor-or-self::*[@' . $attribute . '=' . $this->xpath_literal($matches[2]) . ']';
            }
            return 'ancestor-or-self::*[@' . $attribute . ']';
        }
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $selector)) {
            return 'ancestor::' . strtolower($selector);
        }

        return '';
    }

    private function xpath_literal(string $value): string
    {
        if (strpos($value, '"') === false) {
            return '"' . $value . '"';
        }
        if (strpos($value, "'") === false) {
            return "'" . $value . "'";
        }

        $parts = array_map(static fn($part): string => '"' . $part . '"', explode('"', $value));
        return 'concat(' . implode(', \'"\', ', $parts) . ')';
    }
}
