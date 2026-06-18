<?php

declare(strict_types=1);

namespace Webactueel\Translate\Seo;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\UrlMapping;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Translation\TranslationCoverageReporter;

if (! defined('ABSPATH')) {
    exit;
}

final class SeoAuditService
{
    /** @return array<string, mixed> */
    public function report(): array
    {
        $settings = Settings::all();
        $checks = [
            $this->active_languages_check(),
            $this->hreflang_check($settings),
            $this->x_default_check($settings),
            $this->canonical_check($settings),
            $this->sitemap_check($settings),
            $this->alternate_urls_check(),
            $this->coverage_check(),
        ];
        $summary = $this->summary($checks);

        return [
            'ok' => $summary['fail'] === 0,
            'summary' => $summary,
            'checks' => $checks,
            'sitemap_url' => esc_url_raw(home_url('/?wat_language_sitemap=1')),
            'generated_at' => current_time('mysql'),
        ];
    }

    private function active_languages_check(): array
    {
        $languages = LanguageDetector::active_languages();
        $count = count($languages);

        return [
            'key' => 'active_languages',
            'status' => $count >= 2 ? 'pass' : 'warn',
            'label' => __('Actieve talen', 'webactueel-translate-language-dropdowns'),
            'detail' => $count >= 2
                ? sprintf(__('Er zijn %d actieve talen.', 'webactueel-translate-language-dropdowns'), $count)
                : __('Voeg minimaal één niet-standaardtaal toe voordat je meertalige SEO publiceert.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    /** @param array<string, mixed> $settings */
    private function hreflang_check(array $settings): array
    {
        return [
            'key' => 'hreflang_enabled',
            'status' => ! empty($settings['hreflang_enabled']) ? 'pass' : 'warn',
            'label' => __('Hreflang-output', 'webactueel-translate-language-dropdowns'),
            'detail' => ! empty($settings['hreflang_enabled'])
                ? __('Hreflang staat aan voor indexeerbare frontendpagina’s.', 'webactueel-translate-language-dropdowns')
                : __('Hreflang staat uit; internationale URL-relaties moeten dan buiten deze plugin worden beheerd.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    /** @param array<string, mixed> $settings */
    private function x_default_check(array $settings): array
    {
        return [
            'key' => 'x_default',
            'status' => ! empty($settings['x_default_enabled']) ? 'pass' : 'info',
            'label' => __('x-default fallback', 'webactueel-translate-language-dropdowns'),
            'detail' => ! empty($settings['x_default_enabled'])
                ? __('x-default is ingeschakeld voor fallback naar de standaardtaal.', 'webactueel-translate-language-dropdowns')
                : __('x-default staat uit; dit kan bewust zijn, maar controleer fallbackgedrag.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    /** @param array<string, mixed> $settings */
    private function canonical_check(array $settings): array
    {
        return [
            'key' => 'canonical_enabled',
            'status' => ! empty($settings['canonical_enabled']) ? 'pass' : 'warn',
            'label' => __('Per-taal canonical', 'webactueel-translate-language-dropdowns'),
            'detail' => ! empty($settings['canonical_enabled'])
                ? __('Per-taal canonical ondersteuning staat aan.', 'webactueel-translate-language-dropdowns')
                : __('Canonical-output staat uit; controleer canonicalconflicten met je SEO-plugin.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    /** @param array<string, mixed> $settings */
    private function sitemap_check(array $settings): array
    {
        return [
            'key' => 'multilingual_sitemap',
            'status' => ! empty($settings['multilingual_sitemap_enabled']) ? 'pass' : 'info',
            'label' => __('Meertalige sitemap', 'webactueel-translate-language-dropdowns'),
            'detail' => ! empty($settings['multilingual_sitemap_enabled'])
                ? esc_url_raw(home_url('/?wat_language_sitemap=1'))
                : __('Meertalige sitemap staat uit.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    private function alternate_urls_check(): array
    {
        $languages = LanguageDetector::active_languages();
        $urls = [];
        $seenCodes = [];
        foreach ($languages as $language) {
            $code = Input::key($language['code'] ?? '');
            if ($code === '' || isset($seenCodes[$code])) {
                continue;
            }
            $seenCodes[$code] = true;
            $urls[$code] = UrlMapping::url_for_path($code, '');
        }

        $invalid = [];
        foreach ($urls as $code => $url) {
            if (! wp_http_validate_url($url)) {
                $invalid[] = $code;
            }
        }

        return [
            'key' => 'alternate_home_urls',
            'status' => $invalid === [] ? 'pass' : 'warn',
            'label' => __('Homepage-alternates', 'webactueel-translate-language-dropdowns'),
            'detail' => $invalid === []
                ? __('Alle actieve talen hebben een absolute homepage-URL.', 'webactueel-translate-language-dropdowns')
                : sprintf(
                    /* translators: %s: comma-separated list of invalid language codes. */
                    __('Controleer de alternate URL voor: %s.', 'webactueel-translate-language-dropdowns'),
                    implode(', ', $invalid)
                ),
            'urls' => $urls,
        ];
    }

    private function coverage_check(): array
    {
        $coverage = TranslationCoverageReporter::summary();
        $average = (float) ($coverage['average_percent'] ?? 0.0);
        $status = $average >= 80.0 ? 'pass' : ($average > 0.0 ? 'warn' : 'info');

        return [
            'key' => 'translation_coverage',
            'status' => $status,
            'label' => __('Vertaaldekking', 'webactueel-translate-language-dropdowns'),
            'detail' => sprintf(
                /* translators: %s: average translation coverage percentage. */
                __('Gemiddelde dekking voor niet-standaardtalen: %s%%.', 'webactueel-translate-language-dropdowns'),
                number_format_i18n($average, 1)
            ),
            'coverage' => $coverage,
        ];
    }

    /** @param array<int, array<string, mixed>> $checks */
    private function summary(array $checks): array
    {
        $summary = ['pass' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
        foreach ($checks as $check) {
            $status = is_string($check['status'] ?? null) ? $check['status'] : 'info';
            if (array_key_exists($status, $summary)) {
                ++$summary[$status];
            }
        }

        return $summary;
    }
}
