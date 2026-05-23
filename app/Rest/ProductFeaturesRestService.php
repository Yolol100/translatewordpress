<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest;

use Webactueel\Translate\Rest\Concerns\ChecksRestPermissions;
use Webactueel\Translate\Rest\Concerns\RestRouteArguments;
use Webactueel\Translate\Automation\TranslationJobQueue;
use Webactueel\Translate\Automation\AiTranslationService;
use Webactueel\Translate\Performance\PerformanceMonitor;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Setup\SetupWizard;
use Webactueel\Translate\Workflow\WorkflowStatus;
use WP_REST_Request;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductFeaturesRestService
{
    use ChecksRestPermissions;
    use RestRouteArguments;

    private string $namespace = 'webactueel-translate-language-dropdowns/v1';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }



    public static function validate_optional_language_code($value): bool
    {
        return $value === null || $value === '' || self::validate_language_code($value);
    }

    /** @return array<string, array<string, mixed>> */
    private function setup_args(): array
    {
        return [
            'completed' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            'current_step' => ['type' => 'string', 'enum' => ['languages', 'routing', 'switcher', 'scan', 'safety', 'done'], 'sanitize_callback' => 'sanitize_key'],
            'completed_steps' => [
                'validate_callback' => [self::class, 'validate_key_list'],
                'sanitize_callback' => static function ($value): array {
                    return Input::key_list($value);
                },
            ],
            'dismissed' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
        ];
    }

    public function routes(): void
    {
        register_rest_route($this->namespace, '/setup', [
            ['methods' => 'GET', 'callback' => [$this, 'setup'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'PUT', 'callback' => [$this, 'save_setup'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->setup_args()],
        ]);
        register_rest_route($this->namespace, '/workflow/statuses', [
            'methods' => 'GET', 'callback' => [$this, 'workflow_statuses'], 'permission_callback' => [$this, 'can_translate'],
        ]);
        register_rest_route($this->namespace, '/automation/capabilities', [
            'methods' => 'GET', 'callback' => [$this, 'automation_capabilities'], 'permission_callback' => [$this, 'can_manage'],
        ]);
        register_rest_route($this->namespace, '/automation/translate', [
            'methods' => 'POST',
            'callback' => [$this, 'ai_translate'],
            'permission_callback' => [$this, 'can_manage'],
            'args' => [
                'text' => [
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'wp_kses_post',
                    'validate_callback' => static fn($value): bool => is_scalar($value) && trim(wp_strip_all_tags((string) $value)) !== '' && (function_exists('mb_strlen') ? mb_strlen(wp_strip_all_tags((string) $value)) <= 5000 : strlen(wp_strip_all_tags((string) $value)) <= 5000),
                ],
                'source_language' => ['type' => 'string', 'validate_callback' => [self::class, 'validate_optional_language_code'], 'sanitize_callback' => 'sanitize_key'],
                'target_language' => ['type' => 'string', 'required' => true, 'validate_callback' => [self::class, 'validate_language_code'], 'sanitize_callback' => 'sanitize_key'],
            ],
        ]);
        register_rest_route($this->namespace, '/performance/snapshot', [
            'methods' => 'GET', 'callback' => [$this, 'performance_snapshot'], 'permission_callback' => [$this, 'can_manage'],
        ]);
    }

    public function setup(): array
    {
        return ['state' => SetupWizard::state(), 'steps' => SetupWizard::steps()];
    }

    public function save_setup(WP_REST_Request $request): array
    {
        $params = $request->get_json_params();
        if (! is_array($params)) {
            $params = [];
        }

        return ['state' => SetupWizard::save_state($params)];
    }

    public function workflow_statuses(): array
    {
        return ['statuses' => WorkflowStatus::labels()];
    }

    public function automation_capabilities(): array
    {
        return array_merge(TranslationJobQueue::capabilities(), ['ai' => AiTranslationService::capabilities()]);
    }

    public function ai_translate(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        if (! is_array($params)) {
            $params = [];
        }
        return (new AiTranslationService())->translate(
            Input::scalar_string($params['text'] ?? ''),
            Input::key($params['source_language'] ?? ''),
            Input::key($params['target_language'] ?? ''),
            $params
        );
    }

    public function performance_snapshot(): array
    {
        return ['snapshot' => PerformanceMonitor::snapshot()];
    }
}
