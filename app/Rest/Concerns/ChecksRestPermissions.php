<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Workflow\TranslatorRoles;

if (! defined('ABSPATH')) {
    exit;
}

trait ChecksRestPermissions
{
    /**
     * Admin-only REST capability for settings, language management, logs, and cache actions.
     */
    public function can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Translation-workflow capability for editors and the dedicated translator role.
     */
    public function can_translate(): bool
    {
        return TranslatorRoles::can_translate();
    }
}
