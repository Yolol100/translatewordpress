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

    /**
     * Privileged scan capability for potentially heavy content indexing tasks.
     */
    public function can_scan(): bool
    {
        return TranslatorRoles::can_scan();
    }

    /**
     * Privileged import/export capability for bulk translation data movement.
     */
    public function can_import_export(): bool
    {
        return TranslatorRoles::can_import_export();
    }
}
