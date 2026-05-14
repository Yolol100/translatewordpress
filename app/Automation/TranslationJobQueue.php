<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation;

use Webactueel\Translate\Database\Tables;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This plugin uses its own custom translation tables; queries are scoped and cache invalidation is handled by the plugin.

final class TranslationJobQueue
{
    public const TYPE_AI_TRANSLATION = 'ai_translation';

    /** @param array<string, mixed> $options */
    public static function enqueue(array $options): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $key = 'ai_' . wp_generate_uuid4();
        $wpdb->insert(Tables::jobs(), [
            'job_key' => $key,
            'type' => self::TYPE_AI_TRANSLATION,
            'status' => 'queued',
            'cursor_value' => 0,
            'total_items' => 0,
            'processed_items' => 0,
            'found_strings' => 0,
            'skipped_items' => 0,
            'errors_count' => 0,
            'options_json' => wp_json_encode($options),
            'message' => __('AI-vertaling staat in de wachtrij. Configureer eerst een provider voordat workers taken uitvoeren.', 'webactueel-translate-language-dropdowns'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    /** @return array<string, mixed> */
    public static function capabilities(): array
    {
        return [
            'providers' => ['deepl', 'openai', 'google', 'microsoft'],
            'queue_table' => Tables::jobs(),
            'worker_available' => false,
            'status' => __('Queue-basis is aanwezig; provider-connectors moeten expliciet worden geconfigureerd voordat automatische vertaling draait.', 'webactueel-translate-language-dropdowns'),
        ];
    }
}
