<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Translation\StringNormalizer;
use Webactueel\Translate\Translation\TranslationRepository;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

final class GettextStringDiscovery
{
    private bool $active = false;
    private int $registered = 0;
    private int $maxPerRequest = 50;
    private array $domains = [];
    private array $seen = [];
    private ?TranslationRepository $repository = null;

    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybe_register_filters'], -70);
    }

    public function maybe_register_filters(): void
    {
        $settings = Settings::all();
        if (empty($settings['gettext_discovery_enabled']) || ! $this->is_safe_frontend_request()) {
            return;
        }

        $language = LanguageDetector::current_language();
        if ($language === '' || LanguageDetector::is_default_language($language)) {
            return;
        }

        $this->maxPerRequest = max(1, min(500, Input::absint($settings['gettext_discovery_max_per_request'] ?? 50, 50)));
        $this->domains = $this->allowed_domains(Input::scalar_string($settings['gettext_discovery_domains'] ?? ''));
        if ($this->domains === []) {
            return;
        }

        add_filter('gettext', [$this, 'filter_gettext'], 20, 3);
        add_filter('gettext_with_context', [$this, 'filter_gettext_with_context'], 20, 4);
        add_filter('ngettext', [$this, 'filter_ngettext'], 20, 5);
        add_filter('ngettext_with_context', [$this, 'filter_ngettext_with_context'], 20, 6);
    }

    public function filter_gettext(string $translation, string $text, string $domain): string
    {
        return $this->discover_and_translate($translation, $text, $domain, 'gettext', '');
    }

    public function filter_gettext_with_context(string $translation, string $text, string $context, string $domain): string
    {
        return $this->discover_and_translate($translation, $text, $domain, 'gettext_context', $context);
    }

    public function filter_ngettext(string $translation, string $single, string $plural, int $number, string $domain): string
    {
        $source = $number === 1 ? $single : $plural;
        return $this->discover_and_translate($translation, $source, $domain, 'ngettext', 'count:' . $number);
    }

    public function filter_ngettext_with_context(string $translation, string $single, string $plural, int $number, string $context, string $domain): string
    {
        $source = $number === 1 ? $single : $plural;
        return $this->discover_and_translate($translation, $source, $domain, 'ngettext_context', trim($context . '|count:' . $number, '|'));
    }

    private function discover_and_translate(string $translation, string $source, string $domain, string $kind, string $context): string
    {
        if ($this->active || ! $this->is_allowed_domain($domain)) {
            return $translation;
        }

        $candidate = $this->displayed_source_text($translation, $source);
        if (StringNormalizer::should_skip($candidate)) {
            return $translation;
        }

        $language = LanguageDetector::current_language();
        if ($language === '' || LanguageDetector::is_default_language($language)) {
            return $translation;
        }

        $normalized = StringNormalizer::normalize($candidate);
        $seenKey = $domain . '|' . $kind . '|' . $context . '|' . $normalized;
        if (isset($this->seen[$seenKey])) {
            return $this->translated_or_original($candidate, $translation, $language);
        }
        $this->seen[$seenKey] = true;

        if ($this->registered < $this->maxPerRequest) {
            $this->active = true;
            try {
                $this->repository()->upsert_string(
                    $candidate,
                    'gettext',
                    0,
                    sanitize_key($kind),
                    $this->source_key($domain, $context, $source)
                );
                $this->registered++;
            } catch (\Throwable $e) {
                do_action('wat_log', 'warning', 'Gettext discovery overgeslagen na fout.', ['error' => $e->getMessage(), 'domain' => $domain]);
            } finally {
                $this->active = false;
            }
        }

        return $this->translated_or_original($candidate, $translation, $language);
    }

    private function displayed_source_text(string $translation, string $source): string
    {
        $candidate = trim(wp_strip_all_tags(html_entity_decode($translation, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($candidate === '') {
            $candidate = trim(wp_strip_all_tags(html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }
        return $candidate;
    }

    private function translated_or_original(string $candidate, string $translation, string $language): string
    {
        $translated = $this->repository()->translate_text($candidate, $language);
        return $translated !== $candidate && $translated !== '' ? $translated : $translation;
    }

    private function source_key(string $domain, string $context, string $source): string
    {
        $domain = sanitize_key($domain !== '' ? $domain : 'default');
        $contextHash = $context !== '' ? substr(hash('sha256', sanitize_text_field($context)), 0, 12) : 'default';
        return 'gettext:' . $domain . ':' . $contextHash . ':' . substr(hash('sha256', $source), 0, 16);
    }

    private function allowed_domains(string $raw): array
    {
        $domains = [];
        foreach (preg_split('/\r\n|\r|\n|,/', $raw) ?: [] as $line) {
            $domain = sanitize_key(trim((string) $line));
            if ($domain !== '') {
                $domains[$domain] = true;
            }
        }

        $filtered = apply_filters('wat_gettext_discovery_allowed_domains', array_keys($domains));
        if (! is_array($filtered)) {
            return array_keys($domains);
        }

        $clean = [];
        foreach ($filtered as $domain) {
            if (! is_scalar($domain)) {
                continue;
            }
            $domain = sanitize_key((string) $domain);
            if ($domain !== '') {
                $clean[$domain] = true;
            }
        }
        return array_keys($clean);
    }

    private function is_allowed_domain(string $domain): bool
    {
        $domain = sanitize_key($domain !== '' ? $domain : 'default');
        return in_array($domain, $this->domains, true);
    }

    private function is_safe_frontend_request(): bool
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return false;
        }
        if ((defined('REST_REQUEST') && REST_REQUEST) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
            return false;
        }
        return Input::server_method() === 'GET';
    }

    private function repository(): TranslationRepository
    {
        if ($this->repository === null) {
            $this->repository = new TranslationRepository();
        }
        return $this->repository;
    }
}
