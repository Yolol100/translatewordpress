<?php

declare(strict_types=1);

namespace Webactueel\Translate\Translation;

use Webactueel\Translate\Cache\TranslationCache;
use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned custom tables; table identifiers are prepared with %i placeholders.

final class TranslationMemoryService
{
    use ValidatesLanguages;

    public function apply(int $sourceStringId, string $languageCode, string $translatedText, string $status = 'reviewed'): int
    {
        global $wpdb;

        $languageCode = sanitize_key($languageCode);
        if (! $this->is_translatable_language($languageCode)) {
            return 0;
        }

        $translatedText = trim(wp_kses_post($translatedText));
        if ($sourceStringId <= 0 || $languageCode === '' || $translatedText === '') {
            return 0;
        }

        $status = in_array($status, ['draft', 'reviewed', 'published', 'ignored', 'needs_review', 'outdated'], true) ? $status : 'reviewed';
        $memoryStatus = in_array($status, ['published', 'reviewed'], true) ? 'reviewed' : $status;
        $stringsTable = Tables::strings();
        $translationsTable = Tables::translations();
        $source = $wpdb->get_row($wpdb->prepare('SELECT normalized_text FROM %i WHERE id = %d LIMIT 1', $stringsTable, absint($sourceStringId)), ARRAY_A);
        $normalized = Input::scalar_string($source['normalized_text'] ?? '');
        if ($normalized === '') {
            return 0;
        }

        $now = current_time('mysql');
        $sql = "INSERT INTO %i (string_id, language_code, translated_text, status, origin, created_at, updated_at)
            SELECT s.id, %s, %s, %s, 'memory', %s, %s
            FROM %i s
            LEFT JOIN %i existing ON existing.string_id = s.id AND existing.language_code = %s
            WHERE s.normalized_text = %s AND s.id <> %d AND existing.id IS NULL";
        $result = $wpdb->query($wpdb->prepare($sql, $translationsTable, $languageCode, $translatedText, $memoryStatus, $now, $now, $stringsTable, $translationsTable, $languageCode, $normalized, absint($sourceStringId)));

        if ($result && $result > 0) {
            TranslationCache::bump();
            return (int) $result;
        }

        return 0;
    }

    /** @return array<string, mixed> */
    public function find_match(string $originalText, string $languageCode): array
    {
        global $wpdb;

        $languageCode = sanitize_key($languageCode);
        if (! $this->is_translatable_language($languageCode)) {
            return [];
        }

        $normalized = StringNormalizer::normalize($originalText);
        if ($normalized === '') {
            return [];
        }

        $stringsTable = Tables::strings();
        $translationsTable = Tables::translations();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT t.translated_text, t.status, t.origin, s.id AS source_string_id FROM %i s INNER JOIN %i t ON t.string_id = s.id WHERE s.normalized_text = %s AND t.language_code = %s AND t.status IN ('reviewed','published') AND TRIM(t.translated_text) <> '' ORDER BY t.updated_at DESC, t.id DESC LIMIT 1",
            $stringsTable,
            $translationsTable,
            $normalized,
            $languageCode
        ), ARRAY_A);

        if (! is_array($row)) {
            return [];
        }

        return [
            'translated_text' => Input::scalar_string($row['translated_text'] ?? ''),
            'status' => Input::key($row['status'] ?? 'reviewed'),
            'origin' => Input::key($row['origin'] ?? 'memory'),
            'source_string_id' => absint($row['source_string_id'] ?? 0),
            'score' => 100,
        ];
    }
}
