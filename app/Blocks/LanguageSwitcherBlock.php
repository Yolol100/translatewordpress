<?php

declare(strict_types=1);

namespace Webactueel\Translate\Blocks;

use Webactueel\Translate\Frontend\FrontendBootstrap;
use Webactueel\Translate\Frontend\LanguageSwitcher;

if (! defined('ABSPATH')) {
    exit;
}

final class LanguageSwitcherBlock
{
    public function register(): void
    {
        add_action('init', [$this, 'register_block']);
    }

    public function register_block(): void
    {
        if (! function_exists('register_block_type_from_metadata')) {
            return;
        }

        FrontendBootstrap::register_switcher_assets();

        $editor_asset_file = WAT_PLUGIN_DIR . 'blocks/language-switcher/editor.asset.php';
        $editor_asset = is_readable($editor_asset_file) ? require $editor_asset_file : [
            'dependencies' => ['wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n', 'wp-server-side-render'],
            'version' => WAT_VERSION,
        ];
        $editor_dependencies = isset($editor_asset['dependencies']) && is_array($editor_asset['dependencies'])
            ? array_values(array_filter($editor_asset['dependencies'], 'is_string'))
            : ['wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n', 'wp-server-side-render'];
        $editor_version = isset($editor_asset['version']) && is_scalar($editor_asset['version']) ? (string) $editor_asset['version'] : WAT_VERSION;

        wp_register_script(
            'webactueel-translate-language-switcher-editor',
            WAT_PLUGIN_URL . 'blocks/language-switcher/editor.js',
            $editor_dependencies,
            $editor_version,
            true
        );
        wp_set_script_translations(
            'webactueel-translate-language-switcher-editor',
            'webactueel-translate-language-dropdowns',
            WAT_PLUGIN_DIR . 'languages'
        );

        register_block_type_from_metadata(WAT_PLUGIN_DIR . 'blocks/language-switcher', [
            'render_callback' => static function (): string {
                // The block declares the shared `...-switcher` handle as its viewScript,
                // so WordPress enqueues exactly one switcher behaviour script. This call
                // also guarantees the matching stylesheet loads even on setups that do
                // not auto-enqueue block styles.
                FrontendBootstrap::enqueue_switcher_assets();
                return LanguageSwitcher::render([], true);
            },
        ]);
    }
}
