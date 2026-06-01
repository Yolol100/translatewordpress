<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Setup\SetupWizard;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;
use WP_REST_Request;

if (! defined('ABSPATH')) {
    exit;
}

trait ProductFeatureSetupEndpoints
{
    /**
     * Return practical next steps for first-run onboarding without exposing settings writes.
     *
     * @return array<string, mixed>
     */
    public function setup_recommendations(): array
    {
        $settings = Settings::all();
        $state = SetupWizard::state();
        $completed = isset($state['completed_steps']) && is_array($state['completed_steps']) ? array_map('sanitize_key', $state['completed_steps']) : [];
        $items = [];

        foreach (SetupWizard::steps() as $step) {
            $key = isset($step['key']) && is_scalar($step['key']) ? sanitize_key((string) $step['key']) : '';
            if ($key === '' || in_array($key, $completed, true)) {
                continue;
            }
            $items[] = [
                'key' => $key,
                'label' => isset($step['label']) && is_scalar($step['label']) ? sanitize_text_field((string) $step['label']) : $key,
                'tab' => isset($step['tab']) && is_scalar($step['tab']) ? sanitize_key((string) $step['tab']) : 'dashboard',
                'priority' => $key === 'safety' && ! empty($settings['woocommerce_deep_translation_enabled']) ? 'high' : 'normal',
            ];
        }

        if (! empty($settings['ai_enabled']) && ! Settings::has_ai_api_key(Input::key($settings['ai_provider'] ?? 'openai'))) {
            array_unshift($items, [
                'key' => 'ai_credentials',
                'label' => __('AI staat aan, maar er is geen API-sleutel via serverconstante of filter gevonden.', 'webactueel-translate-language-dropdowns'),
                'tab' => 'automation',
                'priority' => 'high',
            ]);
        }

        return [
            'completed' => ! empty($state['completed']),
            'dismissed' => ! empty($state['dismissed']),
            'items' => $items,
            'safe_defaults' => [
                'frontend_enabled' => ! empty($settings['frontend_enabled']),
                'safe_mode' => ! empty($settings['safe_mode']),
                'ai_review_required' => ! empty($settings['ai_review_required']),
                'translator_review_required' => ! empty($settings['translator_review_required']),
            ],
        ];
    }

    public function setup(): array
    {
        return ['state' => SetupWizard::state(), 'steps' => SetupWizard::steps()];
    }

    public function save_setup(WP_REST_Request $request): array
    {
        $params = $request->get_params();

        return ['state' => SetupWizard::save_state($params)];
    }
}
