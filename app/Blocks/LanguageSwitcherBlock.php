<?php

declare(strict_types=1);

namespace Webactueel\Translate\Blocks;

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

        wp_register_style(
            'webactueel-translate-language-dropdowns-design-system',
            WAT_PLUGIN_URL . 'build/shared/design-system.css',
            [],
            WAT_VERSION
        );
        wp_register_style(
            'webactueel-translate-language-dropdowns-switcher',
            WAT_PLUGIN_URL . 'build/frontend/switcher.css',
            ['webactueel-translate-language-dropdowns-design-system'],
            WAT_VERSION
        );

        $editor_asset_file = WAT_PLUGIN_DIR . 'blocks/language-switcher/editor.asset.php';
        $editor_asset = is_readable($editor_asset_file) ? require $editor_asset_file : [
            'dependencies' => ['wp-blocks', 'wp-element', 'wp-i18n', 'wp-server-side-render'],
            'version' => WAT_VERSION,
        ];
        $editor_dependencies = isset($editor_asset['dependencies']) && is_array($editor_asset['dependencies'])
            ? array_values(array_filter($editor_asset['dependencies'], 'is_string'))
            : ['wp-blocks', 'wp-element', 'wp-i18n', 'wp-server-side-render'];
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
                return LanguageSwitcher::render([], true);
            },
        ]);
    }
}
