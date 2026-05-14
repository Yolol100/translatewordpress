<?php

declare(strict_types=1);

namespace Webactueel\Translate\Translation;

use Webactueel\Translate\Translation\Concerns\TranslationMemoryAndMap;
use Webactueel\Translate\Translation\Concerns\TranslationQueries;
use Webactueel\Translate\Translation\Concerns\TranslationStringWrites;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: custom prefixed tables and public wat_* hooks are intentional.

final class TranslationRepository
{
    use TranslationStringWrites;
    use TranslationQueries;
    use TranslationMemoryAndMap;
}
