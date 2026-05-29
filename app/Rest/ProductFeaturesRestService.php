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
                'enum' => ['new', 'missing', 'draft', 'needs_review', 'reviewed', 'published'],
                'sanitize_callback' => 'sanitize_key',
            ],
            'batch_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'sanitize_callback' => 'absint'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function ai_job_batch_args(): array
    {
        return array_merge($this->id_arg(), [
            'batch_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'sanitize_callback' => 'absint'],
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

    public function ai_job_enqueue(WP_REST_Request $request): array
    {
        $jobId = TranslationJobQueue::enqueue($request->get_params());
        return ['job' => TranslationJobQueue::get_job($jobId)];
    }

    public function ai_job(WP_REST_Request $request)
    {
        return ['job' => TranslationJobQueue::get_job(absint($request['id']))];
    }

    public function ai_job_run_batch(WP_REST_Request $request)
    {
        return ['job' => TranslationJobQueue::run_batch(absint($request['id']), absint($request->get_param('batch_size') ?: 5))];
    }

    public function performance_snapshot(): array
    {
        return ['snapshot' => PerformanceMonitor::snapshot()];
    }
}
