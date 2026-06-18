<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: public wat_* hooks are intentional.

final class AiModelPolicy
{
    /** @return list<string> */
    public static function allowed_models(string $provider): array
    {
        $provider = self::normalize_provider($provider);
        if ($provider === 'deepl') {
            $models = ['deepl-api'];
        } elseif ($provider === 'google_translate') {
            $models = ['google-translate-v2'];
        } elseif ($provider === 'openai_compatible') {
            $models = ['gpt-4o-mini', 'gpt-4.1-mini', 'llama-3.1-70b-versatile', 'mistral-large-latest'];
        } else {
            $models = ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1', 'gpt-4.1-nano'];
        }

        $filtered = apply_filters('wat_allowed_ai_models', $models, $provider);
        if (! is_array($filtered)) {
            $filtered = $models;
        }

        $allowed = [];
        foreach ($filtered as $model) {
            if (! is_scalar($model)) {
                continue;
            }
            $model = sanitize_text_field((string) $model);
            if ($model !== '') {
                $allowed[] = $model;
            }
        }

        return array_values(array_unique($allowed));
    }

    public static function sanitize_model($model, string $provider): string
    {
        $provider = self::normalize_provider($provider);
        $default = $provider === 'deepl' ? 'deepl-api' : ($provider === 'google_translate' ? 'google-translate-v2' : 'gpt-4o-mini');
        $model = sanitize_text_field(Input::scalar_string($model, $default));

        $allowed = self::allowed_models($provider);
        if ($provider === 'openai_compatible') {
            $model = preg_replace('/[^A-Za-z0-9_.:\/\-]/', '', $model) ?: '';
            $model = substr($model, 0, 128);
        }

        return in_array($model, $allowed, true) ? $model : $default;
    }

    private static function normalize_provider(string $provider): string
    {
        $provider = sanitize_key($provider);
        return in_array($provider, ['openai', 'deepl', 'openai_compatible', 'google_translate'], true) ? $provider : 'openai';
    }
}
