<?php

declare(strict_types=1);

namespace Webactueel\Translate\Admin;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

final class AdminMenu
{
    public const SLUG = 'wat-translate';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_bar_menu', [$this, 'admin_bar_menu'], 80);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_notices', [$this, 'admin_notices']);
    }

    public function admin_menu(): void
    {
        add_menu_page(
            __('Webactueel Translate', 'webactueel-translate-language-dropdowns'),
            __('Vertalen', 'webactueel-translate-language-dropdowns'),
            'manage_options',
            self::SLUG,
            [$this, 'render'],
            'dashicons-translation',
            58
        );
    }

    public function admin_bar_menu(\WP_Admin_Bar $admin_bar): void
    {
        if (! is_admin_bar_showing() || ! current_user_can('manage_options')) {
            return;
        }

        $admin_bar->add_node([
            'id' => 'wat-translate',
            'title' => esc_html__('Vertalen', 'webactueel-translate-language-dropdowns'),
            'href' => admin_url('admin.php?page=' . self::SLUG),
            'meta' => [
                'title' => esc_attr__('Webactueel Translate openen', 'webactueel-translate-language-dropdowns'),
            ],
        ]);
    }

    public function enqueue(string $hook): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing.
        $page = Input::get_key('page');
        $allowedHooks = [
            'toplevel_page_' . self::SLUG,
        ];
        if (! in_array($hook, $allowedHooks, true) && $page !== self::SLUG) {
            return;
        }

        $assetFile = WAT_PLUGIN_DIR . 'build/admin/index.asset.php';
        $asset = is_readable($assetFile) ? require $assetFile : ['dependencies' => ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-notices'], 'version' => WAT_VERSION];
        $dependencies = isset($asset['dependencies']) && is_array($asset['dependencies']) ? array_values(array_filter($asset['dependencies'], 'is_string')) : ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-notices'];
        $version = isset($asset['version']) && is_scalar($asset['version']) ? (string) $asset['version'] : WAT_VERSION;
        $pluginUrl = WAT_PLUGIN_URL;
        $pluginDir = WAT_PLUGIN_DIR;

        wp_enqueue_style('wp-components');
        wp_enqueue_style('webactueel-translate-language-dropdowns-design-system', $pluginUrl . 'build/shared/design-system.css', ['wp-components'], $version);
        wp_enqueue_style('webactueel-translate-language-dropdowns-admin', $pluginUrl . 'build/admin/index.css', ['webactueel-translate-language-dropdowns-design-system'], $version);
        wp_enqueue_script('webactueel-translate-language-dropdowns-admin', $pluginUrl . 'build/admin/index.js', $dependencies, $version, true);
        wp_enqueue_script('webactueel-translate-language-dropdowns-native-workflow', $pluginUrl . 'build/admin/native-workflow.js', ['webactueel-translate-language-dropdowns-admin'], $version, true);
        wp_set_script_translations('webactueel-translate-language-dropdowns-admin', 'webactueel-translate-language-dropdowns', $pluginDir . 'languages');
        wp_set_script_translations('webactueel-translate-language-dropdowns-native-workflow', 'webactueel-translate-language-dropdowns', $pluginDir . 'languages');
        $configJson = wp_json_encode([
            'restUrl' => esc_url_raw(rest_url('webactueel-translate-language-dropdowns/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin UI tab selection; no state change is performed.
            'currentTab' => Input::get_key('wat_tab', 'dashboard'),
            'version' => WAT_VERSION,
            'canManage' => current_user_can('manage_options'),
            'exportUrl' => add_query_arg(
                [
                    'action' => 'wat_csv_export',
                    '_wpnonce' => wp_create_nonce('wat_csv_export'),
                ],
                admin_url('admin-post.php')
            ),
            'siteUrl' => esc_url_raw(home_url('/')),
            'visualEditorUrl' => esc_url_raw(add_query_arg('wat_visual_editor', '1', home_url('/'))),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (! is_string($configJson)) {
            $configJson = '{}';
        }
        wp_add_inline_script('webactueel-translate-language-dropdowns-admin', 'window.WebactueelTranslate = ' . $configJson . ';', 'before');
    }

    public function admin_notices(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $replacementError = get_option('wat_replacement_cleanup_error');
        if (is_string($replacementError) && $replacementError !== '') {
            echo '<div class="notice notice-error"><p>' . esc_html(sprintf(
                /* translators: %s: cleanup error message. */
                __('Webactueel Translate kon oude plugininstallaties niet veilig voorbereiden voor handmatige controle: %s', 'webactueel-translate-language-dropdowns'),
                $replacementError
            )) . '</p></div>';
        }

        $cleanupTargets = get_option('wat_replacement_cleanup_targets');
        if (is_array($cleanupTargets) && $cleanupTargets !== []) {
            echo '<div class="notice notice-warning"><p>' . esc_html(sprintf(
                /* translators: %d: number of older plugin installations detected. */
                _n(
                    'Webactueel Translate heeft %d mogelijke oude plugininstallatie gevonden. Er is niets automatisch gedeactiveerd of verwijderd; controleer Plugins handmatig voordat je opruimt.',
                    'Webactueel Translate heeft %d mogelijke oude plugininstallaties gevonden. Er is niets automatisch gedeactiveerd of verwijderd; controleer Plugins handmatig voordat je opruimt.',
                    count($cleanupTargets),
                    'webactueel-translate-language-dropdowns'
                ),
                count($cleanupTargets)
            )) . '</p></div>';
        }

        if (! class_exists('DOMDocument')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Translate heeft de PHP DOM/ext-xml extensie nodig voor frontend vertaling. Schakel deze extensie in op de server.', 'webactueel-translate-language-dropdowns') . '</p></div>';
        }

        $settings = Settings::all();
        if (! empty($settings['ai_enabled'])) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Webactueel Translate AI-vertaling is ingeschakeld. Tekst die je laat vertalen wordt naar de gekozen externe AI-provider verzonden. Controleer of dit past binnen je privacybeleid en klantafspraken.', 'webactueel-translate-language-dropdowns') . '</p></div>';
        }
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Je hebt geen toegang tot deze pagina.', 'webactueel-translate-language-dropdowns'));
        }

        echo '<div class="wrap wat-admin-wrap">';
        echo '<div id="webactueel-translate-native-workflow-root" class="webactueel-translate-admin wat-native-workflow-shell"></div>';
        echo '<div id="webactueel-translate-admin-root" class="webactueel-translate-admin wat-admin webactueel-translate-language-dropdowns-admin">';
        echo '<div id="wat-admin-fallback" class="wat-admin-fallback">';
        echo '<h1>' . esc_html__('Webactueel Translate', 'webactueel-translate-language-dropdowns') . '</h1>';
        echo '<p>' . esc_html__('De beheerinterface wordt geladen. Blijft dit scherm staan, controleer dan of WordPress admin-scripts en de REST API niet worden geblokkeerd.', 'webactueel-translate-language-dropdowns') . '</p>';
        echo '<noscript><p>' . esc_html__('JavaScript is nodig om Webactueel Translate te beheren.', 'webactueel-translate-language-dropdowns') . '</p></noscript>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
