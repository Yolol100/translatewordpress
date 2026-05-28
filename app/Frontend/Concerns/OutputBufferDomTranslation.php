<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Concerns;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Webactueel\Translate\Translation\StringNormalizer;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

trait OutputBufferDomTranslation
{
    /**
     * @param array<string, string> $map
     */
    private function translate_dom(string $html, array $map): string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $flags = LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING;
        if (defined('LIBXML_NONET')) {
            $flags |= LIBXML_NONET;
        }
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, $flags);
        if (! $loaded) {
            libxml_clear_errors();
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
                if (isset($map[$normalized]) && $this->replace_text_node($dom, $node, $map[$normalized])) {
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
                if (! $node instanceof DOMElement) {
                    continue;
                }
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

    private function replace_text_node(DOMDocument $dom, \DOMNode $node, string $translation): bool
    {
        $translation = trim((string) wp_kses_post($translation));
        if ($translation === '') {
            return false;
        }

        if (! $this->can_replace_with_inline_markup($node, $translation)) {
            $node->nodeValue = wp_strip_all_tags($translation);
            return true;
        }

        $parent = $node->parentNode;
        $parentName = $parent ? strtolower($parent->nodeName) : '';
        $fragmentNodes = $this->create_inline_translation_nodes($dom, $translation, $parentName);
        if ($fragmentNodes === [] || ! $parent) {
            $node->nodeValue = wp_strip_all_tags($translation);
            return true;
        }

        foreach ($fragmentNodes as $fragmentNode) {
            $parent->insertBefore($fragmentNode, $node);
        }
        $parent->removeChild($node);
        return true;
    }

    private function can_replace_with_inline_markup(\DOMNode $node, string $translation): bool
    {
        if ($translation === wp_strip_all_tags($translation)) {
            return false;
        }

        $parent = $node->parentNode;
        if (! $parent) {
            return false;
        }

        return in_array(strtolower($parent->nodeName), [
            'blockquote',
            'button',
            'caption',
            'dd',
            'div',
            'dt',
            'figcaption',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'label',
            'legend',
            'li',
            'p',
            'span',
            'td',
            'th',
        ], true);
    }

    /**
     * @return list<\DOMNode>
     */
    private function create_inline_translation_nodes(DOMDocument $dom, string $translation, string $parentName): array
    {
        $translation = $this->sanitize_inline_translation_markup($translation, $parentName);
        if ($translation === '' || $translation === wp_strip_all_tags($translation)) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $temporaryDom = new DOMDocument('1.0', 'UTF-8');
        $flags = LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING;
        if (defined('LIBXML_NONET')) {
            $flags |= LIBXML_NONET;
        }

        $loaded = $temporaryDom->loadHTML('<?xml encoding="utf-8" ?><body>' . $translation . '</body>', $flags);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            return [];
        }

        $body = $temporaryDom->getElementsByTagName('body')->item(0);
        if (! $body) {
            return [];
        }

        $nodes = [];
        foreach (iterator_to_array($body->childNodes) as $childNode) {
            $nodes[] = $dom->importNode($childNode, true);
        }
        return $nodes;
    }

    private function sanitize_inline_translation_markup(string $translation, string $parentName): string
    {
        $allowed = apply_filters('wat_inline_translation_allowed_html', [
            'a' => [
                'href' => true,
                'rel' => true,
                'target' => true,
                'title' => true,
            ],
            'abbr' => ['title' => true],
            'b' => [],
            'br' => [],
            'cite' => [],
            'code' => [],
            'em' => [],
            'i' => [],
            'mark' => [],
            'small' => [],
            'span' => [
                'class' => true,
                'dir' => true,
                'lang' => true,
            ],
            'strong' => [],
            'sub' => [],
            'sup' => [],
        ]);

        if (! is_array($allowed)) {
            $allowed = [];
        }
        if (in_array($parentName, ['button', 'label'], true)) {
            unset($allowed['a']);
        }

        return trim(wp_kses($translation, $allowed));
    }

    private function set_html_language(DOMXPath $xpath): void
    {
        $nodes = $xpath->query('/html');
        if ($nodes && $nodes->length > 0) {
            $html = $nodes->item(0);
            if ($html instanceof DOMElement) {
                $html->setAttribute('lang', $this->language);
            }
        }
    }
}
