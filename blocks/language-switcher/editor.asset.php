<?php
if (! defined('ABSPATH')) {
    exit;
}

return [
    'dependencies' => ['wp-blocks', 'wp-element', 'wp-i18n', 'wp-server-side-render'],
    'version' => defined('WAT_VERSION') ? WAT_VERSION : '1.6.46',
];
