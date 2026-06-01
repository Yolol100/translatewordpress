<?php

declare(strict_types=1);

namespace Webactueel\Translate\Seo;

use Webactueel\Translate\Database\Tables;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

/**
 * Sync translated SEO strings into the post-meta map that SeoMetaManager renders.
 *
 * Source extraction (SeoFieldExtractor) feeds SEO titles/descriptions into the normal
 * translation queue. This listener closes the loop: whenever a translation is saved for
 * a string that originated from an SEO field, it writes the translated value into the
 * post's `_wat_seo_translations[language][field]` map. Without this, the SeoMetaManager
 * read layer has nothing to render.
 */
final class SeoTranslationSync
{
    public const META_KEY = '_wat_seo_translations';

    public function register(): void
    {
        // save_translation() fires this for every writer (manual, visual editor, AI batch,
        // CSV, XLIFF), so SEO translations stay in sync regardless of how they were created.
        add_action('wat_after_translation_saved', [$this, 'sync'], 10, 2);
    }

    /**
     * Sync one saved translation into the SEO meta map when it belongs to an SEO field.
     */
    public function sync(int $stringId, string $language): void
    {
        $stringId = absint($stringId);
        $language = sanitize_key($language);
        if ($stringId <= 0 || $language === '') {
            return;
        }

        $string = $this->string_row($stringId);
        if ($string === null) {
            return;
        }
        if ((string) ($string['source_type'] ?? '') !== SeoFieldExtractor::SOURCE_TYPE) {
            return;
        }

        $postId = absint($string['source_id'] ?? 0);
        $field = SeoFieldExtractor::field_from_string($string);
        if ($postId <= 0 || $field === '') {
            return;
        }

        $translation = $this->translation_value($stringId, $language);

        $map = get_post_meta($postId, self::META_KEY, true);
        if (! is_array($map)) {
            $map = [];
        }
        if (! isset($map[$language]) || ! is_array($map[$language])) {
            $map[$language] = [];
        }

        if ($translation === '') {
            // Empty/cleared translation: drop the field so the front end falls back to source.
            unset($map[$language][$field]);
            if ($map[$language] === []) {
                unset($map[$language]);
            }
        } else {
            $map[$language][$field] = $translation;
        }

        if ($map === []) {
            delete_post_meta($postId, self::META_KEY);
        } else {
            update_post_meta($postId, self::META_KEY, $map);
        }

        do_action('wat_seo_translation_synced', $postId, $language, $field, $translation);
    }

    /**
     * Fetch the minimal string row needed to resolve the SEO field and post.
     *
     * @return array<string, mixed>|null
     */
    private function string_row(int $stringId): ?array
    {
        global $wpdb;
        $table = Tables::strings();
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT source_type, source_id, context, source_key FROM %i WHERE id = %d LIMIT 1',
            $table,
            $stringId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * Read the current saved translation text for a string + language.
     *
     * Only published/reviewed translations are surfaced as live SEO output; drafts and
     * review-pending values are treated as empty so unverified text never reaches meta tags.
     */
    private function translation_value(int $stringId, string $language): string
    {
        global $wpdb;
        $table = Tables::translations();
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT translated_text, status FROM %i WHERE string_id = %d AND language_code = %s LIMIT 1',
            $table,
            $stringId,
            $language
        ), ARRAY_A);

        if (! is_array($row)) {
            return '';
        }

        $status = sanitize_key((string) ($row['status'] ?? ''));
        $publishable = (array) apply_filters('wat_seo_publishable_statuses', ['published', 'reviewed']);
        if (! in_array($status, $publishable, true)) {
            return '';
        }

        $text = trim(wp_strip_all_tags((string) ($row['translated_text'] ?? '')));

        return sanitize_text_field($text);
    }
}
