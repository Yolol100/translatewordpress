<?php

declare(strict_types=1);

namespace Webactueel\Translate\Rest\Concerns;

use Webactueel\Translate\Database\Tables;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

trait LanguageEndpointTransactions
{
    private function start_language_transaction(): bool
    {
        global $wpdb;

        return $wpdb->query('START TRANSACTION') !== false; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Transaction control statement contains no user input.
    }

    /** @return true|WP_Error */
    private function commit_language_transaction(bool $started)
    {
        global $wpdb;

        if (! $started) {
            return true;
        }

        if ($wpdb->query('COMMIT') !== false) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Transaction control statement contains no user input.
            return true;
        }

        $this->rollback_language_transaction(true);
        return new WP_Error(
            'wat_language_transaction_commit_failed',
            __('Taalwijziging bevestigen mislukt:', 'webactueel-translate-language-dropdowns') . ' ' .
                ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.',
            ['status' => 500]
        );
    }

    private function abort_language_transaction(bool $started, WP_Error $error): WP_Error
    {
        $this->rollback_language_transaction($started);
        return $error;
    }

    private function rollback_language_transaction(bool $started): void
    {
        global $wpdb;

        if ($started) {
            $wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Transaction control statement contains no user input.
        }
    }

    /**
     * Keep language-owned data attached when an existing language code changes.
     *
     * @return true|WP_Error
     */
    private function migrate_language_code_references(string $previousCode, string $newCode)
    {
        global $wpdb;

        $translationsTable = Tables::translations();
        $glossaryTable = Tables::glossary();
        $migrationQueries = [
            $wpdb->prepare(
                'UPDATE %i SET language_code = %s WHERE language_code = %s',
                $translationsTable,
                $newCode,
                $previousCode
            ),
            $wpdb->prepare(
                'UPDATE %i SET language_code = %s WHERE language_code = %s',
                $glossaryTable,
                $newCode,
                $previousCode
            ),
        ];

        foreach ($migrationQueries as $query) {
            if ($wpdb->query($query) === false) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with plugin-owned table names.
                return new WP_Error(
                    'wat_language_code_migration_failed',
                    __('Taalcode-referenties bijwerken mislukt:', 'webactueel-translate-language-dropdowns') . ' ' .
                        ($wpdb->last_error ?: __('onbekende databasefout', 'webactueel-translate-language-dropdowns')) . '.',
                    ['status' => 500]
                );
            }
        }

        return true;
    }
}
