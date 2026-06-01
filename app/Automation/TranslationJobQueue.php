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
        $table = Tables::jobs();
        $job = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM %i WHERE id = %d AND type = %s", $table, absint($jobId), self::TYPE_AI_TRANSLATION),
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

        if (self::is_terminal_job($job)) {
            return $job;
        }

        $options = self::normalize_options(is_array($job['options'] ?? null) ? $job['options'] : []);
        if (! self::ensure_target_language($jobId, $options)) {
            return self::get_job($jobId);
        }

        $batchSize = self::resolve_batch_size($batchSize, $options);
        $cursor = absint($job['cursor_value'] ?? 0);

        if (! self::claim_job_cursor($jobId, $cursor)) {
            return self::get_job($jobId);
        }

        $items = self::candidate_strings($options, $cursor, $batchSize);
        if ($items === []) {
            self::complete_job($jobId, __('AI-batch afgerond. Er zijn geen openstaande strings meer voor deze selectie.', 'webactueel-translate-language-dropdowns'));
            return self::get_job($jobId);
        }

        $summary = self::process_batch_items($items, $options, $jobId, $cursor);
        self::update_job($jobId, self::batch_update_payload($job, $summary, $batchSize, count($items)));

        return self::get_job($jobId);
    }

    /** @param array<string, mixed> $job */
    private static function is_terminal_job(array $job): bool
    {
        return in_array(Input::key($job['status'] ?? ''), ['completed', 'cancelled'], true);
    }

    /** @param array<string, mixed> $options */
    private static function ensure_target_language(int $jobId, array $options): bool
    {
        $validator = new self();
        if ($validator->is_translatable_language(Input::key($options['target_language'] ?? ''))) {
            return true;
        }

        self::update_job($jobId, [
            'status' => 'failed',
            'message' => __('Kies een actieve niet-standaardtaal voordat je de AI-batch uitvoert.', 'webactueel-translate-language-dropdowns'),
            'updated_at' => current_time('mysql'),
        ]);

        return false;
    }

    /** @param array<string, mixed> $options */
    private static function resolve_batch_size(int $batchSize, array $options): int
    {
        return max(1, min(self::MAX_BATCH_SIZE, absint($batchSize ?: ($options['batch_size'] ?? 5))));
    }

    private static function claim_job_cursor(int $jobId, int $cursor): bool
    {
        global $wpdb;
        $now = current_time('mysql');
        // Atomic claim: only one concurrent request may advance this job at this cursor.
        // A second parallel run-batch sees 0 affected rows and bails, preventing
        // duplicate translations, double provider billing and corrupted counters.
        $claimed = $wpdb->query($wpdb->prepare(
            'UPDATE %i SET status = %s, started_at = COALESCE(started_at, %s), updated_at = %s WHERE id = %d AND type = %s AND status IN (%s, %s) AND cursor_value = %d',
            Tables::jobs(),
            'running',
            $now,
            $now,
            absint($jobId),
            self::TYPE_AI_TRANSLATION,
            'queued',
            'running',
            $cursor
        ));

        return $claimed !== false && (int) $claimed !== 0;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $options
     * @return array{processed:int,memory_reused:int,skipped:int,errors:int,last_cursor:int,stop_message:string}
     */
    private static function process_batch_items(array $items, array $options, int $jobId, int $cursor): array
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
            $itemResult = self::process_batch_item($item, $options, $jobId, $repository, $ai);
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
    private static function process_batch_item(array $item, array $options, int $jobId, TranslationRepository $repository, AiTranslationService $ai): array
    {
        $stringId = absint($item['id'] ?? 0);
        $original = Input::scalar_string($item['original_text'] ?? '');

        if ($stringId <= 0 || $original === '' || self::string_length($original) > self::MAX_AI_TEXT_LENGTH) {
            return ['status' => 'skipped', 'cursor' => $stringId];
        }

        $translation = self::resolve_batch_translation($repository, $ai, $options, $jobId, $stringId, $original);
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
    private static function resolve_batch_translation(TranslationRepository $repository, AiTranslationService $ai, array $options, int $jobId, int $stringId, string $original)
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

    /**
     * @param array<string, mixed> $job
     * @param array{processed:int,memory_reused:int,skipped:int,errors:int,last_cursor:int,stop_message:string} $summary
     * @return array<string, mixed>
     */
    private static function batch_update_payload(array $job, array $summary, int $batchSize, int $itemCount): array
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
        $stringsTable = Tables::strings();
        $sql = "SELECT COUNT(DISTINCT s.id) FROM %i s {$joinSql} WHERE {$whereSql}";
        array_unshift($params, $stringsTable);
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /** @param array<string, mixed> $options @return array<int, array<string, mixed>> */
    private static function candidate_strings(array $options, int $cursor, int $batchSize): array
    {
        global $wpdb;
        [$joinSql, $whereSql, $params] = self::candidate_sql_parts($options, $cursor);
        $stringsTable = Tables::strings();
        $sql = "SELECT s.id, s.original_text FROM %i s {$joinSql} WHERE {$whereSql} ORDER BY s.id ASC LIMIT %d";
        array_unshift($params, $stringsTable);
        $params[] = $batchSize;
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, mixed> $options @return array{0:string,1:string,2:array<int, mixed>} */
    private static function candidate_sql_parts(array $options, int $cursor): array
    {
        $translationsTable = Tables::translations();
        $targetLanguage = Input::key($options['target_language'] ?? '');
        $status = Input::key($options['status'] ?? 'new');
        $params = [$translationsTable, $targetLanguage, max(0, $cursor), self::MAX_AI_TEXT_LENGTH];
        $where = ['s.id > %d', 'TRIM(s.original_text) <> ""', 'CHAR_LENGTH(s.original_text) <= %d'];
        $joinSql = "LEFT JOIN %i t ON t.string_id = s.id AND t.language_code = %s";

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
