<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

if (! defined('ABSPATH')) {
    exit;
}

final class SettingsRepository
{
    /** @return array<string, mixed> */
    public static function load(): array
    {
        $settings = get_option('wat_settings', []);
        return is_array($settings) ? $settings : [];
    }

    /** @param array<string, mixed> $settings */
    public static function persist(array $settings): void
    {
        update_option('wat_settings', $settings, false);
        update_option('wat_delete_data_on_uninstall', ! empty($settings['delete_data_on_uninstall']) ? '1' : '0', false);
    }
}
