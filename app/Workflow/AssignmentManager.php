<?php

declare(strict_types=1);

namespace Webactueel\Translate\Workflow;

use Webactueel\Translate\Automation\TranslationJobQueue;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Input;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned workflow table.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifiers are plugin-owned and escaped.

final class AssignmentManager
{
    /** @return array<int, array<string, mixed>> */
    public static function assignees(): array
    {
        $users = get_users([
            'capability' => TranslatorRoles::CAP_TRANSLATE,
            'fields' => ['ID', 'display_name', 'user_email'],
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => 100,
        ]);

        $items = [];
        $includeEmail = current_user_can('manage_options');
        foreach ($users as $user) {
            $items[] = [
                'id' => absint($user->ID),
                'name' => sanitize_text_field((string) $user->display_name),
                'email' => $includeEmail ? sanitize_email((string) $user->user_email) : '',
            ];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    public static function summary(): array
    {
        global $wpdb;
        $table = Tables::jobs();
        $openStatuses = ['queued', 'running', 'paused', 'failed'];
        $placeholders = implode(',', array_fill(0, count($openStatuses), '%s'));
        $params = array_merge([$table], $openStatuses);
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT status, COUNT(*) AS total FROM %i WHERE assigned_user_id > 0 AND status IN ({$placeholders}) GROUP BY status", $params),
            ARRAY_A
        ) ?: [];

        $byStatus = [];
        $open = 0;
        foreach ($rows as $row) {
            $status = Input::key($row['status'] ?? '');
            $total = absint($row['total'] ?? 0);
            if ($status !== '') {
                $byStatus[$status] = $total;
                $open += $total;
            }
        }

        $overdue = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE assigned_user_id > 0 AND due_at IS NOT NULL AND due_at <> '0000-00-00 00:00:00' AND due_at < %s AND status IN ({$placeholders})",
                array_merge([$table, current_time('mysql')], $openStatuses)
            )
        );

        return [
            'open' => $open,
            'overdue' => $overdue,
            'by_status' => $byStatus,
        ];
    }

    /** @return array<string, mixed> */
    public static function list_jobs(int $limit = 20): array
    {
        global $wpdb;
        $table = Tables::jobs();
        $users = $wpdb->users;
        $limit = max(1, min(100, absint($limit ?: 20)));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT j.*, u.display_name AS assignee_name, u.user_email AS assignee_email FROM %i j LEFT JOIN %i u ON u.ID = j.assigned_user_id WHERE j.type = %s ORDER BY COALESCE(j.due_at, j.created_at) ASC, j.id DESC LIMIT %d",
                $table,
                $users,
                TranslationJobQueue::TYPE_AI_TRANSLATION,
                $limit
            ),
            ARRAY_A
        ) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $items[] = self::normalize_job_row($row);
        }

        return ['items' => $items, 'summary' => self::summary()];
    }

    /** @return array<string, mixed>|WP_Error */
    public static function assign(int $jobId, int $userId, string $dueAt = '')
    {
        global $wpdb;
        $jobId = absint($jobId);
        $userId = absint($userId);
        if ($jobId <= 0) {
            return new WP_Error('wat_assignment_invalid_job', __('Ongeldige workflowtaak.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        if ($userId > 0 && ! user_can($userId, TranslatorRoles::CAP_TRANSLATE)) {
            return new WP_Error('wat_assignment_invalid_user', __('Deze gebruiker heeft geen vertaalrechten.', 'webactueel-translate-language-dropdowns'), ['status' => 400]);
        }

        $dueAt = self::normalize_due_at($dueAt);
        $data = [
            'assigned_user_id' => $userId,
            'due_at' => $dueAt !== '' ? $dueAt : null,
            'updated_at' => current_time('mysql'),
        ];

        $updated = $wpdb->update(
            Tables::jobs(),
            $data,
            ['id' => $jobId, 'type' => TranslationJobQueue::TYPE_AI_TRANSLATION]
        );

        if ($updated === false) {
            return new WP_Error('wat_assignment_failed', __('Workflowtaak toewijzen mislukt.', 'webactueel-translate-language-dropdowns'), ['status' => 500]);
        }

        return self::get_job($jobId);
    }

    /** @return array<string, mixed>|WP_Error */
    public static function get_job(int $jobId)
    {
        global $wpdb;
        $table = Tables::jobs();
        $users = $wpdb->users;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT j.*, u.display_name AS assignee_name, u.user_email AS assignee_email FROM %i j LEFT JOIN %i u ON u.ID = j.assigned_user_id WHERE j.id = %d AND j.type = %s LIMIT 1",
                $table,
                $users,
                absint($jobId),
                TranslationJobQueue::TYPE_AI_TRANSLATION
            ),
            ARRAY_A
        );

        if (! is_array($row)) {
            return new WP_Error('wat_assignment_job_not_found', __('Workflowtaak niet gevonden.', 'webactueel-translate-language-dropdowns'), ['status' => 404]);
        }

        return self::normalize_job_row($row);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function normalize_job_row(array $row): array
    {
        $options = json_decode(Input::scalar_string($row['options_json'] ?? '{}'), true);
        return [
            'id' => absint($row['id'] ?? 0),
            'type' => Input::key($row['type'] ?? ''),
            'status' => Input::key($row['status'] ?? ''),
            'total_items' => absint($row['total_items'] ?? 0),
            'processed_items' => absint($row['processed_items'] ?? 0),
            'errors_count' => absint($row['errors_count'] ?? 0),
            'message' => Input::scalar_string($row['message'] ?? ''),
            'assigned_user_id' => absint($row['assigned_user_id'] ?? 0),
            'assignee_name' => sanitize_text_field(Input::scalar_string($row['assignee_name'] ?? '')),
            'assignee_email' => current_user_can('manage_options') ? sanitize_email(Input::scalar_string($row['assignee_email'] ?? '')) : '',
            'due_at' => Input::scalar_string($row['due_at'] ?? ''),
            'created_at' => Input::scalar_string($row['created_at'] ?? ''),
            'updated_at' => Input::scalar_string($row['updated_at'] ?? ''),
            'completed_at' => Input::scalar_string($row['completed_at'] ?? ''),
            'options' => is_array($options) ? $options : [],
        ];
    }

    private static function normalize_due_at(string $dueAt): string
    {
        $dueAt = trim(sanitize_text_field($dueAt));
        if ($dueAt === '') {
            return '';
        }

        $timestamp = strtotime($dueAt);
        if ($timestamp === false) {
            return '';
        }

        return gmdate('Y-m-d H:i:s', $timestamp + ((int) (get_option('gmt_offset', 0) * HOUR_IN_SECONDS)));
    }
}
