<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

trait ProductFeatureRouteDefinitions
{
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

    /** @return array<string, array<string, mixed>> */
    private function language_report_args(): array
    {
        return [
            'language' => [
                'type' => 'string',
                'required' => true,
                'validate_callback' => [self::class, 'validate_language_code'],
                'sanitize_callback' => 'sanitize_key',
            ],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'sanitize_callback' => 'absint'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function ai_job_args(): array
    {
        return [
            'target_language' => [
                'type' => 'string',
                'required' => true,
                'validate_callback' => [self::class, 'validate_language_code'],
                'sanitize_callback' => 'sanitize_key',
            ],
            'source_language' => [
                'type' => 'string',
                'validate_callback' => [self::class, 'validate_optional_language_code'],
                'sanitize_callback' => 'sanitize_key',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['new', 'missing', 'draft', 'needs_review', 'reviewed', 'published', 'outdated'],
                'sanitize_callback' => 'sanitize_key',
            ],
            'batch_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'sanitize_callback' => 'absint'],
            'assigned_user_id' => ['type' => 'integer', 'minimum' => 0, 'sanitize_callback' => 'absint', 'validate_callback' => [self::class, 'validate_assignee_id']],
            'due_at' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_due_at']],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function ai_job_batch_args(): array
    {
        return array_merge($this->id_arg(), [
            'batch_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'sanitize_callback' => 'absint'],
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function assignment_args(): array
    {
        return array_merge($this->id_arg(), [
            'assigned_user_id' => ['type' => 'integer', 'minimum' => 0, 'sanitize_callback' => 'absint', 'validate_callback' => [self::class, 'validate_assignee_id']],
            'due_at' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => [self::class, 'validate_due_at']],
        ]);
    }

    public function routes(): void
    {
        $this->register_setup_routes();
        $this->register_safety_routes();
        $this->register_workflow_routes();
        $this->register_automation_routes();
        $this->register_performance_routes();
    }

    private function register_setup_routes(): void
    {
        register_rest_route($this->namespace, '/setup/recommendations', [
            'methods' => 'GET',
            'callback' => [$this, 'setup_recommendations'],
            'permission_callback' => [$this, 'can_translate'],
        ]);
        register_rest_route($this->namespace, '/setup', [
            ['methods' => 'GET', 'callback' => [$this, 'setup'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => ['PUT', 'POST'], 'callback' => [$this, 'save_setup'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->setup_args()],
        ]);
    }

    private function register_safety_routes(): void
    {
        register_rest_route($this->namespace, '/seo/health', [
            'methods' => 'GET',
            'callback' => [$this, 'seo_health'],
            'permission_callback' => [$this, 'can_translate'],
        ]);
        register_rest_route($this->namespace, '/woocommerce/safe-mode', [
            'methods' => 'GET',
            'callback' => [$this, 'woocommerce_safe_mode'],
            'permission_callback' => [$this, 'can_translate'],
        ]);
    }

    private function register_workflow_routes(): void
    {
        register_rest_route($this->namespace, '/workflow/statuses', [
            'methods' => 'GET', 'callback' => [$this, 'workflow_statuses'], 'permission_callback' => [$this, 'can_translate'],
        ]);
        register_rest_route($this->namespace, '/workflow/quality', [
            'methods' => 'GET',
            'callback' => [$this, 'workflow_quality'],
            'permission_callback' => [$this, 'can_translate'],
            'args' => $this->language_report_args(),
        ]);
        register_rest_route($this->namespace, '/workflow/context', [
            'methods' => 'GET',
            'callback' => [$this, 'workflow_context'],
            'permission_callback' => [$this, 'can_translate'],
            'args' => $this->language_report_args(),
        ]);
        register_rest_route($this->namespace, '/workflow/assignees', [
            'methods' => 'GET',
            'callback' => [$this, 'workflow_assignees'],
            'permission_callback' => [$this, 'can_translate'],
        ]);
        register_rest_route($this->namespace, '/workflow/jobs', [
            'methods' => 'GET',
            'callback' => [$this, 'workflow_jobs'],
            'permission_callback' => [$this, 'can_translate'],
            'args' => ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'sanitize_callback' => 'absint']],
        ]);
        register_rest_route($this->namespace, '/workflow/jobs/(?P<id>\d+)/assignment', [
            'methods' => ['PUT', 'POST'],
            'callback' => [$this, 'workflow_assign_job'],
            'permission_callback' => [$this, 'can_manage'],
            'args' => $this->assignment_args(),
        ]);
    }

    private function register_automation_routes(): void
    {
        register_rest_route($this->namespace, '/automation/cost-estimate', [
            'methods' => 'GET',
            'callback' => [$this, 'ai_cost_estimate'],
            'permission_callback' => [$this, 'can_manage'],
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
                    'validate_callback' => [self::class, 'validate_ai_translation_text'],
                ],
                'source_language' => ['type' => 'string', 'validate_callback' => [self::class, 'validate_optional_language_code'], 'sanitize_callback' => 'sanitize_key'],
                'target_language' => ['type' => 'string', 'required' => true, 'validate_callback' => [self::class, 'validate_language_code'], 'sanitize_callback' => 'sanitize_key'],
            ],
        ]);
        register_rest_route($this->namespace, '/automation/usage', [
            'methods' => 'GET',
            'callback' => [$this, 'ai_usage'],
            'permission_callback' => [$this, 'can_manage'],
            'args' => ['days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'sanitize_callback' => 'absint']],
        ]);
        register_rest_route($this->namespace, '/automation/jobs', [
            'methods' => 'POST',
            'callback' => [$this, 'ai_job_enqueue'],
            'permission_callback' => [$this, 'can_manage'],
            'args' => $this->ai_job_args(),
        ]);
        register_rest_route($this->namespace, '/automation/jobs/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'ai_job'],
            'permission_callback' => [$this, 'can_manage'],
            'args' => $this->id_arg(),
        ]);
        register_rest_route($this->namespace, '/automation/jobs/(?P<id>\d+)/run-batch', [
            'methods' => 'POST',
            'callback' => [$this, 'ai_job_run_batch'],
            'permission_callback' => [$this, 'can_manage'],
            'args' => $this->ai_job_batch_args(),
        ]);
    }

    private function register_performance_routes(): void
    {
        register_rest_route($this->namespace, '/performance/snapshot', [
            'methods' => 'GET', 'callback' => [$this, 'performance_snapshot'], 'permission_callback' => [$this, 'can_manage'],
        ]);
    }

}
