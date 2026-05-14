<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend;

use Webactueel\Translate\Seo\HreflangManager;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

final class HreflangRenderer
{
    public static function render(): void
    {
        foreach (HreflangManager::tags() as $tag) {
            if (! is_array($tag)) {
                continue;
            }

            $hreflang = Input::text($tag['hreflang'] ?? '');
            $href = esc_url_raw(Input::scalar_string($tag['href'] ?? ''));
            if ($hreflang === '' || $href === '') {
                continue;
            }

            echo '<link rel="alternate" hreflang="' . esc_attr($hreflang) . '" href="' . esc_url($href) . '" />' . "\n";
        }
    }
}
