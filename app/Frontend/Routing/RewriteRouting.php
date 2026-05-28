<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend\Routing;

if (! defined('ABSPATH')) {
    exit;
}

trait RewriteRouting
{
    use RewriteRequestFiltering;
    public static function register_rewrite_rules(): void
    {
        add_rewrite_tag('%wat_language%', '([^&]+)');
        add_rewrite_tag('%wat_path%', '([^&]*)');

        $codes = self::rewrite_language_codes();
        if (! $codes) {
            return;
        }

        $regex = implode('|', array_map('preg_quote', $codes));
        add_rewrite_rule('^(' . $regex . ')/?$', 'index.php?wat_language=$matches[1]&wat_path=', 'top');
        add_rewrite_rule('^(' . $regex . ')/(.*)/?$', 'index.php?wat_language=$matches[1]&wat_path=$matches[2]', 'top');
    }

    public static function query_vars(array $vars): array
    {
        $vars[] = 'wat_language';
        $vars[] = 'wat_path';
        $vars[] = 'wat_switch_lang';
        return array_values(array_unique($vars));
    }

    public static function maybe_flush_rewrite_rules(): void
    {
        if (! get_option('wat_flush_rewrite_rules')) {
            return;
        }
        delete_option('wat_flush_rewrite_rules');
        flush_rewrite_rules(false);
    }

    public static function schedule_rewrite_flush(): void
    {
        update_option('wat_flush_rewrite_rules', '1', false);
    }
}
