<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Concerns;

use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

trait OutputBufferRequestGuards
{
    /**
     * @return array<string, string|bool>
     */
    private function request_context(): array
    {
        $uri = Input::server_text('REQUEST_URI');
        $path = Input::scalar_string(wp_parse_url($uri, PHP_URL_PATH));

        return [
            'uri' => $uri,
            'path' => $path,
            'method' => Input::server_method(),
            'is_user_logged_in' => function_exists('is_user_logged_in') ? is_user_logged_in() : false,
            'is_admin_bar_showing' => function_exists('is_admin_bar_showing') ? is_admin_bar_showing() : false,
        ];
    }

    private function is_html_response(string $html): bool
    {
        if ($html === '' || (stripos($html, '<html') === false && stripos($html, '<body') === false)) {
            return false;
        }
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) {
                return false;
            }
        }
        return true;
    }
}
