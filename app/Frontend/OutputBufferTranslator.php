<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend;

use Webactueel\Translate\Frontend\Concerns\OutputBufferDomTranslation;
use Webactueel\Translate\Frontend\Concerns\OutputBufferExclusions;
use Webactueel\Translate\Frontend\Concerns\OutputBufferRequestGuards;
use Webactueel\Translate\Frontend\Concerns\OutputBufferUrlRewriter;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Translation\TranslationRepository;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hooks intentionally use the plugin prefix wat_ for the public extension API.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: custom prefixed tables and public wat_* hooks are intentional.

final class OutputBufferTranslator
{
    use OutputBufferDomTranslation;
    use OutputBufferExclusions;
    use OutputBufferRequestGuards;
    use OutputBufferUrlRewriter;

    private string $language;
    private array $settings;
    private bool $started = false;
    private int $lastReplacementCount = 0;
    private int $lastUrlRewriteCount = 0;

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
            if (! $this->is_html_response($html)) {
                return $html;
            }
            if (strpos($html, 'wat:disable-output-translation') !== false) {
                return $html;
            }

            $requestContext = $this->request_context();
            if (! (bool) apply_filters('wat_enable_output_buffer_translation', true, $this->language, $this->settings, $requestContext)) {
                return $html;
            }
            if ((bool) apply_filters('wat_skip_output_translation_for_request', false, $requestContext, $this->language, $this->settings)) {
                return $html;
            }

            if (! class_exists('DOMDocument') || ! class_exists('DOMXPath')) {
                do_action('wat_log', 'warning', 'Frontend translation skipped because PHP DOM/ext-xml is unavailable.');
                return $html;
            }
            $configuredMax = Input::absint($this->settings['max_buffer_size'] ?? 0);
            if ($configuredMax < 1) {
                $configuredMax = 2097152;
            }
            $max = max(1, (int) apply_filters('wat_html_max_buffer_size', $configuredMax));
            if (strlen($html) > $max) {
                return $html;
            }
            $startedAt = microtime(true);
            $map = (new TranslationRepository())->translation_map($this->language);
            // Even when there are no saved translations yet, still run a lightweight DOM pass
            // so internal links/forms keep the selected language prefix while browsing.
            $output = $this->translate_dom($html, $map);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $metrics = [
                'language' => $this->language,
                'duration_ms' => $durationMs,
                'bytes' => strlen($html),
                'map_size' => count($map),
                'replacement_count' => $this->lastReplacementCount,
                'url_rewrite_count' => $this->lastUrlRewriteCount,
                'request_path' => Input::scalar_string($requestContext['path'] ?? ''),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            ];
            do_action('wat_output_buffer_translation_metrics', $metrics, $output, $html);

            $slowThreshold = (int) apply_filters('wat_slow_buffer_threshold_ms', 250, $this->language, $this->settings);
            if ($slowThreshold > 0 && $durationMs > $slowThreshold) {
                do_action('wat_log', 'debug', 'Frontend translation buffer processed slowly.', $metrics);
            }
            return $output;
        } catch (\Throwable $e) {
            do_action('wat_log', 'warning', 'Frontend translation skipped after parser error', ['error' => $e->getMessage()]);
            return $html;
        }
    }
}
