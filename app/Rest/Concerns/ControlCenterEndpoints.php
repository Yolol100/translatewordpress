<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Seo\SeoAuditService;
use Webactueel\Translate\Translation\TranslationCoverageReporter;
use Webactueel\Translate\WooCommerce\WooCommerceCoverageReporter;

if (! defined('ABSPATH')) {
    exit;
}

trait ControlCenterEndpoints
{
    /** @return array<string, mixed> */
    public function seo_audit(): array
    {
        return (new SeoAuditService())->report();
    }

    /** @return array<string, mixed> */
    public function translation_coverage(): array
    {
        return TranslationCoverageReporter::summary();
    }

    /** @return array<string, mixed> */
    public function woocommerce_coverage(): array
    {
        return (new WooCommerceCoverageReporter())->report();
    }
}
