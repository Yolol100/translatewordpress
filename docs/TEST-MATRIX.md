# Staging test matrix

Use this matrix before a client or production release.

## Core

- Clean activation, deactivation and reactivation.
- Existing-site upgrade from previous stable ZIP.
- Database version update path.
- Uninstall with data retention.
- Uninstall with explicit opt-in data deletion.
- Rollback to previous ZIP, followed by cache clear and permalink flush when routing changed.

## Roles and REST

Test logged-out, subscriber, editor, translator role and administrator.

- Settings read/write.
- Language create/update/delete.
- Glossary create/update/delete.
- Scan start/progress.
- CSV/XLIFF export.
- CSV/XLIFF import/preview.
- Visual editor segment read/write.
- AI suggestion/job routes.
- Logs and diagnostics.

Expected result: only the intended role/capability can perform the action; invalid nonce and missing capability must fail safely.

## Import/export

- Valid CSV export and import.
- Valid XLIFF export and import.
- Empty files.
- Wrong extension.
- Wrong MIME type.
- PHP file renamed to CSV/XLIFF.
- Oversized file.
- Malformed XML.
- XLIFF with DTD/entity declarations.
- Server with DOM/ext-xml.
- Server without DOM/ext-xml.

## Frontend routing and SEO

- Home, default language URL and target language URLs.
- Post, page, product, category, tag, custom post type and 404.
- Canonical URL.
- `hreflang` alternates.
- `html lang`.
- Sitemap output when enabled.
- Browser-language redirect with fresh cookies and existing language cookie.
- Cache plugin enabled and disabled.

## WooCommerce

When WooCommerce is active:

- Shop, product, product category and product search.
- Cart, checkout, account, order-pay, thank-you and coupon flows.
- HPOS enabled.
- Safe mode enabled and disabled.
- Product-term/attribute output translation.
- Confirm no translated display text mutates order data unexpectedly.

## Admin UX and accessibility

- Admin tabs, save buttons, notices and error states.
- Keyboard navigation in admin screens and visual editor.
- Focus state visibility.
- Screen-reader labels for form controls and action buttons.
- Browser console free of errors on admin screens and translated frontend pages.

## Final release validation evidence

A release owner may mark runtime-dependent categories as complete only after evidence is recorded for the active target stack.

### Security & trust boundaries

- REST/admin-post capability and nonce failures recorded.
- Import/export malformed input matrix recorded.
- Secret exposure check recorded for logs, REST, exports and frontend output.

### Stability & compatibility

- Plugin Check output saved and triaged.
- Activation/deactivation/upgrade/rollback result recorded.
- DOM/ext-xml available and unavailable behavior recorded.

### WooCommerce/HPOS

- HPOS order flow recorded.
- Cart/checkout/account/order-pay/coupon/payment redirect result recorded.
- Safe-mode behavior recorded.

### Performance/CWV

- TTFB before/after translation recorded.
- Cache variation by language recorded.
- Switcher asset loading recorded.

### Accessibility/admin UX

- Keyboard path recorded.
- Focus visibility recorded.
- Screen-reader label spot check recorded.

### Privacy & AI boundary

- Privacy exporter/eraser result recorded.
- AI default-off and public-page no-call behavior recorded.
- Provider failure and DPA/client approval evidence recorded before enabling AI.


## Performance/accessibility evidence

Use `docs/PERFORMANCE-ACCESSIBILITY-EVIDENCE.md` as the staging checklist for performance and accessibility checks.
