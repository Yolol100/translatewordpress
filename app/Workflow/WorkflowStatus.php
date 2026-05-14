<?php

declare(strict_types=1);

namespace Webactueel\Translate\Workflow;

if (! defined('ABSPATH')) {
    exit;
}

final class WorkflowStatus
{
    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
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
        ];
    }

    public static function normalize(string $status): string
    {
        $status = sanitize_key($status);
        return array_key_exists($status, self::labels()) ? $status : 'published';
    }
}
