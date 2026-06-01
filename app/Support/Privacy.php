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

        $paragraphs = [
            __(
                'Webactueel Translate bewaart plugininstellingen, talen, vertaalstrings, vertaalde tekst, scantaken, optionele technische logs en beheerdersvoorkeuren voor de plugininterface. Vertaalstrings en exports kunnen persoonsgegevens bevatten wanneer die persoonsgegevens in de oorspronkelijke sitecontent voorkomen.',
                'webactueel-translate-language-dropdowns'
            ),
            __(
                'Wanneer AI-vertaling is ingeschakeld, kan de brontekst samen met taal-, model-, toon- en formaliteitsinstellingen naar de gekozen externe provider worden verzonden. Ondersteunde providers zijn OpenAI, DeepL en OpenAI-compatibele endpoints die de sitebeheerder zelf configureert. AI-vertaling staat standaard uit en gegenereerde vertalingen kunnen review-first blijven voordat ze worden gepubliceerd.',
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
