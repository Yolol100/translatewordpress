# Final QA Report - Webactueel Translate 2.4.4

## Scope
Local static QA on `translatewordpress-main-v2.4.4-good.zip`.

## Confirmed checks
- PHP lint: OK, 102 PHP files, 0 syntax errors.
- JavaScript syntax: OK, 7 JS files, 0 syntax errors via `node --check`.
- ZIP integrity: OK.
- ZIP root structure: OK, single `translatewordpress-main/` plugin folder.
- Version metadata: OK, plugin header, `WAT_VERSION`, readme stable tag, block asset fallback and POT project version all use `2.4.4`.
- REST registration pattern: OK, routes use permission callbacks through explicit route helpers or route arrays.
- Release junk scan: OK, no `.log`, `.tmp`, `.bak`, `.map`, `.DS_Store`, `node_modules`, `composer.lock` or `package-lock.json` found.
- High-risk function scan: no `eval`, `shell_exec`, `exec`, `passthru`, `system` or `base64_decode` usage found.

## Reviewed notes
- One `unserialize()` use exists in `ScannerValueHelpers.php`; it is read-only scanner decoding with `allowed_classes => false` and an array-only return, so it is accepted as a guarded compatibility parser.
- Direct database queries are present because the plugin uses its own custom tables. The inspected patterns use prepared values or plugin-owned table identifiers.

## Remaining manual tests
These cannot be proven from local static checks alone:
- Activate/deactivate/uninstall on a real WordPress staging site.
- Run database upgrade/dbDelta path on staging.
- Test REST nonces and capabilities as administrator, editor, translator, subscriber and logged-out user.
- Run scan, AI batch, Translation Memory, glossary enforcement, CSV/XLIFF import/export and usage export.
- Test Visual Editor in real frontend pages.
- Test WooCommerce cart, checkout, account and order-pay safe-mode behavior.
- Test Elementor/page-builder pages.
- Test Yoast, Rank Math, AIOSEO and SEOPress SEO-meta sync where relevant.
- Run Plugin Check and PHPCS/WPCS in a WordPress-aware environment.

## Verdict
GO AFTER STAGING TESTS.

No safe code changes were required during this QA pass.
