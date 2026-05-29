<?php

declare(strict_types=1);

namespace Webactueel\Translate\Workflow;

use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Concerns\ValidatesLanguages;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables are plugin-owned.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic SQL only uses plugin-owned table identifiers and allow-listed clauses.

final class TranslationQualityReport
{
    use ValidatesLanguages;

    /** @return array<string, mixed> */
    public static function for_language(string $languageCode): array
    {
        $languageCode = Input::key($languageCode);
        $validator = new self();
        if (! $validator->is_translatable_language($languageCode)) {
            return [
                'language' => $languageCode,
                'valid' => false,
                'message' => __('Kies een actieve niet-standaardtaal om de vertaalkwaliteit te controleren.', 'webactueel-translate-language-dropdowns'),
                'counts' => [],
                'score' => 0,
                'warnings' => [],
            ];
        }

        $counts = self::counts($languageCode);
        $total = max(0, (int) ($counts['total_strings'] ?? 0));
        $published = max(0, (int) ($counts['published'] ?? 0));
        $reviewed = max(0, (int) ($counts['reviewed'] ?? 0));
        $score = $total > 0 ? (int) round((($published + $reviewed) / $total) * 100) : 0;

        return [
            'language' => $languageCode,
            'valid' => true,
            'message' => self::message($counts, $score),
            'counts' => $counts,
            'score' => $score,
            'warnings' => self::warnings($counts),
        ];
    }

    /** @return array<string, int> */
    private static function counts(string $languageCode): array
    {
        global $wpdb;
        $stringsTable = Tables::sql_identifier(Tables::strings());
        $translationsTable = Tables::sql_identifier(Tables::translations());

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(DISTINCT s.id) AS total_strings,
                    SUM(CASE WHEN t.id IS NULL OR TRIM(COALESCE(t.translated_text, '')) = '' THEN 1 ELSE 0 END) AS missing,
                    SUM(CASE WHEN t.status = 'draft' THEN 1 ELSE 0 END) AS draft,
                    SUM(CASE WHEN t.status = 'needs_review' THEN 1 ELSE 0 END) AS needs_review,
                    SUM(CASE WHEN t.status = 'outdated' THEN 1 ELSE 0 END) AS outdated,
                    SUM(CASE WHEN t.status = 'reviewed' THEN 1 ELSE 0 END) AS reviewed,
                    SUM(CASE WHEN t.status = 'published' THEN 1 ELSE 0 END) AS published,
                    SUM(CASE WHEN t.origin = 'ai' THEN 1 ELSE 0 END) AS ai_origin,
                    SUM(CASE WHEN t.id IS NOT NULL AND TRIM(COALESCE(t.translated_text, '')) = TRIM(COALESCE(s.original_text, '')) THEN 1 ELSE 0 END) AS identical_to_source,
                    SUM(CASE WHEN t.id IS NOT NULL AND t.translated_text LIKE '%<%' AND t.translated_text NOT LIKE '%>%' THEN 1 ELSE 0 END) AS possible_broken_markup
                FROM `{$stringsTable}` s
                LEFT JOIN `{$translationsTable}` t ON t.string_id = s.id AND t.language_code = %s",
                $languageCode
            ),
            ARRAY_A
        );

        $counts = [];
        foreach ((array) $row as $key => $value) {
            $counts[(string) $key] = max(0, (int) $value);
        }

        return array_merge([
            'total_strings' => 0,
            'missing' => 0,
            'draft' => 0,
            'needs_review' => 0,
            'outdated' => 0,
            'reviewed' => 0,
            'published' => 0,
            'ai_origin' => 0,
            'identical_to_source' => 0,
            'possible_broken_markup' => 0,
        ], $counts);
    }

    /** @param array<string, int> $counts */
    private static function message(array $counts, int $score): string
    {
        if (($counts['total_strings'] ?? 0) <= 0) {
            return __('Er zijn nog geen strings gevonden. Start eerst een scan.', 'webactueel-translate-language-dropdowns');
        }

        if ($score >= 95 && ($counts['needs_review'] ?? 0) === 0 && ($counts['outdated'] ?? 0) === 0 && ($counts['missing'] ?? 0) === 0) {
            return __('Deze taal is vrijwel publicatieklaar.', 'webactueel-translate-language-dropdowns');
        }

        return __('Deze taal heeft nog vertaal- of reviewwerk nodig voordat publicatie verstandig is.', 'webactueel-translate-language-dropdowns');
    }

    /** @param array<string, int> $counts @return list<array<string, mixed>> */
    private static function warnings(array $counts): array
    {
        $warnings = [];
        foreach ([
            'missing' => __('Er ontbreken nog vertalingen.', 'webactueel-translate-language-dropdowns'),
            'needs_review' => __('Er staan vertalingen klaar voor review.', 'webactueel-translate-language-dropdowns'),
            'outdated' => __('Er zijn vertalingen verouderd door gewijzigde content.', 'webactueel-translate-language-dropdowns'),
            'identical_to_source' => __('Sommige vertalingen zijn gelijk aan de brontekst; controleer of dat bewust is.', 'webactueel-translate-language-dropdowns'),
            'possible_broken_markup' => __('Mogelijk bevat een vertaling gebroken HTML-markup.', 'webactueel-translate-language-dropdowns'),
        ] as $key => $message) {
            $count = max(0, (int) ($counts[$key] ?? 0));
            if ($count > 0) {
                $warnings[] = ['code' => $key, 'count' => $count, 'message' => $message];
            }
        }

        return $warnings;
    }
}
