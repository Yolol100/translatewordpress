<?php

declare(strict_types=1);

namespace Webactueel\Translate\Setup;

use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

final class SetupWizard
{
    private const OPTION = 'wat_setup_state';

    /** @return array<string, mixed> */
    public static function state(): array
    {
        $state = get_option(self::OPTION, []);
        $state = is_array($state) ? $state : [];
        return array_merge([
            'completed' => false,
            'current_step' => 'settings',
            'completed_steps' => [],
            'dismissed' => false,
        ], $state);
    }

    /** @return array<int, array<string, mixed>> */
    public static function steps(): array
    {
        $settings = Settings::all();
        return [
            ['key' => 'languages', 'label' => __('Talen kiezen', 'webactueel-translate-language-dropdowns'), 'tab' => 'settings', 'required' => true],
            ['key' => 'routing', 'label' => __('URL-structuur controleren', 'webactueel-translate-language-dropdowns'), 'tab' => 'settings', 'required' => true, 'note' => sprintf(
                /* translators: %s: Current URL mode setting, for example subdirectory or query parameter. */
                __('Huidige modus: %s', 'webactueel-translate-language-dropdowns'),
                (string) ($settings['url_mode'] ?? 'subdirectory')
            )],
            ['key' => 'switcher', 'label' => __('Taalkiezer plaatsen', 'webactueel-translate-language-dropdowns'), 'tab' => 'settings', 'required' => true],
            ['key' => 'scan', 'label' => __('Eerste scan starten', 'webactueel-translate-language-dropdowns'), 'tab' => 'translate', 'required' => true],
            ['key' => 'safety', 'label' => __('Cache en checkout controleren', 'webactueel-translate-language-dropdowns'), 'tab' => 'advanced', 'required' => true],
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public static function save_state(array $input): array
    {
        $state = self::state();
        if (array_key_exists('completed', $input)) {
            $state['completed'] = (bool) $input['completed'];
        }
        if (isset($input['current_step']) && is_scalar($input['current_step'])) {
            $state['current_step'] = sanitize_key((string) $input['current_step']);
        }
        if (isset($input['completed_steps']) && is_array($input['completed_steps'])) {
            $state['completed_steps'] = array_values(array_unique(array_filter(array_map('sanitize_key', $input['completed_steps']))));
        }
        if (array_key_exists('dismissed', $input)) {
            $state['dismissed'] = (bool) $input['dismissed'];
        }
        update_option(self::OPTION, $state, false);
        return $state;
    }
}
