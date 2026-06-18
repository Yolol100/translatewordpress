<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Concerns;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Translation\StringNormalizer;
use Webactueel\Translate\Translation\TranslationRepository;

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

        try {
            $dom = $this->load_translation_dom($html);
            if (! $dom) {
                return $html;
            }

            $xpath = new DOMXPath($dom);
            $exclusions = $this->xpath_exclusion_predicates();
            $maxReplacements = absint($this->settings['max_replacements'] ?? 1000);

            $discovered = $this->discover_runtime_nodes($xpath, $map, $exclusions);
            $count = $this->replace_text_nodes($dom, $xpath, $map, $exclusions, $maxReplacements);
            $this->set_html_language($xpath);
            $this->lastReplacementCount = $count;
            $this->lastUrlRewriteCount = $this->rewrite_internal_urls($xpath);
            $count = $this->replace_attribute_nodes($xpath, $map, $exclusions, $count, $maxReplacements);

            $output = $this->save_translation_dom($dom, $html);
            return $this->apply_translation_replacement_filter($output, $count);
        } finally {
            $this->restore_libxml_state($previous);
        }
    }

    private function load_translation_dom(string $html): ?DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, $this->dom_load_flags());
        if (! $loaded) {
            return null;
        }

        return $dom;
    }

    private function dom_load_flags(): int
    {
        $flags = LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING;
        if (defined('LIBXML_NONET')) {
            $flags |= LIBXML_NONET;
        }

        return $flags;
    }

    /**
     * @param array<string, string> $map
     */
    private function replace_text_nodes(DOMDocument $dom, DOMXPath $xpath, array $map, string $exclusions, int $maxReplacements): int
    {
        $query = '//text()[not(ancestor-or-self::script) and not(ancestor-or-self::style) and not(ancestor-or-self::code) and not(ancestor-or-self::pre) and not(ancestor-or-self::textarea) and not(ancestor-or-self::noscript) and not(ancestor-or-self::svg) and not(ancestor-or-self::math) and not(ancestor-or-self::template)' . $exclusions . ']';
        $nodes = $xpath->query($query);
        $count = 0;
        if (! $nodes) {
            return $count;
        }

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

        return $count;
    }

    /**
     * @param array<string, string> $map
     */
    private function replace_attribute_nodes(DOMXPath $xpath, array $map, string $exclusions, int $count, int $maxReplacements): int
    {
        $attributes = apply_filters('wat_translatable_attributes', ['alt', 'title', 'aria-label', 'aria-placeholder', 'placeholder']);
        foreach ((array) $attributes as $attribute) {
            if (! is_scalar($attribute)) {
                continue;
            }
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

        return $count;
    }

    private function discover_runtime_nodes(DOMXPath $xpath, array $map, string $exclusions): int
    {
        $this->lastRuntimeDiscoveryCount = 0;
        if (empty($this->settings['runtime_discovery_enabled'])) {
            return 0;
        }

        $limit = max(1, min(1000, absint($this->settings['runtime_discovery_max_per_request'] ?? 100)));
        $seen = [];
        $count = 0;
        $repository = new TranslationRepository();
        $path = $this->runtime_discovery_path_key();

        $query = '//text()[not(ancestor-or-self::script) and not(ancestor-or-self::style) and not(ancestor-or-self::code) and not(ancestor-or-self::pre) and not(ancestor-or-self::textarea) and not(ancestor-or-self::noscript) and not(ancestor-or-self::svg) and not(ancestor-or-self::math) and not(ancestor-or-self::template)' . $exclusions . ']';
        $nodes = $xpath->query($query);
        if ($nodes) {
            foreach ($nodes as $node) {
                if ($count >= $limit) {
                    break;
                }
                $count += $this->discover_runtime_text((string) ($node->nodeValue ?? ''), $repository, $map, $seen, 'text_node', $path . ':text:' . $count);
            }
        }

        if ($count < $limit) {
            $count = $this->discover_runtime_attributes($xpath, $repository, $map, $seen, $count, $limit, $path, $exclusions);
        }

        $this->lastRuntimeDiscoveryCount = $count;
        return $count;
    }

    private function discover_runtime_attributes(DOMXPath $xpath, TranslationRepository $repository, array $map, array &$seen, int $count, int $limit, string $path, string $exclusions): int
    {
        $attributes = apply_filters('wat_translatable_attributes', ['alt', 'title', 'aria-label', 'aria-placeholder', 'placeholder']);
        foreach ((array) $attributes as $attribute) {
            if (! is_scalar($attribute)) {
                continue;
            }
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
                if ($count >= $limit) {
                    break 2;
                }
                $value = $node->attributes->getNamedItem($attribute)->nodeValue ?? '';
                $count += $this->discover_runtime_text((string) $value, $repository, $map, $seen, 'attribute_' . sanitize_key($attribute), $path . ':attr:' . $attribute . ':' . $count);
            }
        }

        return $count;
    }

    private function discover_runtime_text(string $text, TranslationRepository $repository, array $map, array &$seen, string $context, string $sourceKey): int
    {
        $normalized = StringNormalizer::normalize($text);
        if ($normalized === '' || isset($map[$normalized]) || isset($seen[$normalized]) || StringNormalizer::should_skip($normalized)) {
            return 0;
        }

        $seen[$normalized] = true;
        try {
            return $repository->upsert_string($normalized, 'runtime_buffer', 0, $context, $sourceKey) > 0 ? 1 : 0;
        } catch (\Throwable $e) {
            do_action('wat_log', 'warning', 'Runtime discovery overgeslagen na fout.', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function runtime_discovery_path_key(): string
    {
        $uri = Input::server_raw('REQUEST_URI');
        $path = Input::scalar_string(wp_parse_url($uri, PHP_URL_PATH));
        $path = $path !== '' ? $path : '/';
        return 'runtime:' . substr(hash('sha256', $path), 0, 16);
    }

    private function save_translation_dom(DOMDocument $dom, string $fallback): string
    {
        $output = $dom->saveHTML() ?: $fallback;
        return preg_replace('/^<\?xml encoding="utf-8" \?>/i', '', $output) ?: $output;
    }

    private function restore_libxml_state(bool $previous): void
    {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    private function apply_translation_replacement_filter(string $output, int $count): string
    {
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
        $loaded = $temporaryDom->loadHTML('<?xml encoding="utf-8" ?><body>' . $translation . '</body>', $this->dom_load_flags());
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

        return $this->harden_inline_translation_links(trim(wp_kses($translation, $allowed)));
    }

    private function harden_inline_translation_links(string $markup): string
    {
        if ($markup === '' || stripos($markup, '<a ') === false || stripos($markup, 'target=') === false) {
            return $markup;
        }

        return (string) preg_replace_callback("/<a\\s+([^>]*target\\s*=\\s*[\"\']?_blank[\"\']?[^>]*)>/i", static function (array $matches): string {
            $attributes = $matches[1];

            if (preg_match("/\\srel\\s*=\\s*([\"\'])(.*?)\\1/i", $attributes, $relMatches) === 1) {
                $rel = preg_split('/\s+/', strtolower(trim($relMatches[2]))) ?: [];
                foreach (['noopener', 'noreferrer'] as $required) {
                    if (! in_array($required, $rel, true)) {
                        $rel[] = $required;
                    }
                }
                $newRel = trim(implode(' ', array_filter($rel)));
                $attributes = preg_replace("/\\srel\\s*=\\s*([\"\'])(.*?)\\1/i", ' rel="' . esc_attr($newRel) . '"', $attributes, 1);

                return '<a ' . $attributes . '>';
            }

            return '<a ' . $attributes . ' rel="noopener noreferrer">';
        }, $markup);
    }

    private function html_language_tag(string $language): string
    {
        $language = trim(str_replace('_', '-', $language));
        if ($language === '') {
            return '';
        }

        $parts = array_values(array_filter(explode('-', $language), static fn(string $part): bool => $part !== ''));
        if ($parts === []) {
            return '';
        }

        $parts[0] = strtolower($parts[0]);
        if (isset($parts[1])) {
            $parts[1] = strtoupper($parts[1]);
        }

        return implode('-', $parts);
    }

    private function set_html_language(DOMXPath $xpath): void
    {
        $nodes = $xpath->query('/html');
        if ($nodes && $nodes->length > 0) {
            $html = $nodes->item(0);
            if ($html instanceof DOMElement) {
                $html->setAttribute('lang', $this->html_language_tag($this->language));
            }
        }
    }
}
