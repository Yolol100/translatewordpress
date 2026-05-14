<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Workflow\TranslatorRoles;

if (! defined('ABSPATH')) {
    exit;
}

trait ChecksRestPermissions
{
    public function can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    public function can_translate(): bool
    {
        return TranslatorRoles::can_translate();
    }
}
