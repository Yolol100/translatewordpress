<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation;

use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

final class TranslationJobPayload
{
    private const VALID_STATUSES = ['new', 'missing', 'draft', 'needs_review', 'reviewed', 'published', 'outdated'];

    /** @param array<string, mixed> $options @return array<string, mixed> */
    public function normalize_options(array $options): array
    {
        $status = Input::key($options['status'] ?? 'new');
        if (! in_array($status, self::VALID_STATUSES, true)) {
            $status = 'new';
        }

        return [
            'target_language' => Input::key($options['target_language'] ?? ''),
            'source_language' => Input::key($options['source_language'] ?? ''),
            'status' => $status,
            'batch_size' => max(1, min(TranslationJobLimits::MAX_BATCH_SIZE, absint($options['batch_size'] ?? 5))),
            'assigned_user_id' => absint($options['assigned_user_id'] ?? 0),
            'due_at' => $this->normalize_due_at(Input::scalar_string($options['due_at'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $job */
    public function is_terminal_job(array $job): bool
    {
        return in_array(Input::key($job['status'] ?? ''), ['completed', 'cancelled'], true);
    }

    /** @param array<string, mixed> $options */
    public function resolve_batch_size(int $batchSize, array $options): int
    {
        return max(1, min(TranslationJobLimits::MAX_BATCH_SIZE, absint($batchSize ?: ($options['batch_size'] ?? 5))));
    }

    /**
     * @param array<string, mixed> $job
     * @param array{processed:int,memory_reused:int,skipped:int,errors:int,last_cursor:int,stop_message:string} $summary
     * @return array<string, mixed>
     */
    public function batch_update_payload(array $job, array $summary, int $batchSize, int $itemCount): array
    {
        $newProcessed = absint($job['processed_items'] ?? 0) + $summary['processed'];
        $newSkipped = absint($job['skipped_items'] ?? 0) + $summary['skipped'];
        $newErrors = absint($job['errors_count'] ?? 0) + $summary['errors'];
        $stopMessage = $summary['stop_message'];
        $nextStatus = $stopMessage !== '' ? 'paused' : 'running';
        $message = $stopMessage !== ''
            ? sprintf(
                /* translators: %s: provider or queue error message. */
                __('AI-batch gepauzeerd: %s', 'webactueel-translate-language-dropdowns'),
                sanitize_text_field($stopMessage)
            )
            : sprintf(
                /* translators: 1: processed translations, 2: skipped items, 3: errors, 4: memory matches. */
                __('Laatste batch verwerkt: %1$d vertaald, %2$d overgeslagen, %3$d fouten, %4$d uit vertaalgeheugen.', 'webactueel-translate-language-dropdowns'),
                $summary['processed'],
                $summary['skipped'],
                $summary['errors'],
                $summary['memory_reused']
            );

        if ($stopMessage === '' && $itemCount < $batchSize) {
            $nextStatus = 'completed';
            $message = __('AI-batch afgerond. Controleer de gegenereerde vertalingen voordat je ze publiceert.', 'webactueel-translate-language-dropdowns');
        }

        return [
            'status' => $nextStatus,
            'cursor_value' => $summary['last_cursor'],
            'processed_items' => $newProcessed,
            'skipped_items' => $newSkipped,
            'errors_count' => $newErrors,
            'message' => $message,
            'updated_at' => current_time('mysql'),
            'completed_at' => $nextStatus === 'completed' ? current_time('mysql') : null,
        ];
    }

    /** @param array<string, mixed> $options */
    public function encode_options(array $options): string
    {
        $encoded = wp_json_encode($options);
        return is_string($encoded) ? $encoded : '{}';
    }

    /** @return array<string, mixed> */
    public function decode_options(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function normalize_due_at(string $dueAt): ?string
    {
        $dueAt = trim(sanitize_text_field($dueAt));
        if ($dueAt === '') {
            return null;
        }
        $timestamp = strtotime($dueAt);
        if ($timestamp === false) {
            return null;
        }
        return gmdate('Y-m-d H:i:s', $timestamp + ((int) (get_option('gmt_offset', 0) * HOUR_IN_SECONDS)));
    }
}
