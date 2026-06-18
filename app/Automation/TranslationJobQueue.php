<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation;

if (! defined('ABSPATH')) {
    exit;
}

final class TranslationJobQueue
{
    public const TYPE_AI_TRANSLATION = 'ai_translation';
    private const MAX_BATCH_SIZE = TranslationJobLimits::MAX_BATCH_SIZE;
    private const MAX_AI_TEXT_LENGTH = TranslationJobLimits::MAX_AI_TEXT_LENGTH;

    /** @param array<string, mixed> $options */
    public static function enqueue(array $options): int
    {
        $payload = new TranslationJobPayload();
        $options = $payload->normalize_options($options);
        $totalItems = (new TranslationCandidateSelector())->count_candidates($options);

        return (new TranslationJobRepository($payload))->insert_ai_job($options, $totalItems);
    }

    /** @return array<string, mixed> */
    public static function capabilities(): array
    {
        return [
            'providers' => ['openai', 'deepl', 'openai_compatible', 'google_translate'],
            'queue_table' => \Webactueel\Translate\Database\Tables::jobs(),
            'worker_available' => true,
            'max_batch_size' => self::MAX_BATCH_SIZE,
            'max_text_length' => self::MAX_AI_TEXT_LENGTH,
            'review_first' => true,
            'status' => __('AI-batchvertaling is beschikbaar voor beheerders. Batches blijven klein, respecteren providerlimieten en slaan output standaard als review-vertaling op.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    /** @return array<string, mixed>|\WP_Error */
    public static function get_job(int $jobId)
    {
        return (new TranslationJobRepository())->get_ai_job($jobId);
    }

    /** @return array<string, mixed>|\WP_Error */
    public static function run_batch(int $jobId, int $batchSize = 5)
    {
        return (new TranslationBatchRunner())->run_batch($jobId, $batchSize);
    }
}
