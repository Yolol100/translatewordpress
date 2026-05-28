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
                'Webactueel Translate bewaart vertaalstrings, vertaalde tekst, taalinstellingen, scantaken, optionele technische logs en beheerdersvoorkeuren voor de interface. Vertaalstrings kunnen persoonsgegevens bevatten wanneer die persoonsgegevens in paginacontent voorkomen. Geëxporteerde CSV-bestanden kunnen dezelfde vertaalde content bevatten. Technische logs zijn bedoeld voor beheerders en gevoelige contextwaarden worden vóór opslag geredigeerd.',
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
            $encodedPreferences = wp_json_encode($preferences);
            $data[] = [
                'name' => __('Admin preferences', 'webactueel-translate-language-dropdowns'),
                'value' => is_string($encodedPreferences) ? $encodedPreferences : '{}',
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
