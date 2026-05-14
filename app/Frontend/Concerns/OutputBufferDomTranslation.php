<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Concerns;

use DOMDocument;
use DOMXPath;
use Webactueel\Translate\Translation\StringNormalizer;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

trait OutputBufferDomTranslation
{
    /**
     * @param array<string, string> $map
     */
    private function translate_dom(string $html, array $map): string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (! $loaded) {
            libxml_use_internal_errors($previous);
            return $html;
        }
        $xpath = new DOMXPath($dom);
        $exclusions = $this->xpath_exclusion_predicates();
        $query = '//text()[not(ancestor-or-self::script) and not(ancestor-or-self::style) and not(ancestor-or-self::code) and not(ancestor-or-self::pre) and not(ancestor-or-self::textarea) and not(ancestor-or-self::noscript) and not(ancestor-or-self::svg) and not(ancestor-or-self::math) and not(ancestor-or-self::template)' . $exclusions . ']';
        $nodes = $xpath->query($query);
        $maxReplacements = absint($this->settings['max_replacements'] ?? 1000);
        $count = 0;
        if ($nodes) {
            foreach ($nodes as $node) {
                if ($count >= $maxReplacements) {
                    break;
                }
                $normalized = StringNormalizer::normalize($node->nodeValue ?? '');
                if (isset($map[$normalized])) {
                    $node->nodeValue = $map[$normalized];
                    $count++;
                    $this->lastReplacementCount = $count;
                }
            }
        }
        $this->set_html_language($xpath);
        $this->lastReplacementCount = $count;
        $this->lastUrlRewriteCount = $this->rewrite_internal_urls($xpath);

        $attributes = apply_filters('wat_translatable_attributes', ['alt', 'title', 'aria-label', 'aria-placeholder', 'placeholder']);
        foreach ((array) $attributes as $attribute) {
            $attribute = preg_replace('/[^a-zA-Z0-9_:\-]/', '', (string) $attribute);
            if ($attribute === '') {
                continue;
            }
            $attrNodes = $xpath->query('//*[@' . $attribute . $exclusions . ']');
            if (! $attrNodes) {
                continue;
            }
            foreach ($attrNodes as $node) {
                if ($count >= $maxReplacements) {
                    break 2;
                }
                $value = $node->attributes->getNamedItem($attribute)->nodeValue ?? '';
                $normalized = StringNormalizer::normalize($value);
                if (isset($map[$normalized])) {
                    $node->setAttribute($attribute, $map[$normalized]);
                    $count++;
                    $this->lastReplacementCount = $count;
                }
            }
        }
        $output = $dom->saveHTML() ?: $html;
        $output = preg_replace('/^<\?xml encoding="utf-8" \?>/i', '', $output) ?: $output;
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $filtered = apply_filters('wat_translation_replacement', $output, $this->language, $count);
        return is_scalar($filtered) ? (string) $filtered : $output;
    }

    private function set_html_language(DOMXPath $xpath): void
    {
        $nodes = $xpath->query('/html');
        if ($nodes && $nodes->length > 0) {
            $nodes->item(0)->setAttribute('lang', $this->language);
        }
    }
}
