<?php

declare(strict_types=1);

namespace Webactueel\Translate\Translation\Concerns;

use Webactueel\Translate\Cache\CacheInvalidator;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Translation\StringNormalizer;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.

trait TranslationStringWrites
{
    use ValidatesLanguages;
    public function upsert_string(string $text, string $sourceType = '', int $sourceId = 0, string $context = '', string $sourceKey = ''): int
    {
        global $wpdb;
        if (StringNormalizer::should_skip($text)) {
            return 0;
        }
        $normalized = StringNormalizer::normalize($text);
        $hash = StringNormalizer::hash($normalized, $context ?: $sourceType);
        $now = current_time('mysql');
        $table = Tables::strings();
        $sqlTable = esc_sql($table);
        $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$sqlTable}` WHERE hash = %s", $hash)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the plugin-owned strings table helper.
        if ($id) {
            $wpdb->update($table, ['last_seen_at' => $now, 'updated_at' => $now], ['id' => $id]);
            return $id;
        }
        $wpdb->insert($table, [
            'hash' => $hash,
            'original_text' => $text,
            'normalized_text' => $normalized,
            'context' => sanitize_text_field($context),
            'source_type' => sanitize_key($sourceType),
            'source_id' => absint($sourceId),
            'source_key' => sanitize_text_field($sourceKey),
            'status' => 'new',
            'created_at' => $now,
            'updated_at' => $now,
            'last_seen_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    public function save_translation(int $stringId, string $languageCode, string $translatedText, string $status = 'published', string $origin = 'manual'): bool
    {
        global $wpdb;
        $languageCode = sanitize_key($languageCode);
        if (! $this->is_translatable_language($languageCode)) {
            return false;
        }
        $status = sanitize_key($status);
        $translatedText = trim(wp_kses_post($translatedText));
        if ($translatedText !== '' && ! in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review'], true)) {
            $status = 'published';
        }
        if ($translatedText === '' && in_array($status, ['published', 'reviewed'], true)) {
            $status = 'draft';
        }
        if (! in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review'], true)) {
            $status = 'draft';
        }
        $origin = sanitize_key($origin ?: 'manual');
        $now = current_time('mysql');
        $table = Tables::translations();
        $sqlTable = esc_sql($table);
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$sqlTable}` WHERE string_id = %d AND language_code = %s", $stringId, $languageCode)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the plugin-owned translations table helper.
        $data = [
            'string_id' => absint($stringId),
            'language_code' => $languageCode,
            'translated_text' => $translatedText,
            'status' => $status,
            'origin' => $origin,
            'updated_at' => $now,
        ];
        $ok = false;
        if ($exists) {
            $ok = $wpdb->update($table, $data, ['id' => $exists]) !== false;
        } else {
            $data['created_at'] = $now;
            $ok = $wpdb->insert($table, $data) !== false;
        }
        if ($ok) {
            CacheInvalidator::bump();
        }
        return $ok;
    }
}
