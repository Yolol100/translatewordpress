<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation;

use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Translation\TranslationRepository;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic SQL only uses plugin-owned table identifiers and allow-listed clauses.

final class TranslationJobQueue
{
    use ValidatesLanguages;

    public const TYPE_AI_TRANSLATION = 'ai_translation';
    private const MAX_BATCH_SIZE = 20;
    private const MAX_AI_TEXT_LENGTH = 5000;

    /** @param array<string, mixed> $options */
    public static function enqueue(array $options): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $key = 'ai_' . wp_generate_uuid4();
        $options = self::normalize_options($options);
        $totalItems = self::count_candidates($options);

        $wpdb->insert(Tables::jobs(), [
            'job_key' => $key,
            'type' => self::TYPE_AI_TRANSLATION,
            'status' => 'queued',
            'cursor_value' => 0,
            'total_items' => $totalItems,
            'processed_items' => 0,
            'found_strings' => $totalItems,
            'skipped_items' => 0,
            'errors_count' => 0,
            'assigned_user_id' => absint($options['assigned_user_id'] ?? 0),
            'due_at' => self::normalize_due_at(Input::scalar_string($options['due_at'] ?? '')),
            'options_json' => self::encode_options($options),
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

    /** @return array<string, mixed> */
    public static function capabilities(): array
    {
        return [
            'providers' => ['openai', 'deepl', 'openai_compatible'],
            'queue_table' => Tables::jobs(),
            'worker_available' => true,
            'max_batch_size' => self::MAX_BATCH_SIZE,
            'max_text_length' => self::MAX_AI_TEXT_LENGTH,
            'review_first' => true,
            'status' => __('AI-batchvertaling is beschikbaar voor beheerders. Batches blijven klein, respecteren providerlimieten en slaan output standaard als review-vertaling op.', 'webactueel-translate-language-dropdowns'),
        ];
    }

    /** @return array<string, mixed>|WP_Error */
    public static function get_job(int $jobId)
    {
        global $wpdb;
        $table = Tables::sql_identifier(Tables::jobs());
        $job = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d AND type = %s", absint($jobId), self::TYPE_AI_TRANSLATION),
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
        $job['options'] = self::decode_options(Input::scalar_string($job['options_json'] ?? '{}'));
        unset($job['options_json']);

        return $job;
    }

    /** @return array<string, mixed>|WP_Error */
    public static function run_batch(int $jobId, int $batchSize = 5)
    {
        $job = self::get_job($jobId);
        if (is_wp_error($job)) {
            return $job;
        }

        $status = Input::key($job['status'] ?? '');
        if (in_array($status, ['completed', 'cancelled'], true)) {
            return $job;
        }

        $options = self::normalize_options(is_array($job['options'] ?? null) ? $job['options'] : []);
        $validator = new self();
        if (! $validator->is_translatable_language(Input::key($options['target_language'] ?? ''))) {
            self::update_job($jobId, [
                'status' => 'failed',
                'message' => __('Kies een actieve niet-standaardtaal voordat je de AI-batch uitvoert.', 'webactueel-translate-language-dropdowns'),
                'updated_at' => current_time('mysql'),
            ]);
            return self::get_job($jobId);
        }

        $batchSize = max(1, min(self::MAX_BATCH_SIZE, absint($batchSize ?: ($options['batch_size'] ?? 5))));
        $cursor = absint($job['cursor_value'] ?? 0);

        global $wpdb;
        $now = current_time('mysql');
        // Atomic claim: only one concurrent request may advance this job at this cursor.
        // A second parallel run-batch sees 0 affected rows and bails, preventing
        // duplicate translations, double provider billing and corrupted counters.
        $claimed = $wpdb->query($wpdb->prepare(
            'UPDATE ' . Tables::sql_identifier(Tables::jobs()) . ' SET status = %s, started_at = COALESCE(started_at, %s), updated_at = %s WHERE id = %d AND type = %s AND status IN (%s, %s) AND cursor_value = %d',
            'running',
            $now,
            $now,
            absint($jobId),
            self::TYPE_AI_TRANSLATION,
            'queued',
            'running',
            $cursor
        ));
        if ($claimed === 0) {
            return self::get_job($jobId);
        }

        $items = self::candidate_strings($options, $cursor, $batchSize);
        if ($items === []) {
            self::complete_job($jobId, __('AI-batch afgerond. Er zijn geen openstaande strings meer voor deze selectie.', 'webactueel-translate-language-dropdowns'));
            return self::get_job($jobId);
        }

        $repository = new TranslationRepository();
        $ai = new AiTranslationService();
        $processed = 0;
        $memoryReused = 0;
        $skipped = 0;
        $errors = 0;
        $lastCursor = $cursor;
        $stopMessage = '';

        foreach ($items as $item) {
            $stringId = absint($item['id'] ?? 0);
            $original = Input::scalar_string($item['original_text'] ?? '');
            if ($stringId <= 0 || $original === '' || self::string_length($original) > self::MAX_AI_TEXT_LENGTH) {
                $skipped++;
                $lastCursor = max($lastCursor, $stringId);
                continue;
            }

            $targetLanguage = Input::key($options['target_language'] ?? '');
            $memoryMatch = $repository->find_translation_memory_match($original, $targetLanguage);
            if ($memoryMatch !== []) {
                $translated = Input::scalar_string($memoryMatch['translated_text'] ?? '');
                $reviewStatus = 'reviewed';
                $origin = 'memory';
                $result = ['translated_text' => $translated, 'review_status' => $reviewStatus, 'origin' => $origin, 'memory_score' => absint($memoryMatch['score'] ?? 100)];
                $memoryReused++;
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
            } else {
                $result = $ai->translate(
                    $original,
                    Input::key($options['source_language'] ?? ''),
                    $targetLanguage,
                    ['job_id' => $jobId, 'string_id' => $stringId, 'batch' => true]
                );

                if (is_wp_error($result)) {
                    $errors++;
                    $stopMessage = $result->get_error_message();
                    break;
                }

                $translated = Input::scalar_string($result['translated_text'] ?? '');
                $reviewStatus = Input::key($result['review_status'] ?? 'needs_review') ?: 'needs_review';
                $origin = Input::key($result['origin'] ?? 'ai') ?: 'ai';
            }
            if ($translated === '' || ! $repository->save_translation($stringId, Input::key($options['target_language'] ?? ''), $translated, $reviewStatus, $origin)) {
                $errors++;
                $lastCursor = max($lastCursor, $stringId);
                continue;
            }

            $processed++;
            $lastCursor = max($lastCursor, $stringId);
        }

        $newProcessed = absint($job['processed_items'] ?? 0) + $processed;
        $newSkipped = absint($job['skipped_items'] ?? 0) + $skipped;
        $newErrors = absint($job['errors_count'] ?? 0) + $errors;
        $nextStatus = $stopMessage !== '' ? 'paused' : 'running';
        $message = $stopMessage !== ''
            ? sprintf(
                /* translators: %s: provider or queue error message. */
                __('AI-batch gepauzeerd: %s', 'webactueel-translate-language-dropdowns'),
                sanitize_text_field($stopMessage)
            )
            : sprintf(
                /* translators: 1: processed translations, 2: skipped items, 3: errors. */
                __('Laatste batch verwerkt: %1$d vertaald, %2$d overgeslagen, %3$d fouten, %4$d uit vertaalgeheugen.', 'webactueel-translate-language-dropdowns'),
                $processed,
                $skipped,
                $errors,
                $memoryReused
            );

        if ($stopMessage === '' && count($items) < $batchSize) {
            $nextStatus = 'completed';
            $message = __('AI-batch afgerond. Controleer de gegenereerde vertalingen voordat je ze publiceert.', 'webactueel-translate-language-dropdowns');
        }

        self::update_job($jobId, [
            'status' => $nextStatus,
            'cursor_value' => $lastCursor,
            'processed_items' => $newProcessed,
            'skipped_items' => $newSkipped,
            'errors_count' => $newErrors,
            'message' => $message,
            'updated_at' => current_time('mysql'),
            'completed_at' => $nextStatus === 'completed' ? current_time('mysql') : null,
        ]);

        return self::get_job($jobId);
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    private static function normalize_options(array $options): array
    {
        $status = Input::key($options['status'] ?? 'new');
        if (! in_array($status, ['new', 'missing', 'draft', 'needs_review', 'reviewed', 'published', 'outdated'], true)) {
            $status = 'new';
        }

        return [
            'target_language' => Input::key($options['target_language'] ?? ''),
            'source_language' => Input::key($options['source_language'] ?? ''),
            'status' => $status,
            'batch_size' => max(1, min(self::MAX_BATCH_SIZE, absint($options['batch_size'] ?? 5))),
            'assigned_user_id' => absint($options['assigned_user_id'] ?? 0),
            'due_at' => self::normalize_due_at(Input::scalar_string($options['due_at'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $options */
    private static function count_candidates(array $options): int
    {
        global $wpdb;
        [$joinSql, $whereSql, $params] = self::candidate_sql_parts($options, 0);
        $stringsTable = Tables::sql_identifier(Tables::strings());
        $sql = "SELECT COUNT(DISTINCT s.id) FROM `{$stringsTable}` s {$joinSql} WHERE {$whereSql}";
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /** @param array<string, mixed> $options @return array<int, array<string, mixed>> */
    private static function candidate_strings(array $options, int $cursor, int $batchSize): array
    {
        global $wpdb;
        [$joinSql, $whereSql, $params] = self::candidate_sql_parts($options, $cursor);
        $stringsTable = Tables::sql_identifier(Tables::strings());
        $sql = "SELECT s.id, s.original_text FROM `{$stringsTable}` s {$joinSql} WHERE {$whereSql} ORDER BY s.id ASC LIMIT %d";
        $params[] = $batchSize;
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, mixed> $options @return array{0:string,1:string,2:array<int, mixed>} */
    private static function candidate_sql_parts(array $options, int $cursor): array
    {
        $translationsTable = Tables::sql_identifier(Tables::translations());
        $targetLanguage = Input::key($options['target_language'] ?? '');
        $status = Input::key($options['status'] ?? 'new');
        $params = [$targetLanguage, max(0, $cursor), self::MAX_AI_TEXT_LENGTH];
        $where = ['s.id > %d', 'TRIM(s.original_text) <> ""', 'CHAR_LENGTH(s.original_text) <= %d'];
        $joinSql = "LEFT JOIN `{$translationsTable}` t ON t.string_id = s.id AND t.language_code = %s";

        if (in_array($status, ['new', 'missing'], true)) {
            $where[] = '(t.id IS NULL OR TRIM(COALESCE(t.translated_text, "")) = "")';
        } else {
            $where[] = 't.status = %s';
            $params[] = $status;
        }

        return [$joinSql, implode(' AND ', $where), $params];
    }

    /** @param array<string, mixed> $data */
    private static function update_job(int $jobId, array $data): void
    {
        global $wpdb;
        $wpdb->update(Tables::jobs(), $data, ['id' => absint($jobId), 'type' => self::TYPE_AI_TRANSLATION]);
    }

    private static function complete_job(int $jobId, string $message): void
    {
        self::update_job($jobId, [
            'status' => 'completed',
            'message' => $message,
            'updated_at' => current_time('mysql'),
            'completed_at' => current_time('mysql'),
        ]);
    }

    private static function normalize_due_at(string $dueAt): ?string
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

    /** @param array<string, mixed> $options */
    private static function encode_options(array $options): string
    {
        $encoded = wp_json_encode($options);
        return is_string($encoded) ? $encoded : '{}';
    }

    /** @return array<string, mixed> */
    private static function decode_options(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function string_length(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }
}
