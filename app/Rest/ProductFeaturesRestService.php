<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest;

use Webactueel\Translate\Rest\Concerns\ChecksRestPermissions;
use Webactueel\Translate\Rest\Concerns\RestRouteArguments;
use Webactueel\Translate\Automation\TranslationJobQueue;
use Webactueel\Translate\Automation\AiTranslationService;
use Webactueel\Translate\Automation\AiUsageLedger;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Settings;
use Webactueel\Translate\Workflow\TranslatorRoles;
use Webactueel\Translate\Performance\PerformanceMonitor;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Setup\SetupWizard;
use Webactueel\Translate\Workflow\TranslationContextReport;
use Webactueel\Translate\Workflow\TranslationQualityReport;
use Webactueel\Translate\Workflow\WorkflowStatus;
use Webactueel\Translate\Workflow\AssignmentManager;
use WP_REST_Request;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only QA endpoints query plugin-owned custom tables; table identifiers are normalized through Tables::sql_identifier().

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
        register_rest_route($this->namespace, '/setup/recommendations', [
            'methods' => 'GET',
            'callback' => [$this, 'setup_recommendations'],
            'permission_callback' => [$this, 'can_translate'],
        ]);
        register_rest_route($this->namespace, '/seo/health', [
            'methods' => 'GET',
            'callback' => [$this, 'seo_health'],
            'permission_callback' => [$this, 'can_translate'],
        ]);
        register_rest_route($this->namespace, '/automation/cost-estimate', [
            'methods' => 'GET',
            'callback' => [$this, 'ai_cost_estimate'],
            'permission_callback' => [$this, 'can_manage'],
        ]);
        register_rest_route($this->namespace, '/woocommerce/safe-mode', [
            'methods' => 'GET',
            'callback' => [$this, 'woocommerce_safe_mode'],
            'permission_callback' => [$this, 'can_translate'],
        ]);
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

    /**
     * Lightweight SEO readiness report for multilingual publishing.
     *
     * @return array<string, mixed>
     */
    public function seo_health(): array
    {
        $settings = Settings::all();
        $checks = [];
        $checks[] = [
            'key' => 'hreflang',
            'status' => ! empty($settings['hreflang_enabled']) ? 'pass' : 'warn',
            'label' => __('Hreflang-output', 'webactueel-translate-language-dropdowns'),
            'detail' => ! empty($settings['hreflang_enabled']) ? __('Ingeschakeld.', 'webactueel-translate-language-dropdowns') : __('Uitgeschakeld; controleer meertalige indexatie handmatig.', 'webactueel-translate-language-dropdowns'),
        ];
        $checks[] = [
            'key' => 'canonical',
            'status' => ! empty($settings['canonical_enabled']) ? 'pass' : 'warn',
            'label' => __('Per-taal canonicals', 'webactueel-translate-language-dropdowns'),
            'detail' => ! empty($settings['canonical_enabled']) ? __('Ingeschakeld.', 'webactueel-translate-language-dropdowns') : __('Uitgeschakeld; canonical-conflicten zijn handmatig te controleren.', 'webactueel-translate-language-dropdowns'),
        ];
        $checks[] = [
            'key' => 'sitemap',
            'status' => ! empty($settings['multilingual_sitemap_enabled']) ? 'pass' : 'info',
            'label' => __('Meertalige sitemap', 'webactueel-translate-language-dropdowns'),
            'detail' => ! empty($settings['multilingual_sitemap_enabled']) ? esc_url_raw(home_url('/?wat_language_sitemap=1')) : __('Sitemap-output staat uit.', 'webactueel-translate-language-dropdowns'),
        ];
        $checks[] = [
            'key' => 'seo_plugin',
            'status' => (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) ? 'pass' : 'info',
            'label' => __('SEO-plugin integratie', 'webactueel-translate-language-dropdowns'),
            'detail' => defined('WPSEO_VERSION') ? 'Yoast SEO' : (defined('RANK_MATH_VERSION') ? 'Rank Math' : __('Geen ondersteunde SEO-plugin gedetecteerd.', 'webactueel-translate-language-dropdowns')),
        ];

        $summary = ['pass' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
        foreach ($checks as $check) {
            $status = isset($check['status']) && is_string($check['status']) ? $check['status'] : 'info';
            if (isset($summary[$status])) {
                ++$summary[$status];
            }
        }

        return ['ok' => $summary['fail'] === 0, 'summary' => $summary, 'checks' => $checks];
    }

    /**
     * Estimate remaining AI batch volume without using provider pricing claims.
     *
     * @return array<string, mixed>
     */
    public function ai_cost_estimate(): array
    {
        global $wpdb;
        $stringsTable = Tables::sql_identifier(Tables::strings());
        $translationsTable = Tables::sql_identifier(Tables::translations());
        $missing = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$stringsTable}` s LEFT JOIN `{$translationsTable}` t ON t.string_id = s.id WHERE t.id IS NULL");
        $sample = (array) $wpdb->get_col("SELECT source_text FROM `{$stringsTable}` ORDER BY id DESC LIMIT 100");
        $characters = 0;
        foreach ($sample as $text) {
            $characters += function_exists('mb_strlen') ? mb_strlen((string) $text) : strlen((string) $text);
        }
        $average = $sample !== [] ? (int) ceil($characters / max(1, count($sample))) : 0;
        $estimatedCharacters = $missing * $average;

        return [
            'missing_strings' => $missing,
            'sample_size' => count($sample),
            'average_source_characters' => $average,
            'estimated_source_characters' => $estimatedCharacters,
            'estimated_tokens_note' => __('Gebruik dit als ruwe volume-indicatie. Werkelijke tokenkosten verschillen per provider, taal, model en prompt.', 'webactueel-translate-language-dropdowns'),
            'recommended_batch_size' => min(20, max(1, (int) apply_filters('wat_ai_recommended_batch_size', 10))),
            'review_required' => ! empty(Settings::all()['ai_review_required']),
        ];
    }

    /**
     * Explain WooCommerce safety state and recommended manual checks.
     *
     * @return array<string, mixed>
     */
    public function woocommerce_safe_mode(): array
    {
        $settings = Settings::all();
        $woocommerceActive = class_exists('WooCommerce');
        $excludedPaths = isset($settings['exclude_paths']) && is_scalar($settings['exclude_paths']) ? (string) $settings['exclude_paths'] : '';
        $checks = [
            [
                'key' => 'woocommerce_active',
                'status' => $woocommerceActive ? 'pass' : 'info',
                'label' => __('WooCommerce actief', 'webactueel-translate-language-dropdowns'),
                'detail' => $woocommerceActive ? __('WooCommerce is actief.', 'webactueel-translate-language-dropdowns') : __('WooCommerce is niet actief.', 'webactueel-translate-language-dropdowns'),
            ],
            [
                'key' => 'safe_mode',
                'status' => ! empty($settings['safe_mode']) ? 'pass' : 'warn',
                'label' => __('Veilige frontendmodus', 'webactueel-translate-language-dropdowns'),
                'detail' => ! empty($settings['safe_mode']) ? __('Ingeschakeld.', 'webactueel-translate-language-dropdowns') : __('Uitgeschakeld; test checkout, cart, account en order-pay handmatig.', 'webactueel-translate-language-dropdowns'),
            ],
            [
                'key' => 'excluded_paths',
                'status' => str_contains($excludedPaths, '/checkout/') && str_contains($excludedPaths, '/cart/') ? 'pass' : 'warn',
                'label' => __('Checkout/cart uitgesloten', 'webactueel-translate-language-dropdowns'),
                'detail' => $excludedPaths,
            ],
        ];

        return [
            'checks' => $checks,
            'manual_tests' => [
                __('Cart met coupon en verzendkosten.', 'webactueel-translate-language-dropdowns'),
                __('Checkout met gastbestelling en bestaande klant.', 'webactueel-translate-language-dropdowns'),
                __('Order-pay, accountpagina en order-e-mails.', 'webactueel-translate-language-dropdowns'),
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
