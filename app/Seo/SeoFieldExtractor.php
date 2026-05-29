<?php

declare(strict_types=1);

namespace Webactueel\Translate\Seo;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

/**
 * Detect the active SEO plugin and read its translatable meta fields for a post.
 *
 * This is the source-extraction half of the SEO auto-translate pipeline: it knows how
 * each supported SEO plugin (Yoast, Rank Math, AIOSEO, SEOPress) stores its custom title
 * and meta description, so those fields can be scanned into the translation queue and,
 * after translation, synced back into the `_wat_seo_translations` map that SeoMetaManager
 * renders on the front end.
 *
 * The set of fields is intentionally small (title + description) because those are the
 * SEO elements with the highest ranking impact and a clean 1:1 source-to-output mapping.
 */
final class SeoFieldExtractor
{
    /** Source type stored on scanned SEO strings. */
    public const SOURCE_TYPE = 'seo_meta';

    /** Logical fields handled by the pipeline. */
    public const FIELD_TITLE = 'title';
    public const FIELD_DESCRIPTION = 'description';

    /**
     * Detect which SEO plugin is active.
     *
     * @return string One of 'yoast', 'rankmath', 'aioseo', 'seopress', or '' when none.
     */
    public static function active_plugin(): string
    {
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Frontend')) {
            return 'yoast';
        }
        if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) {
            return 'rankmath';
        }
        if (defined('AIOSEO_VERSION') || function_exists('aioseo')) {
            return 'aioseo';
        }
        if (defined('SEOPRESS_VERSION') || defined('SEOPRESS_PLUGIN_FILE')) {
            return 'seopress';
        }

        return '';
    }

    /**
     * Whether SEO field translation is available for the current install.
     */
    public static function is_available(): bool
    {
        return (bool) apply_filters('wat_seo_fields_enabled', self::active_plugin() !== '');
    }

    /**
     * Source meta keys for the active plugin, keyed by logical field.
     *
     * AIOSEO stores SEO data in its own custom table rather than post meta, so it is
     * resolved separately via {@see self::read_field()} and returns an empty map here.
     *
     * @return array<string, string> Map of logical field => post meta key.
     */
    public static function meta_keys(string $plugin = ''): array
    {
        $plugin = $plugin !== '' ? $plugin : self::active_plugin();
        switch ($plugin) {
            case 'yoast':
                return [
                    self::FIELD_TITLE => '_yoast_wpseo_title',
                    self::FIELD_DESCRIPTION => '_yoast_wpseo_metadesc',
                ];
            case 'rankmath':
                return [
                    self::FIELD_TITLE => 'rank_math_title',
                    self::FIELD_DESCRIPTION => 'rank_math_description',
                ];
            case 'seopress':
                return [
                    self::FIELD_TITLE => '_seopress_titles_title',
                    self::FIELD_DESCRIPTION => '_seopress_titles_desc',
                ];
            default:
                return [];
        }
    }

    /**
     * Read a single SEO source field value for a post in the site's default language.
     *
     * Returns the raw stored value with SEO template variables (e.g. %%title%%, {{title}})
     * left untouched but skipped by the caller, so only human-authored custom values are
     * sent for translation.
     */
    public static function read_field(int $postId, string $field, string $plugin = ''): string
    {
        $postId = absint($postId);
        $field = sanitize_key($field);
        if ($postId <= 0 || ! in_array($field, [self::FIELD_TITLE, self::FIELD_DESCRIPTION], true)) {
            return '';
        }

        $plugin = $plugin !== '' ? $plugin : self::active_plugin();

        if ($plugin === 'aioseo') {
            $value = self::read_aioseo_field($postId, $field);
        } else {
            $keys = self::meta_keys($plugin);
            if (empty($keys[$field])) {
                return '';
            }
            $raw = get_post_meta($postId, $keys[$field], true);
            $value = is_scalar($raw) ? (string) $raw : '';
        }

        return self::is_translatable_value($value) ? $value : '';
    }

    /**
     * Read all translatable SEO source fields for a post.
     *
     * @return array<string, string> Map of logical field => non-empty source value.
     */
    public static function read_fields(int $postId, string $plugin = ''): array
    {
        $plugin = $plugin !== '' ? $plugin : self::active_plugin();
        if ($plugin === '') {
            return [];
        }

        $fields = [];
        foreach ([self::FIELD_TITLE, self::FIELD_DESCRIPTION] as $field) {
            $value = self::read_field($postId, $field, $plugin);
            if ($value !== '') {
                $fields[$field] = $value;
            }
        }

        return $fields;
    }

    /**
     * Stable source_key for a scanned SEO string so it can be mapped back to a field.
     */
    public static function source_key(string $field): string
    {
        return 'seo:' . sanitize_key($field);
    }

    /**
     * Scan context label for a scanned SEO string.
     */
    public static function context(string $field): string
    {
        return 'seo_' . sanitize_key($field);
    }

    /**
     * Resolve the logical SEO field from a string row's context/source_key.
     *
     * @param array<string, mixed> $stringRow A row from the strings table.
     */
    public static function field_from_string(array $stringRow): string
    {
        $sourceKey = isset($stringRow['source_key']) ? (string) $stringRow['source_key'] : '';
        if (strpos($sourceKey, 'seo:') === 0) {
            $field = sanitize_key(substr($sourceKey, 4));
            if (in_array($field, [self::FIELD_TITLE, self::FIELD_DESCRIPTION], true)) {
                return $field;
            }
        }

        $context = isset($stringRow['context']) ? (string) $stringRow['context'] : '';
        if (strpos($context, 'seo_') === 0) {
            $field = sanitize_key(substr($context, 4));
            if (in_array($field, [self::FIELD_TITLE, self::FIELD_DESCRIPTION], true)) {
                return $field;
            }
        }

        return '';
    }

    /**
     * Read a field from the AIOSEO custom table.
     */
    private static function read_aioseo_field(int $postId, string $field): string
    {
        global $wpdb;
        $column = $field === self::FIELD_TITLE ? 'title' : 'description';
        $table = $wpdb->prefix . 'aioseo_posts';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column is from a fixed allow-list; table name is the AIOSEO-owned posts table.
        $value = $wpdb->get_var($wpdb->prepare("SELECT `{$column}` FROM `" . esc_sql($table) . "` WHERE post_id = %d LIMIT 1", $postId));

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Reject empty values and values that are only SEO template tags/variables.
     *
     * Template-only values (e.g. "%%title%% %%sep%% %%sitename%%") are dynamic and must
     * not be translated as literal strings.
     */
    private static function is_translatable_value(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        // Strip common SEO template tokens; if nothing human-readable remains, skip it.
        $withoutTokens = preg_replace('/%%[^%]+%%|\{\{[^}]+\}\}|#[a-z_]+#/i', '', $trimmed);
        $withoutTokens = is_string($withoutTokens) ? trim($withoutTokens) : '';

        return $withoutTokens !== '' && preg_match('/\p{L}/u', $withoutTokens) === 1;
    }
}
