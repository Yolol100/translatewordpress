# Database contract

The plugin intentionally uses custom, plugin-owned database tables for translation data and workflow state. Direct `$wpdb` usage is therefore expected in repository and schema classes, but it must remain disciplined.

## Owned tables

Table names are centralized in `app/Database/Tables.php` and use the current WordPress table prefix.

- `wat_languages`
- `wat_strings`
- `wat_translations`
- `wat_string_sources`
- `wat_scan_jobs`
- `wat_logs`
- `wat_glossary`
- `wat_ai_usage`

## Allowed direct database usage

Direct database queries are acceptable when all of the following are true:

1. The query targets plugin-owned tables or a documented WordPress core table.
2. Dynamic values use `$wpdb->prepare()` placeholders.
3. Dynamic table names come from `Tables::*()` or a fixed allow-list.
4. User-controlled filter/sort/page arguments are normalized through input helpers or allow-lists before query construction.
5. Writes update relevant cache/version state when frontend translation maps can be affected.
6. Errors return a safe admin/REST error and do not expose SQL or sensitive values.

## Review requirements for future changes

Before merging a new query, check:

- Capability and nonce are enforced before state-changing REST/admin actions.
- The query cannot be reached by logged-out or low-privilege users unless explicitly public and read-only.
- Search, order, status and language parameters are allow-listed.
- Bulk operations have a row limit or pagination.
- AI/provider text, API keys and personal data are not written to logs unless intentionally minimized and documented.
- Table schema changes update `Schema::DB_VERSION` and include an activation/update/staging test.

## PHPCS notes

Some direct query warnings are expected for plugin-owned tables. Suppressions must stay local and must explain the safe source of table names or fixed query parts. Do not add file-wide suppressions for new code unless the whole class is a repository/schema boundary.
