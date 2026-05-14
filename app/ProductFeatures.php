<?php

declare(strict_types=1);

namespace Webactueel\Translate;

use Webactueel\Translate\Blocks\LanguageSwitcherBlock;
use Webactueel\Translate\Media\MediaTranslationManager;
use Webactueel\Translate\Performance\PerformanceMonitor;
use Webactueel\Translate\Rest\ProductFeaturesRestService;
use Webactueel\Translate\Seo\SeoMetaManager;
use Webactueel\Translate\Seo\MultilingualSitemapManager;
use Webactueel\Translate\VisualEditor\VisualEditor;
use Webactueel\Translate\VisualEditor\VisualEditorRestService;
use Webactueel\Translate\WooCommerce\WooCommerceSupport;

if (! defined('ABSPATH')) {
    exit;
}

final class ProductFeatures
{
    public function register(): void
    {
        (new ProductFeaturesRestService())->register();
        (new VisualEditor())->register();
        (new VisualEditorRestService())->register();
        (new LanguageSwitcherBlock())->register();
        (new SeoMetaManager())->register();
        (new MultilingualSitemapManager())->register();
        (new MediaTranslationManager())->register();
        (new WooCommerceSupport())->register();
        (new PerformanceMonitor())->register();
    }
}
