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

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
// Reviewed: custom prefixed tables and public wat_* hooks are intentional.

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
    }
}
