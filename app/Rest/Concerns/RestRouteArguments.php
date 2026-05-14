<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

trait RestRouteArguments
{
    public static function validate_language_code($value): bool
    {
        return is_scalar($value) && preg_match('/^[a-z]{2,3}(?:[-_][a-z0-9]{2,8})?$/i', (string) $value) === 1;
    }

    public static function validate_key_list($value): bool
    {
        if (is_string($value)) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_scalar($item)) {
                return false;
            }
        }

        return true;
    }

    private function id_arg(): array
    {
        return [
            'id' => [
                'type' => 'integer',
                'required' => true,
                'minimum' => 1,
                'validate_callback' => static fn($value): bool => is_scalar($value) && absint($value) > 0,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    private function language_args(): array
    {
        return [
            'code' => ['type' => 'string', 'validate_callback' => [self::class, 'validate_language_code'], 'sanitize_callback' => 'sanitize_key'],
            'locale' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'name' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'native_name' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'flag' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'is_default' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            'is_active' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            'is_rtl' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
        ];
    }

    private function strings_args(): array
    {
        return [
            'search' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'language' => ['type' => 'string', 'validate_callback' => [self::class, 'validate_language_code'], 'sanitize_callback' => 'sanitize_key'],
            'status' => ['type' => 'string', 'enum' => ['draft', 'needs_review', 'reviewed', 'published', 'ignored', 'new'], 'sanitize_callback' => 'sanitize_key'],
            'source_type' => ['type' => 'string', 'sanitize_callback' => 'sanitize_key'],
            'page' => ['type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint'],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'sanitize_callback' => 'absint'],
        ];
    }

    private function translation_args(): array
    {
        return [
            'language_code' => ['type' => 'string', 'required' => true, 'validate_callback' => [self::class, 'validate_language_code'], 'sanitize_callback' => 'sanitize_key'],
            'translated_text' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'wp_kses_post'],
            'status' => ['type' => 'string', 'enum' => ['draft', 'needs_review', 'reviewed', 'published', 'ignored'], 'sanitize_callback' => 'sanitize_key'],
            'apply_memory' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
        ];
    }

    private function scan_start_args(): array
    {
        return [
            'type' => ['type' => 'string', 'enum' => ['full', 'posts', 'pages', 'woocommerce'], 'sanitize_callback' => 'sanitize_key'],
        ];
    }

    private function scan_batch_args(): array
    {
        return [
            'batch_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'sanitize_callback' => 'absint'],
        ];
    }

    private function csv_import_args(): array
    {
        return [
            'preview_token' => [
                'required' => true,
                'type' => 'string',
                'pattern' => '^[a-zA-Z0-9]+$',
                'sanitize_callback' => static fn($value): string => preg_replace('/[^a-zA-Z0-9]/', '', Input::scalar_string($value)),
                'validate_callback' => static fn($value): bool => is_scalar($value) && preg_match('/^[a-zA-Z0-9]+$/', (string) $value) === 1,
            ],
            'languages' => [
                'validate_callback' => [self::class, 'validate_key_list'],
                'sanitize_callback' => static function ($value): array {
                    return Input::key_list($value);
                },
            ],
        ];
    }

    private function glossary_args(): array
    {
        return [
            'id' => ['type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint'],
            'source_term' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'target_term' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'language_code' => ['type' => 'string', 'required' => true, 'validate_callback' => [self::class, 'validate_language_code'], 'sanitize_callback' => 'sanitize_key'],
            'case_sensitive' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
        ];
    }

    private function settings_args(): array
    {
        $boolean = ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'];
        $textarea = ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'];

        return [
            'frontend_enabled' => $boolean,
            'safe_mode' => $boolean,
            'compatibility_override' => $boolean,
            'browser_redirect' => $boolean,
            'media_translation_enabled' => $boolean,
            'woocommerce_deep_translation_enabled' => $boolean,
            'translator_review_required' => $boolean,
            'remember_language' => $boolean,
            'hreflang_enabled' => $boolean,
            'hreflang_force' => $boolean,
            'x_default_enabled' => $boolean,
            'canonical_enabled' => $boolean,
            'multilingual_sitemap_enabled' => $boolean,
            'cache_enabled' => $boolean,
            'delete_data_on_uninstall' => $boolean,
            'debug_logging' => $boolean,
            'switcher_floating' => $boolean,
            'ai_enabled' => $boolean,
            'ai_review_required' => $boolean,
            'performance_monitoring' => $boolean,
            'ai_provider' => ['type' => 'string', 'enum' => ['openai', 'deepl'], 'sanitize_callback' => 'sanitize_key'],
            'ai_model' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'ai_tone' => ['type' => 'string', 'enum' => ['professional', 'friendly', 'formal', 'casual', 'seo'], 'sanitize_callback' => 'sanitize_key'],
            'ai_formality' => ['type' => 'string', 'enum' => ['default', 'more', 'less', 'prefer_more', 'prefer_less'], 'sanitize_callback' => 'sanitize_key'],
            'max_buffer_size' => ['type' => 'integer', 'minimum' => 100000, 'maximum' => 5000000, 'sanitize_callback' => 'absint'],
            'max_replacements' => ['type' => 'integer', 'minimum' => 10, 'maximum' => 5000, 'sanitize_callback' => 'absint'],
            'scan_batch_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'sanitize_callback' => 'absint'],
            'cache_ttl' => ['type' => 'integer', 'minimum' => 300, 'maximum' => 604800, 'sanitize_callback' => 'absint'],
            'csv_preview_rows' => ['type' => 'integer', 'minimum' => 20, 'maximum' => 1000, 'sanitize_callback' => 'absint'],
            'csv_import_max_rows' => ['type' => 'integer', 'minimum' => 100, 'maximum' => 50000, 'sanitize_callback' => 'absint'],
            'language_domains' => $textarea,
            'switcher_layout' => ['type' => 'string', 'enum' => ['dropdown', 'inline', 'flags_name', 'flags', 'code', 'flag_code', 'name_code', 'flags_name_code'], 'sanitize_callback' => 'sanitize_key'],
            'switcher_style' => ['type' => 'string', 'enum' => ['light', 'dark', 'compact', 'outline', 'minimal'], 'sanitize_callback' => 'sanitize_key'],
            'switcher_position' => ['type' => 'string', 'enum' => ['bottom-right', 'bottom-left', 'top-right', 'top-left'], 'sanitize_callback' => 'sanitize_key'],
            'exclude_selectors' => $textarea,
            'exclude_paths' => $textarea,
        ];
    }

    private function preferences_args(): array
    {
        return [
            'dashboard_order' => [
                'validate_callback' => [self::class, 'validate_key_list'],
                'sanitize_callback' => static function ($value): array {
                    return Input::key_list($value);
                },
            ],
        ];
    }
}
