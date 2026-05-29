<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest;

use Webactueel\Translate\Rest\Concerns\ChecksRestPermissions;
use Webactueel\Translate\Rest\Concerns\RestRouteArguments;
use Webactueel\Translate\Automation\TranslationJobQueue;
use Webactueel\Translate\Automation\AiTranslationService;
use Webactueel\Translate\Automation\AiUsageLedger;
use Webactueel\Translate\Performance\PerformanceMonitor;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Setup\SetupWizard;
use Webactueel\Translate\Workflow\TranslationContextReport;
use Webactueel\Translate\Workflow\TranslationQualityReport;
use Webactueel\Translate\Workflow\WorkflowStatus;
use Webactueel\Translate\Workflow\AssignmentManager;
use Webactueel\Translate\Workflow\ContentReadinessReport;
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

    public static function validate_ai_translation_text($value): bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        $text = trim(wp_kses_post(str_replace("\0", '', (string) $value)));
        if ($text === '' || trim(wp_strip_all_tags($text)) === '') {
            return false;
        }

        return function_exists('mb_strlen') ? mb_strlen($text) <= 5000 : strlen($text) <= 5000;
    }

    /** @return array<string, array<string, mixed>> */
    private function setup_args(): array
    {
        return [
            'completed' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            'current_step' => ['type' => 'string', 'enum' => ['start', 'settings', 'translate', 'workflow', 'visual', 'advanced', 'done'], 'sanitize_callback' => 'sanitize_key'],
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
            'assigned_user_id' => ['type' => 'integer', 'minimum' => 0, 'sanitize_callback' => 'absint'],
            'due_at' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
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
            'assigned_user_id' => ['type' => 'integer', 'minimum' => 0, 'sanitize_callback' => 'absint'],
            'due_at' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        ]);
    }

    public function routes(): void
    {
        register_rest_route($this->namespace, '/setup', [
            ['methods' => 'GET', 'callback' => [$this, 'setup'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => ['PUT', 'POST'], 'callback' => [$this, 'save_setup'], 'permission_callback' => [$this, 'can_manage'], 'args' => $this->setup_args()],
        ]);
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
            'permission_callback' => [$this, 'can_manage'],
        ]);
        register_rest_route($this->namespace, '/workflow/content', [
            'methods' => 'GET',
            'callback' => [$this, 'workflow_content'],
            'permission_callback' => [$this, 'can_translate'],
            'args' => $this->language_report_args(),
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
        $params = $request->get_params();

        return ['state' => SetupWizard::save_state($params)];
    }

    public function workflow_statuses(): array
    {
        return ['statuses' => WorkflowStatus::labels()];
    }

    public function workflow_quality(WP_REST_Request $request): array
    {
        return ['quality' => TranslationQualityReport::for_language(Input::key($request->get_param('language')))];
    }

    public function workflow_context(WP_REST_Request $request): array
    {
        return [
            'context' => TranslationContextReport::for_language(
                Input::key($request->get_param('language')),
                absint($request->get_param('limit') ?: 20)
            ),
        ];
    }


    public function workflow_content(WP_REST_Request $request): array
    {
        return [
            'content' => ContentReadinessReport::for_language(
                Input::key($request->get_param('language')),
                absint($request->get_param('limit') ?: 8)
            ),
        ];
    }

    public function workflow_assignees(): array
    {
        return ['assignees' => AssignmentManager::assignees()];
    }

    public function workflow_jobs(WP_REST_Request $request): array
    {
        return ['jobs' => AssignmentManager::list_jobs(absint($request->get_param('limit') ?: 20))];
    }

    public function workflow_assign_job(WP_REST_Request $request)
    {
        return AssignmentManager::assign(
            absint($request['id']),
            absint($request->get_param('assigned_user_id') ?: 0),
            Input::scalar_string($request->get_param('due_at') ?: '')
        );
    }

    public function automation_capabilities(): array
    {
        return array_merge(TranslationJobQueue::capabilities(), ['ai' => AiTranslationService::capabilities()]);
    }

    public function ai_translate(WP_REST_Request $request)
    {
        $params = $request->get_params();
        return (new AiTranslationService())->translate(
            Input::scalar_string($params['text'] ?? ''),
            Input::key($params['source_language'] ?? ''),
            Input::key($params['target_language'] ?? ''),
            $params
        );
    }

    public function ai_usage(WP_REST_Request $request): array
    {
        return ['usage' => AiUsageLedger::summary(absint($request->get_param('days') ?: 30))];
    }

    public function ai_job_enqueue(WP_REST_Request $request)
    {
        $jobId = TranslationJobQueue::enqueue($request->get_params());
        $job = TranslationJobQueue::get_job($jobId);
        return is_wp_error($job) ? $job : ['job' => $job];
    }

    public function ai_job(WP_REST_Request $request)
    {
        $job = TranslationJobQueue::get_job(absint($request['id']));
        return is_wp_error($job) ? $job : ['job' => $job];
    }

    public function ai_job_run_batch(WP_REST_Request $request)
    {
        $job = TranslationJobQueue::run_batch(absint($request['id']), absint($request->get_param('batch_size') ?: 5));
        return is_wp_error($job) ? $job : ['job' => $job];
    }

    public function performance_snapshot(): array
    {
        return ['snapshot' => PerformanceMonitor::snapshot()];
    }
}
