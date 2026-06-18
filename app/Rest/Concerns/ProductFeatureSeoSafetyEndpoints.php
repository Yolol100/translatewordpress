<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Performance\PerformanceMonitor;
use Webactueel\Translate\Seo\SeoAuditService;
use Webactueel\Translate\WooCommerce\WooCommerceCoverageReporter;

if (! defined('ABSPATH')) {
    exit;
}

trait ProductFeatureSeoSafetyEndpoints
{
    /**
     * Lightweight SEO readiness report for multilingual publishing.
     *
     * @return array<string, mixed>
     */
    public function seo_health(): array
    {
        return (new SeoAuditService())->report();
    }

    /**
     * Explain WooCommerce safety state and recommended manual checks.
     *
     * @return array<string, mixed>
     */
    public function woocommerce_safe_mode(): array
    {
        return (new WooCommerceCoverageReporter())->report();
    }

    public function performance_snapshot(): array
    {
        return ['snapshot' => PerformanceMonitor::snapshot()];
    }
}
