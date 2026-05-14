<?php

declare(strict_types=1);

namespace Webactueel\Translate\VisualEditor;

if (! defined('ABSPATH')) {
    exit;
}

final class BuilderCompatibility
{
    /**
     * @return array<int, array{key:string,label:string,active:bool}>
     */
    public static function detect_active_builders(): array
    {
        $builders = [
            ['key' => 'elementor', 'label' => 'Elementor', 'active' => did_action('elementor/loaded') > 0 || defined('ELEMENTOR_VERSION')],
            ['key' => 'divi', 'label' => 'Divi', 'active' => defined('ET_BUILDER_VERSION') || function_exists('et_builder_init_plugin')],
            ['key' => 'wpbakery', 'label' => 'WPBakery', 'active' => defined('WPB_VC_VERSION') || class_exists('Vc_Manager')],
            ['key' => 'visual-composer', 'label' => 'Visual Composer', 'active' => defined('VCV_VERSION') || class_exists('VisualComposer\Framework\Illuminate\Support\Facades\vcapp')],
            ['key' => 'beaver-builder', 'label' => 'Beaver Builder', 'active' => class_exists('FLBuilder')],
            ['key' => 'oxygen', 'label' => 'Oxygen', 'active' => defined('CT_VERSION') || class_exists('OxygenElement')],
            ['key' => 'bricks', 'label' => 'Bricks', 'active' => defined('BRICKS_VERSION') || class_exists('Bricks\Theme')],
        ];

        return array_map(static function (array $builder): array {
            $builder['active'] = (bool) $builder['active'];
            return $builder;
        }, $builders);
    }

    /**
     * @return string[]
     */
    public static function protected_selectors(): array
    {
        return [
            '#wpadminbar',
            '.woocommerce-checkout',
            '.woocommerce-cart',
            '.woocommerce-account',
            '.payment_box',
            '.wc_payment_methods',
            'form.checkout',
            'script',
            'style',
            'noscript',
            'svg',
            'code',
            'pre',
            'textarea',
            'input',
            'select',
            'option',
        ];
    }
}
