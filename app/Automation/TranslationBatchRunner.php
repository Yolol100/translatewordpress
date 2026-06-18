<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation;

use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Translation\TranslationRepository;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

final class TranslationBatchRunner
{
    use ValidatesLanguages;

    private TranslationJobRepository $jobs;
    private TranslationCandidateSelector $candidates;
    private TranslationJobPayload $payload;

    public function __construct(
        ?TranslationJobRepository $jobs = null,
        ?TranslationCandidateSelector $candidates = null,
        ?TranslationJobPayload $payload = null
    ) {
        $this->payload = $payload ?: new TranslationJobPayload();
        $this->jobs = $jobs ?: new TranslationJobRepository($this->payload);
        $this->candidates = $candidates ?: new TranslationCandidateSelector();
    }

    /** @return array<string, mixed>|WP_Error */
    public function run_batch(int $jobId, int $batchSize = 5)
    {
        $job = $this->jobs->get_ai_job($jobId);
        if (is_wp_error($job)) {
            return $job;
        }

        if ($this->payload->is_terminal_job($job)) {
            return $job;
        }

        $options = $this->payload->normalize_options(is_array($job['options'] ?? null) ? $job['options'] : []);
        if (! $this->ensure_target_language($jobId, $options)) {
            return $this->jobs->get_ai_job($jobId);
        }

        $batchSize = $this->payload->resolve_batch_size($batchSize, $options);
        $cursor = absint($job['cursor_value'] ?? 0);

        if (! $this->jobs->claim_job_cursor($jobId, $cursor)) {
            return $this->jobs->get_ai_job($jobId);
        }

        $items = $this->candidates->candidate_strings($options, $cursor, $batchSize);
        if ($items === []) {
            $this->jobs->complete_job($jobId, __('AI-batch afgerond. Er zijn geen openstaande strings meer voor deze selectie.', 'webactueel-translate-language-dropdowns'));
            return $this->jobs->get_ai_job($jobId);
        }

        $summary = $this->process_batch_items($items, $options, $jobId, $cursor);
        $this->jobs->update_job($jobId, $this->payload->batch_update_payload($job, $summary, $batchSize, count($items)));

        return $this->jobs->get_ai_job($jobId);
    }

    /** @param array<string, mixed> $options */
    private function ensure_target_language(int $jobId, array $options): bool
    {
        if ($this->is_translatable_language(Input::key($options['target_language'] ?? ''))) {
            return true;
        }

        $this->jobs->update_job($jobId, [
            'status' => 'failed',
            'message' => __('Kies een actieve niet-standaardtaal voordat je de AI-batch uitvoert.', 'webactueel-translate-language-dropdowns'),
            'updated_at' => current_time('mysql'),
        ]);

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $options
     * @return array{processed:int,memory_reused:int,skipped:int,errors:int,last_cursor:int,stop_message:string}
     */
    private function process_batch_items(array $items, array $options, int $jobId, int $cursor): array
    {
        $repository = new TranslationRepository();
        $ai = new AiTranslationService();
        $summary = [
            'processed' => 0,
            'memory_reused' => 0,
            'skipped' => 0,
            'errors' => 0,
            'last_cursor' => $cursor,
            'stop_message' => '',
        ];

        foreach ($items as $item) {
            $itemResult = $this->process_batch_item($item, $options, $jobId, $repository, $ai);
            $status = Input::key($itemResult['status'] ?? '');

            if ($status !== 'paused') {
                $summary['last_cursor'] = max($summary['last_cursor'], absint($itemResult['cursor'] ?? 0));
            }

            if (! empty($itemResult['memory_reused'])) {
                ++$summary['memory_reused'];
            }

            if ($status === 'processed') {
                ++$summary['processed'];
                continue;
            }

            if ($status === 'skipped') {
                ++$summary['skipped'];
                continue;
            }

            ++$summary['errors'];
            if ($status === 'paused') {
                $summary['stop_message'] = Input::scalar_string($itemResult['message'] ?? '');
                break;
            }
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function process_batch_item(array $item, array $options, int $jobId, TranslationRepository $repository, AiTranslationService $ai): array
    {
        $stringId = absint($item['id'] ?? 0);
        $original = Input::scalar_string($item['original_text'] ?? '');

        if ($stringId <= 0 || $original === '' || $this->string_length($original) > TranslationJobLimits::MAX_AI_TEXT_LENGTH) {
            return ['status' => 'skipped', 'cursor' => $stringId];
        }

        $translation = $this->resolve_batch_translation($repository, $ai, $options, $jobId, $stringId, $original);
        if (is_wp_error($translation)) {
            return ['status' => 'paused', 'message' => $translation->get_error_message()];
        }

        $translated = Input::scalar_string($translation['translated_text'] ?? '');
        $reviewStatus = Input::key($translation['review_status'] ?? 'needs_review') ?: 'needs_review';
        $origin = Input::key($translation['origin'] ?? 'ai') ?: 'ai';
        $targetLanguage = Input::key($options['target_language'] ?? '');

        if ($translated === '' || ! $repository->save_translation($stringId, $targetLanguage, $translated, $reviewStatus, $origin)) {
            return [
                'status' => 'error',
                'cursor' => $stringId,
                'memory_reused' => ! empty($translation['memory_reused']),
            ];
        }

        return [
            'status' => 'processed',
            'cursor' => $stringId,
            'memory_reused' => ! empty($translation['memory_reused']),
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>|WP_Error
     */
    private function resolve_batch_translation(TranslationRepository $repository, AiTranslationService $ai, array $options, int $jobId, int $stringId, string $original)
    {
        $targetLanguage = Input::key($options['target_language'] ?? '');
        $memoryMatch = $repository->find_translation_memory_match($original, $targetLanguage);
        if ($memoryMatch !== []) {
            $translated = Input::scalar_string($memoryMatch['translated_text'] ?? '');
            AiUsageLedger::record([
                'job_id' => $jobId,
                'string_id' => $stringId,
                'provider' => 'memory',
                'model' => 'exact-normalized-match',
                'source_language' => Input::key($options['source_language'] ?? ''),
                'target_language' => $targetLanguage,
                'source_text' => $original,
                'translated_text' => $translated,
                'memory_reused' => true,
                'glossary_terms' => 0,
            ]);

            return [
                'translated_text' => $translated,
                'review_status' => 'reviewed',
                'origin' => 'memory',
                'memory_score' => absint($memoryMatch['score'] ?? 100),
                'memory_reused' => true,
            ];
        }

        return $ai->translate(
            $original,
            Input::key($options['source_language'] ?? ''),
            $targetLanguage,
            ['job_id' => $jobId, 'string_id' => $stringId, 'batch' => true]
        );
    }

    private function string_length(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }
}
