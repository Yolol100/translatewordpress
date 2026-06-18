<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend;

use Webactueel\Translate\Frontend\Concerns\OutputBufferDomTranslation;
use Webactueel\Translate\Frontend\Concerns\OutputBufferExclusions;
use Webactueel\Translate\Frontend\Concerns\OutputBufferUrlRewriter;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Translation\TranslationRepository;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

final class OutputBufferTranslator
{
    use OutputBufferDomTranslation;
    use OutputBufferExclusions;
    use OutputBufferUrlRewriter;

    private string $language;
    private array $settings;
    private bool $started = false;
    private int $lastReplacementCount = 0;
    private int $lastUrlRewriteCount = 0;
    private int $lastRuntimeDiscoveryCount = 0;

    public function __construct(string $language, array $settings)
    {
        $this->language = sanitize_key($language);
        $this->settings = $settings;
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }
        $this->started = true;
        ob_start([$this, 'translate_buffer']);
    }

    /**
     * Translate a completed HTML response and return the original HTML on any uncertainty.
     *
     * The fail-open behavior is intentional: a translation parser problem should never
     * break page rendering, checkout, or form submission.
     */
    public function translate_buffer(string $html): string
    {
        try {
            $requestContext = [];
            if (! $this->can_translate_buffer($html, $requestContext)) {
                return $html;
            }

            $startedAt = microtime(true);
            $map = (new TranslationRepository())->translation_map($this->language);
            // Even when there are no saved translations yet, still run a lightweight DOM pass
            // so internal links/forms keep the selected language prefix while browsing.
            $output = $this->translate_dom($html, $map);
            $metrics = $this->buffer_metrics($html, $map, $requestContext, $startedAt);
            do_action('wat_output_buffer_translation_metrics', $metrics, $output, $html);
            $this->log_slow_buffer_if_needed($metrics);

            return $output;
        } catch (\Throwable $e) {
            do_action('wat_log', 'warning', 'Frontend translation skipped after parser error', ['error' => $e->getMessage()]);
            return $html;
        }
    }

    private function can_translate_buffer(string $html, array &$requestContext): bool
    {
        if (! $this->is_html_response($html) || strpos($html, 'wat:disable-output-translation') !== false || strpos($html, "\0") !== false) {
            return false;
        }

        $requestContext = $this->request_context();
        if (! (bool) apply_filters('wat_enable_output_buffer_translation', true, $this->language, $this->settings, $requestContext)) {
            return false;
        }
        if ((bool) apply_filters('wat_skip_output_translation_for_request', false, $requestContext, $this->language, $this->settings)) {
            return false;
        }
        if (! $this->dom_extension_available()) {
            do_action('wat_log', 'warning', 'Frontend translation skipped because PHP DOM/ext-xml is unavailable.');
            return false;
        }

        return strlen($html) <= $this->max_buffer_size();
    }

    private function dom_extension_available(): bool
    {
        return class_exists('DOMDocument') && class_exists('DOMXPath');
    }

    private function max_buffer_size(): int
    {
        $configuredMax = Input::absint($this->settings['max_buffer_size'] ?? 0);
        if ($configuredMax < 1) {
            $configuredMax = 2097152;
        }

        return max(1, (int) apply_filters('wat_html_max_buffer_size', $configuredMax));
    }

    private function buffer_metrics(string $html, array $map, array $requestContext, float $startedAt): array
    {
        return [
            'language' => $this->language,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'bytes' => strlen($html),
            'map_size' => count($map),
            'replacement_count' => $this->lastReplacementCount,
            'url_rewrite_count' => $this->lastUrlRewriteCount,
            'runtime_discovery_count' => $this->lastRuntimeDiscoveryCount,
            'request_path' => Input::scalar_string($requestContext['path'] ?? ''),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ];
    }

    private function log_slow_buffer_if_needed(array $metrics): void
    {
        $slowThreshold = (int) apply_filters('wat_slow_buffer_threshold_ms', 250, $this->language, $this->settings);
        if ($slowThreshold > 0 && (int) ($metrics['duration_ms'] ?? 0) > $slowThreshold) {
            do_action('wat_log', 'debug', 'Frontend translation buffer processed slowly.', $metrics);
        }
    }
    /**
     * @return array<string, string|bool>
     */
    private function request_context(): array
    {
        $uri = Input::server_raw('REQUEST_URI');
        $path = Input::scalar_string(wp_parse_url($uri, PHP_URL_PATH));

        return [
            'uri' => $uri,
            'path' => $path,
            'method' => Input::server_method(),
            'is_user_logged_in' => function_exists('is_user_logged_in') ? is_user_logged_in() : false,
            'is_admin_bar_showing' => function_exists('is_admin_bar_showing') ? is_admin_bar_showing() : false,
        ];
    }

    private function is_html_response(string $html): bool
    {
        if ($html === '' || (stripos($html, '<html') === false && stripos($html, '<body') === false)) {
            return false;
        }
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) {
                return false;
            }
        }
        return true;
    }

}
