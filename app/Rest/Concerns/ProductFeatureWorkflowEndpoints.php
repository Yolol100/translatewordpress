<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Workflow\AssignmentManager;
use Webactueel\Translate\Workflow\TranslationContextReport;
use Webactueel\Translate\Workflow\TranslationQualityReport;
use WP_REST_Request;

if (! defined('ABSPATH')) {
    exit;
}

trait ProductFeatureWorkflowEndpoints
{
    public function workflow_statuses(): array
    {
        return [
            'statuses' => [
                'new' => __('Nieuw', 'webactueel-translate-language-dropdowns'),
                'untranslated' => __('Onvertaald', 'webactueel-translate-language-dropdowns'),
                'draft' => __('Concept', 'webactueel-translate-language-dropdowns'),
                'needs_review' => __('Review nodig', 'webactueel-translate-language-dropdowns'),
                'machine' => __('Machinevertaling', 'webactueel-translate-language-dropdowns'),
                'manual' => __('Handmatig aangepast', 'webactueel-translate-language-dropdowns'),
                'reviewed' => __('Gecontroleerd', 'webactueel-translate-language-dropdowns'),
                'published' => __('Gepubliceerd', 'webactueel-translate-language-dropdowns'),
                'ignored' => __('Negeren', 'webactueel-translate-language-dropdowns'),
                'outdated' => __('Verouderd', 'webactueel-translate-language-dropdowns'),
            ],
        ];
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
}
