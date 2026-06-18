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
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

final class TranslationMapBuilder
{
    use ValidatesLanguages;

    public function translate_text(string $text, string $languageCode): string
    {
        $languageCode = Input::key($languageCode);
        if ($text === '' || $languageCode === '') {
            return $text;
        }

        $normalized = StringNormalizer::normalize($text);
        if ($normalized === '') {
            return $text;
        }

        $map = $this->translation_map($languageCode);
        return isset($map[$normalized]) && is_string($map[$normalized]) && $map[$normalized] !== '' ? $map[$normalized] : $text;
    }

    public function translation_map(string $languageCode): array
    {
        $languageCode = sanitize_key($languageCode);
        if (! $this->is_translatable_language($languageCode)) {
            return [];
        }

        $cached = TranslationCache::get($languageCode);
        if (is_array($cached)) {
            return $cached;
        }

        $map = $this->build_from_rows(
            $this->translation_map_rows($languageCode),
            (new GlossaryRepository())->all($languageCode)
        );
        $filtered = apply_filters('wat_translation_map', $map, $languageCode);
        if (is_array($filtered)) {
            $map = $this->sanitize_translation_map($filtered);
        }

        TranslationCache::set($languageCode, $map);
        return $map;
    }

    /**
     * Keep public translation-map filters from breaking strict frontend string replacement.
     *
     * @param array<mixed, mixed> $map
     * @return array<string, string>
     */
    private function sanitize_translation_map(array $map): array
    {
        $clean = [];
        foreach ($map as $normalized => $translated) {
            if (! is_scalar($normalized) || ! is_scalar($translated)) {
                continue;
            }

            $normalized = Input::scalar_string($normalized);
            $translated = Input::scalar_string($translated);
            if ($normalized !== '' && $translated !== '') {
                $clean[$normalized] = $translated;
            }
        }

        return $clean;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function translation_map_rows(string $languageCode): array
    {
        global $wpdb;

        $limit = (int) apply_filters('wat_translation_map_limit', 10000, $languageCode);
        $limit = max(100, min(50000, $limit));
        $sql = "SELECT s.normalized_text, t.translated_text
            FROM %i s
            INNER JOIN (
                SELECT string_id, MAX(id) AS latest_id
                FROM %i
                WHERE language_code = %s
                AND status IN ('published', 'reviewed')
                AND translated_text <> ''
                GROUP BY string_id
            ) latest ON latest.string_id = s.id
            INNER JOIN %i t ON t.id = latest.latest_id
            ORDER BY s.id ASC
            LIMIT %d";

        return $wpdb->get_results($wpdb->prepare($sql, Tables::strings(), Tables::translations(), $languageCode, Tables::translations(), $limit), ARRAY_A) ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $glossaryTerms
     * @return array<string, string>
     */
    private function build_from_rows(array $rows, array $glossaryTerms): array
    {
        $map = [];
        $ambiguous = [];
        $glossaryApplier = new GlossaryApplier();

        foreach ($rows as $row) {
            $normalized = Input::scalar_string($row['normalized_text'] ?? '');
            $translated = Input::scalar_string($row['translated_text'] ?? '');
            if ($normalized === '' || $translated === '' || isset($ambiguous[$normalized])) {
                continue;
            }

            $translated = $glossaryApplier->apply($translated, $glossaryTerms);
            if (! isset($map[$normalized])) {
                $map[$normalized] = $translated;
                continue;
            }

            if ($map[$normalized] !== $translated) {
                unset($map[$normalized]);
                $ambiguous[$normalized] = true;
            }
        }

        return $map;
    }
}
