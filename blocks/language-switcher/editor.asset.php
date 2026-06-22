<?php
if (! defined('ABSPATH')) {
    exit;
}

return [
    'dependencies' => ['wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n', 'wp-server-side-render'],
    'version' => defined('WAT_VERSION') ? WAT_VERSION : '2.7.82',
];
