<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest;

use Webactueel\Translate\Rest\Concerns\ChecksRestPermissions;
use Webactueel\Translate\Rest\Concerns\ProductFeatureAutomationEndpoints;
use Webactueel\Translate\Rest\Concerns\ProductFeatureRouteDefinitions;
use Webactueel\Translate\Rest\Concerns\ProductFeatureSeoSafetyEndpoints;
use Webactueel\Translate\Rest\Concerns\ProductFeatureSetupEndpoints;
use Webactueel\Translate\Rest\Concerns\ProductFeatureWorkflowEndpoints;
use Webactueel\Translate\Rest\Concerns\RestRouteArguments;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductFeaturesRestService
{
    use ChecksRestPermissions;
    use RestRouteArguments;
    use ProductFeatureRouteDefinitions;
    use ProductFeatureSetupEndpoints;
    use ProductFeatureSeoSafetyEndpoints;
    use ProductFeatureWorkflowEndpoints;
    use ProductFeatureAutomationEndpoints;

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
}
