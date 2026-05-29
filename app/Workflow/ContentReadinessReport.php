<?php

declare(strict_types=1);

namespace Webactueel\Translate\Workflow;

use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom reporting query over plugin-owned translation tables.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic SQL only uses plugin-owned table identifiers and allow-listed clauses.

final class ContentReadinessReport
{
    use ValidatesLanguages;

    private const MAX_LIMIT = 20;

    /** @return array<string, mixed> */
    public static function for_language(string $languageCode, int $limit = 8): array
    {
        $languageCode = Input::key($languageCode);
        $validator = new self();
        if (! $validator->is_translatable_language($languageCode)) {
            return [
                'language' => $languageCode,
                'valid' => false,
                'message' => __('Kies een actieve niet-standaardtaal om contenttaken te bekijken.', 'webactueel-translate-language-dropdowns'),
                'summary' => ['items' => 0, 'attention_needed' => 0, 'ready' => 0],
                'items' => [],
            ];
        }

        $limit = max(1, min(self::MAX_LIMIT, absint($limit ?: 8)));
        $items = self::items($languageCode, $limit);
        $attention = array_sum(array_map(static fn (array $item): int => $item['attention_needed'] > 0 ? 1 : 0, $items));

        return [
            'language' => $languageCode,
            'valid' => true,
            'message' => $items
                ? __('Belangrijkste pagina’s en contentgroepen met vertaalwerk.', 'webactueel-translate-language-dropdowns')
                : __('Er zijn nog geen contentgroepen gevonden voor deze taal.', 'webactueel-translate-language-dropdowns'),
            'summary' => [
                'items' => count($items),
                'attention_needed' => $attention,
                'ready' => max(0, count($items) - $attention),
            ],
            'items' => $items,
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function items(string $languageCode, int $limit): array
    {
        global $wpdb;
        $stringsTable = Tables::sql_identifier(Tables::strings());
        $translationsTable = Tables::sql_identifier(Tables::translations());
        $sourcesTable = Tables::sql_identifier(Tables::sources());

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    CASE
                        WHEN MAX(src.post_id) > 0 THEN CONCAT('post:', MAX(src.post_id))
                        WHEN MAX(NULLIF(src.url, '')) IS NOT NULL THEN CONCAT('url:', MD5(MAX(src.url)))
                        ELSE CONCAT('source:', COALESCE(NULLIF(s.source_type, ''), 'unknown'), ':', COALESCE(s.source_id, 0), ':', COALESCE(NULLIF(s.source_key, ''), 'main'))
                    END AS group_key,
                    MAX(src.post_id) AS post_id,
                    MAX(src.post_type) AS post_type,
                    MAX(src.url) AS url,
                    COALESCE(NULLIF(s.source_type, ''), 'content') AS source_type,
                    COALESCE(NULLIF(s.source_key, ''), '') AS source_key,
                    MIN(LEFT(s.original_text, 160)) AS sample_text,
                    COUNT(DISTINCT s.id) AS total_strings,
                    SUM(CASE WHEN t.id IS NULL OR TRIM(COALESCE(t.translated_text, '')) = '' THEN 1 ELSE 0 END) AS missing,
                    SUM(CASE WHEN t.status = 'needs_review' THEN 1 ELSE 0 END) AS needs_review,
                    SUM(CASE WHEN t.status = 'outdated' THEN 1 ELSE 0 END) AS outdated,
                    SUM(CASE WHEN t.status IN ('published', 'reviewed') AND TRIM(COALESCE(t.translated_text, '')) <> '' THEN 1 ELSE 0 END) AS ready,
                    MAX(s.last_seen_at) AS last_seen_at
                FROM `{$stringsTable}` s
                LEFT JOIN `{$sourcesTable}` src ON src.string_id = s.id
                LEFT JOIN `{$translationsTable}` t ON t.string_id = s.id AND t.language_code = %s
                GROUP BY group_key, source_type, source_key
                HAVING total_strings > 0
                ORDER BY (missing + needs_review + outdated) DESC, total_strings DESC, last_seen_at DESC
                LIMIT %d",
                $languageCode,
                $limit
            ),
            ARRAY_A
        ) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $items[] = self::normalize_row($row);
        }

        return $items;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function normalize_row(array $row): array
    {
        $postId = absint($row['post_id'] ?? 0);
        $postTitle = '';
        $editUrl = '';
        $viewUrl = '';
        if ($postId > 0) {
            $postTitle = get_the_title($postId);
            $editUrl = get_edit_post_link($postId, 'raw') ?: '';
            $viewUrl = get_permalink($postId) ?: '';
        }

        $sourceType = sanitize_text_field(Input::scalar_string($row['source_type'] ?? 'content'));
        $sourceKey = sanitize_text_field(Input::scalar_string($row['source_key'] ?? ''));
        $url = esc_url_raw(Input::scalar_string($row['url'] ?? ''));
        $title = $postTitle ?: self::fallback_title($sourceType, $sourceKey, $url);
        $missing = absint($row['missing'] ?? 0);
        $needsReview = absint($row['needs_review'] ?? 0);
        $outdated = absint($row['outdated'] ?? 0);
        $total = max(0, absint($row['total_strings'] ?? 0));
        $ready = absint($row['ready'] ?? 0);
        $attention = $missing + $needsReview + $outdated;
        $score = $total > 0 ? (int) round(($ready / $total) * 100) : 0;

        return [
            'key' => sanitize_key(Input::scalar_string($row['group_key'] ?? 'content')),
            'title' => sanitize_text_field($title),
            'source_type' => $sourceType,
            'source_key' => $sourceKey,
            'post_id' => $postId,
            'post_type' => sanitize_key(Input::scalar_string($row['post_type'] ?? '')),
            'url' => $url,
            'edit_url' => esc_url_raw($editUrl),
            'view_url' => esc_url_raw($viewUrl ?: $url),
            'sample_text' => sanitize_text_field(Input::scalar_string($row['sample_text'] ?? '')),
            'total_strings' => $total,
            'ready' => $ready,
            'missing' => $missing,
            'needs_review' => $needsReview,
            'outdated' => $outdated,
            'attention_needed' => $attention,
            'score' => max(0, min(100, $score)),
            'last_seen_at' => sanitize_text_field(Input::scalar_string($row['last_seen_at'] ?? '')),
        ];
    }

    private static function fallback_title(string $sourceType, string $sourceKey, string $url): string
    {
        if ($url !== '') {
            $path = wp_parse_url($url, PHP_URL_PATH);
            return $path ? __('URL', 'webactueel-translate-language-dropdowns') . ': ' . $path : __('Websitepagina', 'webactueel-translate-language-dropdowns');
        }

        if ($sourceType !== '' && $sourceType !== 'content') {
            return ucwords(str_replace(['_', '-'], ' ', $sourceType)) . ($sourceKey !== '' ? ' · ' . $sourceKey : '');
        }

        return __('Contentgroep', 'webactueel-translate-language-dropdowns');
    }
}
