<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation;

use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Input;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic SQL only uses plugin-owned table identifiers and allow-listed clauses.

final class TranslationJobRepository
{
    private TranslationJobPayload $payload;

    public function __construct(?TranslationJobPayload $payload = null)
    {
        $this->payload = $payload ?: new TranslationJobPayload();
    }

    /** @param array<string, mixed> $options */
    public function insert_ai_job(array $options, int $totalItems): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $key = 'ai_' . wp_generate_uuid4();

        $wpdb->insert(Tables::jobs(), [
            'job_key' => $key,
            'type' => TranslationJobQueue::TYPE_AI_TRANSLATION,
            'status' => 'queued',
            'cursor_value' => 0,
            'total_items' => $totalItems,
            'processed_items' => 0,
            'found_strings' => $totalItems,
            'skipped_items' => 0,
            'errors_count' => 0,
            'assigned_user_id' => absint($options['assigned_user_id'] ?? 0),
            'due_at' => $this->payload->normalize_due_at(Input::scalar_string($options['due_at'] ?? '')),
            'options_json' => $this->payload->encode_options($options),
            'message' => sprintf(
                /* translators: %d: number of strings queued for translation. */
                __('AI-batch staat in de wachtrij met %d vertaalbare strings. Start kleine batches en review vertalingen voordat je publiceert.', 'webactueel-translate-language-dropdowns'),
                $totalItems
            ),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    /** @return array<string, mixed>|WP_Error */
    public function get_ai_job(int $jobId)
    {
        global $wpdb;
        $table = Tables::jobs();
        $job = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM %i WHERE id = %d AND type = %s", $table, absint($jobId), TranslationJobQueue::TYPE_AI_TRANSLATION),
            ARRAY_A
        );
        if (! is_array($job)) {
            return new WP_Error('wat_ai_job_not_found', __('AI-vertaaltaak niet gevonden.', 'webactueel-translate-language-dropdowns'), ['status' => 404]);
        }

        $job['id'] = absint($job['id'] ?? 0);
        $job['cursor_value'] = absint($job['cursor_value'] ?? 0);
        $job['total_items'] = absint($job['total_items'] ?? 0);
        $job['processed_items'] = absint($job['processed_items'] ?? 0);
        $job['found_strings'] = absint($job['found_strings'] ?? 0);
        $job['skipped_items'] = absint($job['skipped_items'] ?? 0);
        $job['errors_count'] = absint($job['errors_count'] ?? 0);
        $job['assigned_user_id'] = absint($job['assigned_user_id'] ?? 0);
        $job['due_at'] = Input::scalar_string($job['due_at'] ?? '');
        $job['options'] = $this->payload->decode_options(Input::scalar_string($job['options_json'] ?? '{}'));
        unset($job['options_json']);

        return $job;
    }

    public function claim_job_cursor(int $jobId, int $cursor): bool
    {
        global $wpdb;
        $now = current_time('mysql');
        // Atomic claim: only one concurrent request may advance this job at this cursor.
        // A second parallel run-batch sees 0 affected rows and bails, preventing
        // duplicate translations, double provider billing and corrupted counters.
        $claimed = $wpdb->query($wpdb->prepare(
            'UPDATE %i SET status = %s, started_at = COALESCE(started_at, %s), updated_at = %s WHERE id = %d AND type = %s AND status IN (%s, %s, %s) AND cursor_value = %d',
            Tables::jobs(),
            'running',
            $now,
            $now,
            absint($jobId),
            TranslationJobQueue::TYPE_AI_TRANSLATION,
            'queued',
            'running',
            'paused',
            $cursor
        ));

        return $claimed !== false && (int) $claimed !== 0;
    }

    /** @param array<string, mixed> $data */
    public function update_job(int $jobId, array $data): void
    {
        global $wpdb;
        $wpdb->update(Tables::jobs(), $data, ['id' => absint($jobId), 'type' => TranslationJobQueue::TYPE_AI_TRANSLATION]);
    }

    public function complete_job(int $jobId, string $message): void
    {
        $this->update_job($jobId, [
            'status' => 'completed',
            'message' => $message,
            'updated_at' => current_time('mysql'),
            'completed_at' => current_time('mysql'),
        ]);
    }
}
