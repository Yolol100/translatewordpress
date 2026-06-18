<?php

declare(strict_types=1);

namespace Webactueel\Translate\Translation;

if (! defined('ABSPATH')) {
    exit;
}

final class TranslationRepository
{
    private ?StringRepository $strings = null;
    private ?TranslationMemoryService $memory = null;
    private ?TranslationMapBuilder $mapBuilder = null;

    public function upsert_string(string $text, string $sourceType = '', int $sourceId = 0, string $context = '', string $sourceKey = ''): int
    {
        return $this->strings()->upsert($text, $sourceType, $sourceId, $context, $sourceKey);
    }

    public function save_translation(int $stringId, string $languageCode, string $translatedText, string $status = 'published', string $origin = 'manual'): bool
    {
        return $this->strings()->save_translation($stringId, $languageCode, $translatedText, $status, $origin);
    }

    public function get_strings(array $args = []): array
    {
        return $this->strings()->get_strings($args);
    }

    public function translate_text(string $text, string $languageCode): string
    {
        return $this->map_builder()->translate_text($text, $languageCode);
    }

    public function get_translations_for_string(int $stringId): array
    {
        return $this->strings()->get_translations_for_string($stringId);
    }

    public function apply_translation_memory(int $sourceStringId, string $languageCode, string $translatedText, string $status = 'reviewed'): int
    {
        return $this->memory()->apply($sourceStringId, $languageCode, $translatedText, $status);
    }

    /** @return array<string, mixed> */
    public function find_translation_memory_match(string $originalText, string $languageCode): array
    {
        return $this->memory()->find_match($originalText, $languageCode);
    }

    public function translation_map(string $languageCode): array
    {
        return $this->map_builder()->translation_map($languageCode);
    }

    private function strings(): StringRepository
    {
        if ($this->strings === null) {
            $this->strings = new StringRepository();
        }

        return $this->strings;
    }

    private function memory(): TranslationMemoryService
    {
        if ($this->memory === null) {
            $this->memory = new TranslationMemoryService();
        }

        return $this->memory;
    }

    private function map_builder(): TranslationMapBuilder
    {
        if ($this->mapBuilder === null) {
            $this->mapBuilder = new TranslationMapBuilder();
        }

        return $this->mapBuilder;
    }
}
