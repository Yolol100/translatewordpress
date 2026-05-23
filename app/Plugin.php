<?php

declare(strict_types=1);

namespace Webactueel\Translate;

use Webactueel\Translate\Admin\AdminMenu;
use Webactueel\Translate\Admin\UrlMappingAdmin;
use Webactueel\Translate\Cache\CacheInvalidator;
use Webactueel\Translate\Database\Schema;
use Webactueel\Translate\Frontend\FrontendBootstrap;
use Webactueel\Translate\Frontend\LanguageRouter;
use Webactueel\Translate\ImportExport\CsvExporter;
use Webactueel\Translate\Installer\ReplacementManager;
use Webactueel\Translate\Rest\RestServiceProvider;
use Webactueel\Translate\Support\Logger;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Privacy;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Diagnostics;
use Webactueel\Translate\ProductFeatures;
use Webactueel\Translate\Workflow\TranslatorRoles;

if (! defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private static ?self $instance = null;
    private bool $booted = false;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Install persistent plugin data and role capabilities on activation.
     *
     * Keep this method safe to run more than once; WordPress may call activation logic
     * during reinstalls or when a deployment reactivates the plugin.
     */
    public static function activate(): void
    {
        ReplacementManager::replace_older_installations();
        Schema::install();
        TranslatorRoles::activate();
        if (! get_option('wat_settings')) {
            add_option('wat_settings', Settings::defaults(), '', false);
        }
        if (get_option('wat_delete_data_on_uninstall') === false) {
            add_option('wat_delete_data_on_uninstall', '0', '', false);
        }
        if (get_option('wat_cache_version') === false) {
            add_option('wat_cache_version', '1', '', false);
        }
        LanguageRouter::schedule_rewrite_flush();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('wat_scan_batch');
        TranslatorRoles::deactivate();
    }

    /**
     * Register all WordPress integration points for the current request.
     *
     * This is the composition root for the plugin. New feature modules should normally
     * be registered here or from ProductFeatures rather than from global scope.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        (new AdminMenu())->register();
        (new UrlMappingAdmin())->register();
        (new RestServiceProvider())->register();
        (new FrontendBootstrap())->register();
        (new ProductFeatures())->register();
        (new TranslatorRoles())->register();

        add_action('admin_init', [Schema::class, 'maybe_install'], 1);
        add_action('rest_api_init', [Schema::class, 'maybe_install'], 1);

        Privacy::register();
        Diagnostics::register_wp_cli();

        add_action('wat_log', [Logger::class, 'write'], 10, 3);
        add_action('admin_post_wat_csv_export', [self::class, 'admin_csv_export']);
        add_action('wat_settings_updated', [CacheInvalidator::class, 'bump']);
        add_action('wat_language_routes_changed', [LanguageRouter::class, 'schedule_rewrite_flush']);
        add_filter('plugin_action_links_' . plugin_basename(WAT_PLUGIN_FILE), [self::class, 'plugin_action_links']);
    }

    /**
     * Add a direct Settings link on the Plugins screen.
     *
     * @param array<int|string, string> $links Existing plugin action links.
     * @return array<int|string, string>
     */
    public static function plugin_action_links(array $links): array
    {
        if (! current_user_can('manage_options')) {
            return $links;
        }

        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=' . AdminMenu::SLUG)),
            esc_html__('Instellingen', 'webactueel-translate-language-dropdowns')
        );

        return array_merge(['settings' => $settingsLink], $links);
    }

    public static function admin_csv_export(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'webactueel-translate-language-dropdowns'));
        }
        check_admin_referer('wat_csv_export');
        $rawLanguagesValue = Input::get_array_text('languages');
        if (empty($rawLanguagesValue)) {
            $rawLanguagesValue = Input::get_text('languages');
        }
        $languages = Input::key_list($rawLanguagesValue);
        $mode = Input::get_key('mode', 'all');
        $csv = (new CsvExporter())->csv_string($languages, $mode);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="webactueel-translate-language-dropdowns-export.csv"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }
}
