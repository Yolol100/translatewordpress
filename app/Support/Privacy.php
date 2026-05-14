<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

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

        $content = wp_kses_post(
            __(
                'Webactueel Translate stores translation strings, translated text, language settings, scan jobs, optional diagnostic logs and administrator interface preferences. Translation strings may contain personal data when personal data is present in page content. Exported CSV files can contain the same translated content. Diagnostic logs are intended for administrators and sensitive context values are redacted before storage.',
                'webactueel-translate-language-dropdowns'
            )
        );

        wp_add_privacy_policy_content('Webactueel Translate', wpautop($content));
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
            $data[] = [
                'name' => __('Admin preferences', 'webactueel-translate-language-dropdowns'),
                'value' => wp_json_encode($preferences),
            ];
        }

        return [
            'data' => $data === [] ? [] : [[
                'group_id' => 'webactueel-translate',
                'group_label' => __('Webactueel Translate', 'webactueel-translate-language-dropdowns'),
                'item_id' => 'wat-admin-preferences-' . $user->ID,
                'data' => $data,
            ]],
            'done' => true,
        ];
    }

    public static function erase_personal_data(string $emailAddress, int $page = 1): array
    {
        $user = get_user_by('email', $emailAddress);
        $itemsRemoved = false;

        if ($user instanceof \WP_User && metadata_exists('user', $user->ID, 'wat_admin_preferences')) {
            delete_user_meta($user->ID, 'wat_admin_preferences');
            $itemsRemoved = true;
        }

        return [
            'items_removed' => $itemsRemoved,
            'items_retained' => false,
            'messages' => [],
            'done' => true,
        ];
    }
}
