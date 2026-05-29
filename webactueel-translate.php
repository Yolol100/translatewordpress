<?php
/**
 * Plugin Name: Webactueel Translate
 * Description: Universal frontend translation plugin with manual translations, setup workflow, clean language URLs, CSV/XLIFF import/export, SEO foundations and compatibility-safe output translation.
 * Version: 2.4.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Webactueel
 * Text Domain: webactueel-translate-language-dropdowns
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('WAT_VERSION', '2.4.0');
define('WAT_PLUGIN_FILE', __FILE__);
define('WAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WAT_TEXT_DOMAIN', 'webactueel-translate-language-dropdowns');

/**
 * Lightweight plugin autoloader.
 *
 * Keep this mapping aligned with the `Webactueel\\Translate` namespace and the `app/`
 * directory. Do not add side effects here; bootstrap work belongs in Plugin::boot().
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Webactueel\\Translate\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = WAT_PLUGIN_DIR . 'app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});

register_activation_hook(__FILE__, ['Webactueel\\Translate\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Webactueel\\Translate\\Plugin', 'deactivate']);

add_action('before_woocommerce_init', static function (): void {
    if (class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    Webactueel\Translate\Plugin::instance()->boot();
});
