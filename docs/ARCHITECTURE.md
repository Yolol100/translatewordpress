# Webactueel Translate architecture notes

This document is part of the release package so future maintainers can review the plugin without reverse-engineering the full codebase first.

## Bootstrap

- Main plugin file: `webactueel-translate.php`.
- Namespace root: `Webactueel\Translate\`.
- Autoload mapping: `app/`.
- Runtime entry point: `Webactueel\Translate\Plugin::boot()` on `plugins_loaded`.
- Activation/deactivation entry points are registered in the main plugin file.
- WooCommerce HPOS compatibility is declared before WooCommerce initialization when WooCommerce is present.

## Primary modules

- `app/Admin/` admin menu and post/term URL mapping UI.
- `app/Rest/` REST API routes for languages, settings, glossary, scanner, import/export, coverage, workflow and automation.
- `app/Frontend/` language detection, routing, switcher and output-buffer translation.
- `app/ImportExport/` CSV and XLIFF import/export boundaries.
- `app/Database/` plugin-owned custom tables and schema updates.
- `app/Translation/` repositories, map building, memory, glossary and coverage.
- `app/Automation/` AI job queue, provider calls and rate/usage controls.
- `app/VisualEditor/` in-context editing and related REST endpoints.
- `app/WooCommerce/` product/output compatibility and coverage diagnostics.
- `app/Seo/` hreflang, canonical, SEO metadata and sitemap support.
- `app/Support/` settings, sanitization, input helpers, logging, privacy and diagnostics.

## Runtime boundaries

- Frontend output translation must remain fail-open and must not run on admin, REST, AJAX, cron, feeds, XML-RPC, POST requests or protected WooCommerce flows when safe mode is enabled.
- REST/admin actions must keep capability checks close to the action.
- Imports, exports, AI requests, logs and database writes are trust boundaries and must stay covered by validation, sanitization and explicit error paths.
- AI translation must stay review-first and must not run on normal public page views.

## Versioning rule

When the release package changes, keep these aligned:

- `Version` header in `webactueel-translate.php`.
- `WAT_VERSION` constant.
- `Stable tag` in `readme.txt`.
- `Upgrade Notice` and `Changelog` entries.
- Block metadata or asset versions if runtime assets change.

## Change discipline

Prefer small, reversible patches. Do not rename public hooks, REST namespaces, shortcode tags, option keys, table names or capability names without an explicit migration and rollback plan.
