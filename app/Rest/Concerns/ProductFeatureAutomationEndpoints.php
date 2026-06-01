<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Automation\AiTranslationService;
use Webactueel\Translate\Automation\AiUsageLedger;
use Webactueel\Translate\Automation\TranslationJobQueue;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;
use WP_REST_Request;

if (! defined('ABSPATH')) {
    exit;
}

trait ProductFeatureAutomationEndpoints
{
    /**
     * Estimate remaining AI batch volume without using provider pricing claims.
     *
     * @return array<string, mixed>
     */
    public function ai_cost_estimate(): array
    {
        global $wpdb;
        $stringsTable = Tables::strings();
        $translationsTable = Tables::translations();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only admin estimate on plugin-owned custom tables.
        $missing = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i s LEFT JOIN %i t ON t.string_id = s.id WHERE t.id IS NULL',
                $stringsTable,
                $translationsTable
            )
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only admin estimate on plugin-owned custom tables.
        $sample = (array) $wpdb->get_col(
            $wpdb->prepare('SELECT original_text FROM %i ORDER BY id DESC LIMIT 100', $stringsTable)
        );
        $characters = 0;
        foreach ($sample as $text) {
            $characters += function_exists('mb_strlen') ? mb_strlen((string) $text) : strlen((string) $text);
        }
        $average = $sample !== [] ? (int) ceil($characters / max(1, count($sample))) : 0;
        $estimatedCharacters = $missing * $average;

        return [
            'missing_strings' => $missing,
            'sample_size' => count($sample),
            'average_source_characters' => $average,
            'estimated_source_characters' => $estimatedCharacters,
            'estimated_tokens_note' => __('Gebruik dit als ruwe volume-indicatie. Werkelijke tokenkosten verschillen per provider, taal, model en prompt.', 'webactueel-translate-language-dropdowns'),
            'recommended_batch_size' => min(20, max(1, (int) apply_filters('wat_ai_recommended_batch_size', 10))),
            'review_required' => ! empty(Settings::all()['ai_review_required']),
        ];
    }

    public function automation_capabilities(): array
    {
        return array_merge(TranslationJobQueue::capabilities(), ['ai' => AiTranslationService::capabilities()]);
    }

    public function ai_translate(WP_REST_Request $request)
    {
        $params = $request->get_params();
        return (new AiTranslationService())->translate(
            Input::scalar_string($params['text'] ?? ''),
            Input::key($params['source_language'] ?? ''),
            Input::key($params['target_language'] ?? ''),
            $params
        );
    }

    public function ai_usage(WP_REST_Request $request): array
    {
        return ['usage' => AiUsageLedger::summary(absint($request->get_param('days') ?: 30))];
    }

    public function ai_job_enqueue(WP_REST_Request $request)
    {
        $jobId = TranslationJobQueue::enqueue($request->get_params());
        $job = TranslationJobQueue::get_job($jobId);
        return is_wp_error($job) ? $job : ['job' => $job];
    }

    public function ai_job(WP_REST_Request $request)
    {
        $job = TranslationJobQueue::get_job(absint($request['id']));
        return is_wp_error($job) ? $job : ['job' => $job];
    }

    public function ai_job_run_batch(WP_REST_Request $request)
    {
        $job = TranslationJobQueue::run_batch(absint($request['id']), absint($request->get_param('batch_size') ?: 5));
        return is_wp_error($job) ? $job : ['job' => $job];
    }
}
