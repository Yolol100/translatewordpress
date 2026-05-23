<?php

declare(strict_types=1);

namespace Webactueel\Translate\Performance;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

final class PerformanceMonitor
{
    public const OPTION = 'wat_performance_snapshot';

    public function register(): void
    {
        add_action('wat_output_buffer_translation_metrics', [$this, 'capture'], 10, 3);
        add_action('send_headers', [$this, 'server_timing']);
    }

    /**
     * @param array<string, mixed> $metrics
     */
    public function capture(array $metrics, string $output, string $input): void
    {
        $settings = Settings::all();
        if (empty($settings['performance_monitoring'])) {
            return;
        }

        $snapshot = [
            'captured_at' => current_time('mysql'),
            'language' => Input::key($metrics['language'] ?? ''),
            'duration_ms' => Input::absint($metrics['duration_ms'] ?? 0),
            'bytes' => Input::absint($metrics['bytes'] ?? strlen($input)),
            'output_bytes' => strlen($output),
            'map_size' => Input::absint($metrics['map_size'] ?? 0),
            'replacement_count' => Input::absint($metrics['replacement_count'] ?? 0),
            'url_rewrite_count' => Input::absint($metrics['url_rewrite_count'] ?? 0),
            'request_path' => sanitize_text_field(Input::scalar_string($metrics['request_path'] ?? '')),
            'memory_peak_mb' => (float) ($metrics['memory_peak_mb'] ?? 0),
        ];
        update_option(self::OPTION, $snapshot, false);
    }

    public function server_timing(): void
    {
        if (headers_sent() || ! current_user_can('manage_options')) {
            return;
        }
        $snapshot = get_option(self::OPTION, []);
        if (! is_array($snapshot) || empty($snapshot['duration_ms'])) {
            return;
        }
        header('Server-Timing: wat;dur=' . absint($snapshot['duration_ms']) . ';desc="Webactueel Translate"', false);
    }

    /** @return array<string, mixed> */
    public static function snapshot(): array
    {
        $snapshot = get_option(self::OPTION, []);
        return is_array($snapshot) ? $snapshot : [];
    }
}
