<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend;

use Webactueel\Translate\Frontend\Concerns\RendersLanguageSwitcher;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

final class LanguageSwitcher
{
    use RendersLanguageSwitcher;

    /**
     * @var array<string, bool>
     */
    private static array $renderedTypes = [];

    public static function has_rendered(string $type = 'any'): bool
    {
        if ($type === 'any') {
            return self::$renderedTypes !== [];
        }

        return ! empty(self::$renderedTypes[sanitize_key($type)]);
    }

    public static function render(array $overrides = [], bool $isShortcode = false): string
    {
        $settings = self::settings($overrides);
        if (self::is_excluded_path(Input::scalar_string($settings['exclude_paths'] ?? ''))) {
            return '';
        }

        $languages = LanguageDetector::active_languages();
        if (! $languages) {
            return '';
        }

        $current = LanguageDetector::current_language();
        $layout = Input::key($settings['switcher_layout'] ?? 'dropdown');
        $layout = in_array($layout, ['dropdown', 'inline', 'flags_name', 'flags', 'code', 'flag_code', 'name_code', 'flags_name_code'], true) ? $layout : 'dropdown';
        $style = Input::key($settings['switcher_style'] ?? 'light');
        $style = in_array($style, ['light', 'dark', 'compact', 'outline', 'minimal'], true) ? $style : 'light';
        $position = Input::key($settings['switcher_position'] ?? 'bottom-right');
        $position = in_array($position, ['bottom-right', 'bottom-left', 'top-right', 'top-left'], true) ? $position : 'bottom-right';
        $classes = ['wat-language-switcher', 'wat-switcher-' . $layout, 'wat-switcher-' . $style];
        if (! empty($settings['switcher_floating'])) {
            $classes[] = 'wat-switcher-floating';
            $classes[] = 'wat-switcher-' . $position;
        }
        if ($isShortcode) {
            $classes[] = 'wat-switcher-shortcode';
        }

        $renderType = ! empty($settings['switcher_floating']) ? 'floating' : ($isShortcode ? 'shortcode' : 'inline');
        self::$renderedTypes[$renderType] = true;
        if ($layout === 'dropdown') {
            return self::dropdown($classes, $languages, $current);
        }

        return self::list($classes, $languages, $current, $layout);
    }

    private static function settings(array $overrides): array
    {
        $settings = Settings::all();
        foreach ($overrides as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if ($key === 'switcher_floating') {
                $settings[$key] = filter_var(Input::scalar_string($value), FILTER_VALIDATE_BOOLEAN);
                continue;
            }
            $settings[$key] = is_scalar($value) ? wp_unslash($value) : '';
        }
        return $settings;
    }

    private static function is_excluded_path(string $patterns): bool
    {
        return LanguageRouter::is_excluded_request_path($patterns);
    }
}
