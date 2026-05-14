<?php

declare(strict_types=1);

namespace Webactueel\Translate\Workflow;

if (! defined('ABSPATH')) {
    exit;
}

final class TranslatorRoles
{
    public const CAP_TRANSLATE = 'wat_manage_translations';
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
        if ($editor && ! $editor->has_cap(self::CAP_TRANSLATE)) {
            $editor->add_cap(self::CAP_TRANSLATE);
        }

        if (! get_role(self::ROLE_TRANSLATOR)) {
            add_role(
                self::ROLE_TRANSLATOR,
                __('Webactueel Translator', 'webactueel-translate-language-dropdowns'),
                [
                    'read' => true,
                    self::CAP_TRANSLATE => true,
                ]
            );
        }
    }

    public static function can_translate(): bool
    {
        return current_user_can('manage_options') || current_user_can(self::CAP_TRANSLATE);
    }
}
