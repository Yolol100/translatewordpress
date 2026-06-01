<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest;

use Webactueel\Translate\Rest\Concerns\CsvEndpoints;
use Webactueel\Translate\Rest\Concerns\DashboardLanguageEndpoints;
use Webactueel\Translate\Rest\Concerns\GlossarySettingsEndpoints;
use Webactueel\Translate\Rest\Concerns\HealthCheckEndpoints;
use Webactueel\Translate\Rest\Concerns\RegistersRestRoutes;
use Webactueel\Translate\Rest\Concerns\ScanEndpoints;
use Webactueel\Translate\Rest\Concerns\TranslationEndpoints;
use Webactueel\Translate\Rest\Concerns\ChecksRestPermissions;

if (! defined('ABSPATH')) {
    exit;
}

final class RestServiceProvider
{
    use ChecksRestPermissions;
    use RegistersRestRoutes;
    use DashboardLanguageEndpoints;
    use TranslationEndpoints;
    use ScanEndpoints;
    use CsvEndpoints;
    use GlossarySettingsEndpoints;
    use HealthCheckEndpoints;

    private string $namespace = 'webactueel-translate-language-dropdowns/v1';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
        add_filter('rest_pre_serve_request', [$this, 'serve_raw_export'], 10, 4);
    }
}
