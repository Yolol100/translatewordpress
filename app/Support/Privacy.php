<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

use Webactueel\Translate\Database\Tables;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers WordPress privacy helpers for data stored by Webactueel Translate.
 */
final class Privacy
{
    public static function register(): void
    {
        add_action('admin_init', [self::class, 'add_privacy_policy_content']);
        add_filter('wp_privacy_personal_data_exporters', [self::class, 'register_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [self::class, 'register_eraser']);
    }

    public static function add_privacy_policy_content(): void
    {
        if (! function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $paragraphs = [
            __(
                'Webactueel Translate bewaart plugininstellingen, talen, vertaalstrings, vertaalde tekst, scantaken, optionele technische logs en beheerdersvoorkeuren voor de plugininterface. Vertaalstrings en exports kunnen persoonsgegevens bevatten wanneer die persoonsgegevens in de oorspronkelijke sitecontent voorkomen.',
                'webactueel-translate-language-dropdowns'
            ),
            __(
                'Wanneer AI-vertaling is ingeschakeld, kan de brontekst samen met taal-, model-, toon- en formaliteitsinstellingen naar de gekozen externe provider worden verzonden. Ondersteunde providers zijn OpenAI, DeepL, Google Translate en OpenAI-compatibele endpoints die de sitebeheerder zelf configureert. AI-vertaling staat standaard uit en gegenereerde vertalingen kunnen review-first blijven voordat ze worden gepubliceerd.',
                'webactueel-translate-language-dropdowns'
            ),
            __(
                'API-sleutels worden bij voorkeur via serverconstanten of het wat_ai_api_key filter geleverd. Opslag van AI-sleutels in de WordPress-database is standaard uitgeschakeld en werkt alleen wanneer WAT_ENABLE_DB_AI_CREDENTIALS of het wat_allow_db_ai_credentials filter dit expliciet toestaat.',
                'webactueel-translate-language-dropdowns'
            ),
            __(
                'Wanneer taalherinnering is ingeschakeld, kan de plugin de gekozen taal bewaren in de wat_language cookie. CSV-previews worden tijdelijk opgeslagen in een beschermde tijdelijke map, gekoppeld aan de beheerder die de preview startte, en na import of verval verwijderd. Optionele AI-gebruiksgegevens en technische logs zijn bedoeld voor beheerderscontrole, kostenbewaking en foutdiagnose.',
                'webactueel-translate-language-dropdowns'
            ),
            __(
                'Site-eigenaren moeten zelf controleren of de gekozen AI-provider, verwerkingslocatie, bewaartermijnen, verwerkersafspraken en privacyverklaring passen bij hun wettelijke verplichtingen en klantafspraken.',
                'webactueel-translate-language-dropdowns'
            ),
        ];

        $content = wp_kses_post(wpautop(implode("\n\n", $paragraphs), false));

        wp_add_privacy_policy_content('Webactueel Translate', $content);
    }

    public static function register_exporter(array $exporters): array
    {
        $exporters['webactueel-translate'] = [
            'exporter_friendly_name' => __('Webactueel Translate', 'webactueel-translate-language-dropdowns'),
            'callback' => [self::class, 'export_personal_data'],
        ];

        return $exporters;
    }

    public static function register_eraser(array $erasers): array
    {
        $erasers['webactueel-translate'] = [
            'eraser_friendly_name' => __('Webactueel Translate', 'webactueel-translate-language-dropdowns'),
            'callback' => [self::class, 'erase_personal_data'],
        ];

        return $erasers;
    }

    public static function export_personal_data(string $emailAddress, int $page = 1): array
    {
        $user = get_user_by('email', $emailAddress);
        if (! $user instanceof \WP_User) {
            return [
                'data' => [],
                'done' => true,
            ];
        }

        $preferences = get_user_meta($user->ID, 'wat_admin_preferences', true);
        $data = [];
        if (is_array($preferences) && $preferences !== []) {
            $encodedPreferences = wp_json_encode($preferences);
            $data[] = [
                'name' => __('Admin preferences', 'webactueel-translate-language-dropdowns'),
                'value' => is_string($encodedPreferences) ? $encodedPreferences : '{}',
            ];
        }

        $data = array_merge($data, self::ai_usage_export_data((int) $user->ID), self::assigned_workflow_export_data((int) $user->ID));

        return [
            'data' => $data === [] ? [] : [[
                'group_id' => 'webactueel-translate',
                'group_label' => __('Webactueel Translate', 'webactueel-translate-language-dropdowns'),
                'item_id' => 'wat-user-data-' . $user->ID,
                'data' => $data,
            ]],
            'done' => true,
        ];
    }

    public static function erase_personal_data(string $emailAddress, int $page = 1): array
    {
        $user = get_user_by('email', $emailAddress);
        $itemsRemoved = false;
        $messages = [];

        if ($user instanceof \WP_User && metadata_exists('user', $user->ID, 'wat_admin_preferences')) {
            delete_user_meta($user->ID, 'wat_admin_preferences');
            $itemsRemoved = true;
        }

        if ($user instanceof \WP_User) {
            $anonymizedAiRows = self::anonymize_ai_usage_for_user((int) $user->ID);
            $unassignedJobs = self::unassign_workflow_jobs_for_user((int) $user->ID);
            if ($anonymizedAiRows > 0) {
                $itemsRemoved = true;
                $messages[] = sprintf(
                    /* translators: %d: number of anonymized AI usage rows. */
                    __('%d AI-gebruiksregels zijn losgekoppeld van deze gebruiker. Operationele totalen blijven bewaard zonder user-id.', 'webactueel-translate-language-dropdowns'),
                    $anonymizedAiRows
                );
            }
            if ($unassignedJobs > 0) {
                $itemsRemoved = true;
                $messages[] = sprintf(
                    /* translators: %d: number of workflow jobs. */
                    __('%d workflowtaken zijn losgekoppeld van deze gebruiker.', 'webactueel-translate-language-dropdowns'),
                    $unassignedJobs
                );
            }
        }

        return [
            'items_removed' => $itemsRemoved,
            'items_retained' => false,
            'messages' => $messages,
            'done' => true,
        ];
    }

    /** @return array<int, array{name:string,value:string}> */
    private static function ai_usage_export_data(int $userId): array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT COUNT(*) AS calls, COALESCE(SUM(source_chars),0) AS source_chars, COALESCE(SUM(output_chars),0) AS output_chars, COALESCE(SUM(estimated_words),0) AS estimated_words, MIN(created_at) AS first_used_at, MAX(created_at) AS last_used_at FROM %i WHERE user_id = %d',
            Tables::ai_usage(),
            $userId
        ), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy export reads plugin-owned reporting table.

        if (! is_array($row) || absint($row['calls'] ?? 0) === 0) {
            return [];
        }

        return [
            ['name' => __('AI usage calls', 'webactueel-translate-language-dropdowns'), 'value' => (string) absint($row['calls'] ?? 0)],
            ['name' => __('AI usage source characters', 'webactueel-translate-language-dropdowns'), 'value' => (string) absint($row['source_chars'] ?? 0)],
            ['name' => __('AI usage output characters', 'webactueel-translate-language-dropdowns'), 'value' => (string) absint($row['output_chars'] ?? 0)],
            ['name' => __('AI usage estimated words', 'webactueel-translate-language-dropdowns'), 'value' => (string) absint($row['estimated_words'] ?? 0)],
            ['name' => __('AI usage first used at', 'webactueel-translate-language-dropdowns'), 'value' => sanitize_text_field((string) ($row['first_used_at'] ?? ''))],
            ['name' => __('AI usage last used at', 'webactueel-translate-language-dropdowns'), 'value' => sanitize_text_field((string) ($row['last_used_at'] ?? ''))],
        ];
    }

    /** @return array<int, array{name:string,value:string}> */
    private static function assigned_workflow_export_data(int $userId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT status, COUNT(*) AS total FROM %i WHERE assigned_user_id = %d GROUP BY status ORDER BY status ASC',
            Tables::jobs(),
            $userId
        ), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy export reads plugin-owned workflow table.

        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $values = [];
        foreach ($rows as $row) {
            $status = sanitize_key((string) ($row['status'] ?? ''));
            if ($status !== '') {
                $values[] = $status . ': ' . absint($row['total'] ?? 0);
            }
        }

        return $values === [] ? [] : [[
            'name' => __('Assigned translation workflow jobs', 'webactueel-translate-language-dropdowns'),
            'value' => implode(', ', $values),
        ]];
    }

    private static function anonymize_ai_usage_for_user(int $userId): int
    {
        global $wpdb;

        $updated = $wpdb->update(Tables::ai_usage(), ['user_id' => 0], ['user_id' => $userId], ['%d'], ['%d']); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy eraser anonymizes plugin-owned reporting table.

        return $updated === false ? 0 : absint($updated);
    }

    private static function unassign_workflow_jobs_for_user(int $userId): int
    {
        global $wpdb;

        $updated = $wpdb->update(Tables::jobs(), ['assigned_user_id' => 0], ['assigned_user_id' => $userId], ['%d'], ['%d']); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy eraser removes plugin-owned workflow user assignment.

        return $updated === false ? 0 : absint($updated);
    }
}
