# Release checklist

Use this checklist on staging before production deployment.

## Static checks

- ZIP integrity passes.
- PHP lint passes for all PHP files.
- JavaScript syntax check passes for admin, frontend, visual editor and block scripts.
- JSON metadata validates.
- Plugin version, readme stable tag, package version, block metadata and asset fallback versions match.

## WordPress staging checks

- Install and activate the plugin on a clean staging site.
- Open the Webactueel Translate admin page and save settings.
- Add at least one target language.
- Run a full scan.
- Verify menu, widget, term, post, Elementor, ACF and WooCommerce strings where applicable.
- Verify frontend translation, language switcher, hreflang and sitemap output.
- Enable gettext discovery only on staging first and test dynamic theme/plugin strings.
- Enable runtime discovery only on staging first and confirm no checkout/form/cache regressions.
- Test CSV export/import and XLIFF export/import.
- Test CSV import rows with a valid plugin hash, empty hash and invalid hash to confirm safe fallback matching still works.
- Test AI translation only after provider keys and privacy terms are approved.
- Verify Google Translate with `WAT_GOOGLE_TRANSLATE_API_KEY` when used.
- Test conditional publish before enabling it on production.

## Safety checks

- Confirm REST actions require the intended capability.
- Test low-privilege translator access separately from administrator access.
- Confirm WooCommerce cart, checkout, account and order-pay pages are excluded or safe-mode protected.
- Confirm external AI/provider requests are disclosed in the privacy policy.
- Confirm rollback path: previous plugin ZIP, database backup and cache flush.
