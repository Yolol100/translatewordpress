<?php

declare(strict_types=1);

namespace Webactueel\Translate\Workflow;

if (! defined('ABSPATH')) {
    exit;
}

final class TranslatorRoles
{
    public const CAP_TRANSLATE = 'wat_manage_translations';
    public const CAP_SCAN = 'wat_run_scans';
    public const CAP_IMPORT_EXPORT = 'wat_import_export_translations';
    public const ROLE_TRANSLATOR = 'wat_translator';

    public function register(): void
    {
        add_action('admin_init', [self::class, 'ensure']);
    }

    public static function activate(): void
    {
        self::ensure();
    }

    public static function deactivate(): void
    {
        // Keep the role and capability on deactivation so existing editorial workflows are not broken.
    }

    public static function ensure(): void
    {
        $administrator = get_role('administrator');
        if ($administrator && ! $administrator->has_cap(self::CAP_TRANSLATE)) {
            $administrator->add_cap(self::CAP_TRANSLATE);
        }

        $editor = get_role('editor');
        $allow_editor_cap = (bool) apply_filters('wat_allow_editor_translation_capability', false);
        if ($editor) {
            if ($allow_editor_cap && ! $editor->has_cap(self::CAP_TRANSLATE)) {
                $editor->add_cap(self::CAP_TRANSLATE);
            } elseif (! $allow_editor_cap && $editor->has_cap(self::CAP_TRANSLATE)) {
                $editor->remove_cap(self::CAP_TRANSLATE);
            }
        }

        foreach ([self::CAP_SCAN, self::CAP_IMPORT_EXPORT] as $capability) {
            if ($administrator && ! $administrator->has_cap($capability)) {
                $administrator->add_cap($capability);
            }
        }

        if (! get_role(self::ROLE_TRANSLATOR)) {
            add_role(
                self::ROLE_TRANSLATOR,
                __('Webactueel Translator', 'webactueel-translate-language-dropdowns'),
                [
                    'read' => true,
                    self::CAP_TRANSLATE => true,
                    self::CAP_SCAN => false,
                    self::CAP_IMPORT_EXPORT => false,
                ]
            );
        }
    }

    public static function can_translate(): bool
    {
        return current_user_can('manage_options') || current_user_can(self::CAP_TRANSLATE);
    }

    public static function can_scan(): bool
    {
        return current_user_can('manage_options') || current_user_can(self::CAP_SCAN);
    }

    public static function can_import_export(): bool
    {
        return current_user_can('manage_options') || current_user_can(self::CAP_IMPORT_EXPORT);
    }
}
