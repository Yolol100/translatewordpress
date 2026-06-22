<?php

declare(strict_types=1);

namespace Webactueel\Translate\VisualEditor\Concerns;

use Webactueel\Translate\Support\Settings;
use WP_Error;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

trait VisualEditorRestResponses
{
    private function review_status_for_request(string $requestedStatus, string $translation): string
    {
        $status = $requestedStatus !== '' ? $requestedStatus : 'published';
        if ($translation === '' && in_array($status, ['published', 'reviewed'], true)) {
            $status = 'draft';
        }
        if (! in_array($status, ['draft', 'needs_review', 'reviewed', 'published'], true)) {
            $status = 'published';
        }
        if (! current_user_can('manage_options') && ! empty(Settings::all()['translator_review_required']) && in_array($status, ['published', 'reviewed'], true)) {
            return 'needs_review';
        }

        return $status;
    }

    private function save_message(string $status): string
    {
        if ($status === 'published') {
            return __('Vertaling gepubliceerd.', 'webactueel-translate-language-dropdowns');
        }
        if ($status === 'reviewed') {
            return __('Vertaling gemarkeerd als reviewed.', 'webactueel-translate-language-dropdowns');
        }
        if ($status === 'needs_review') {
            return __('Vertaling opgeslagen ter review.', 'webactueel-translate-language-dropdowns');
        }

        return __('Vertaling opgeslagen als concept.', 'webactueel-translate-language-dropdowns');
    }

    private function error_response(WP_Error $error): WP_REST_Response
    {
        $data = $error->get_error_data();
        $status = is_array($data) && isset($data['status']) ? absint($data['status']) : 400;

        return new WP_REST_Response([
            'code' => $error->get_error_code(),
            'message' => $error->get_error_message(),
        ], $status > 0 ? $status : 400);
    }
}
