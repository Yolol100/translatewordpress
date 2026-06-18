<?php

declare(strict_types=1);

namespace Webactueel\Translate\Translation;

use Webactueel\Translate\Database\Tables;
use Webactueel\Translate\Support\Input;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only dashboard/reporting queries against plugin-owned tables.

final class TranslationCoverageReporter
{
    /**
     * @var array<string, bool>
     */
    private static array $translationPresenceCache = [];
    private static array $fullyPublishedCache = [];

    /** @return array<string, mixed> */
    public static function summary(): array
    {
        $languages = self::language_coverage();
        $nonDefault = array_values(array_filter($languages, static fn(array $row): bool => empty($row['is_default'])));
        $average = 100.0;
        if ($nonDefault !== []) {
            $average = round(array_sum(array_map(static fn(array $row): float => (float) $row['coverage_percent'], $nonDefault)) / count($nonDefault), 1);
        }

        return [
            'average_percent' => $average,
            'languages' => $languages,
            'has_untranslated_languages' => self::has_missing_translations($nonDefault),
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private static function has_missing_translations(array $rows): bool
    {
        foreach ($rows as $row) {
            if ((int) ($row['missing'] ?? 0) > 0) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int, array<string, mixed>> */
    public static function language_coverage(): array
    {
        global $wpdb;

        $languagesTable = Tables::languages();
        $stringsTable = Tables::strings();
        $translationsTable = Tables::translations();
        $languages = $wpdb->get_results(
            $wpdb->prepare('SELECT code, name, native_name, is_default, is_active FROM %i WHERE is_active = 1 ORDER BY is_default DESC, code ASC', $languagesTable),
            ARRAY_A
        ) ?: [];
        $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $stringsTable));

        $coverage = [];
        foreach ($languages as $language) {
            $code = Input::key($language['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $isDefault = ! empty($language['is_default']);
            $published = $isDefault ? $total : self::published_count($code, $stringsTable, $translationsTable);
            $needsReview = $isDefault ? 0 : self::status_count($code, 'needs_review', $translationsTable);
            $draft = $isDefault ? 0 : self::status_count($code, 'draft', $translationsTable);
            $missing = $isDefault ? 0 : max(0, $total - $published);
            $percent = $total > 0 ? round(($published / $total) * 100, 1) : 100.0;

            $coverage[] = [
                'code' => $code,
                'name' => Input::scalar_string($language['name'] ?? strtoupper($code), strtoupper($code)),
                'native_name' => Input::scalar_string($language['native_name'] ?? strtoupper($code), strtoupper($code)),
                'is_default' => $isDefault,
                'total' => $total,
                'published' => $published,
                'missing' => $missing,
                'needs_review' => $needsReview,
                'draft' => $draft,
                'coverage_percent' => $percent,
                'has_published_translations' => $isDefault || $published > 0,
            ];
        }

        return $coverage;
    }

    public static function language_is_fully_published(string $languageCode): bool
    {
        $languageCode = Input::key($languageCode);
        if ($languageCode === '') {
            return false;
        }
        if (array_key_exists($languageCode, self::$fullyPublishedCache)) {
            return self::$fullyPublishedCache[$languageCode];
        }

        global $wpdb;
        $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', Tables::strings()));
        if ($total < 1) {
            self::$fullyPublishedCache[$languageCode] = true;
            return true;
        }

        $published = self::published_count($languageCode, Tables::strings(), Tables::translations());
        self::$fullyPublishedCache[$languageCode] = $published >= $total;
        return self::$fullyPublishedCache[$languageCode];
    }

    public static function language_has_published_translations(string $languageCode): bool
    {
        $languageCode = Input::key($languageCode);
        if ($languageCode === '') {
            return false;
        }
        if (array_key_exists($languageCode, self::$translationPresenceCache)) {
            return self::$translationPresenceCache[$languageCode];
        }

        global $wpdb;
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE language_code = %s AND status IN ("published", "reviewed") AND translated_text <> "" LIMIT 1',
                Tables::translations(),
                $languageCode
            )
        );
        self::$translationPresenceCache[$languageCode] = $count > 0;
        return self::$translationPresenceCache[$languageCode];
    }

    private static function published_count(string $languageCode, string $stringsTable, string $translationsTable): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(DISTINCT s.id) FROM %i s INNER JOIN %i t ON t.string_id = s.id AND t.language_code = %s AND t.status IN ("published", "reviewed") AND t.translated_text <> ""',
                $stringsTable,
                $translationsTable,
                $languageCode
            )
        );
    }

    private static function status_count(string $languageCode, string $status, string $translationsTable): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE language_code = %s AND status = %s',
                $translationsTable,
                $languageCode,
                $status
            )
        );
    }
}
