# Release quality gates

This document defines the evidence needed before Webactueel Translate is approved for an agency/client release. It is a checklist-backed release validation record, not a marketing claim.

## Validation policy

- **Package review** confirms the ZIP contains the code, documentation and maintainership gates needed for a controlled release.
- **Runtime validation** confirms the staging owner has completed and recorded the matching tests on the target stack.
- Do not mark a category complete from static review alone when it depends on browser behavior, roles, WooCommerce, caching, provider failures or real uploads.

## Security & trust boundaries

Required evidence:

1. REST/admin-post role matrix tested for logged-out, subscriber, editor, translator and administrator.
2. Missing/invalid nonce and missing capability failures tested.
3. CSV/XLIFF import rejects empty, oversized, wrong-extension, wrong-MIME, PHP-renamed, malformed XML and DTD/entity files.
4. Export endpoints fail safely when required PHP extensions are unavailable.
5. AI keys are not present in logs, REST responses, frontend markup, CSV/XLIFF exports, screenshots or browser console output.
6. Logs contain enough diagnostic detail without raw secrets or unnecessary personal data.

## Stability & compatibility

Required evidence:

1. PHP syntax and JS syntax checks pass for the release ZIP.
2. Clean activation, deactivation, reactivation and upgrade from the previous stable ZIP pass.
3. Rollback to the previous stable ZIP is tested with cache clear and permalink flush when routing changed.
4. Server with DOM/ext-xml and server without DOM/ext-xml both fail safely where expected.
5. Plugin Check output is saved and triaged.
6. Elementor, ACF, Yoast/Rank Math and a common cache plugin are smoke-tested when present on the client stack.

## WooCommerce/HPOS

Required evidence when WooCommerce is installed:

1. HPOS is enabled and no order-storage compatibility notices or fatal errors occur.
2. Cart, checkout, account, order-pay, thank-you, coupon and payment redirect flows complete on staging.
3. Safe mode skips conversion-critical pages where output buffering would be risky.
4. Product title, variation, attribute, category/tag and order-item display translations are checked without mutating stored order data.
5. Cache/session behavior is checked after language switching during a shop flow.

## Performance/Core Web Vitals

Required evidence:

1. TTFB comparison is recorded with frontend translation enabled and disabled on representative pages.
2. Translation map cache and invalidation are checked after translation/settings changes.
3. Page cache/object cache variation by language is tested.
4. Frontend switcher assets load only when the switcher is present or floating mode is enabled.
5. Browser console and network waterfalls are checked for duplicate assets, blocking requests and avoidable layout shifts.

## Accessibility/admin UX

Required evidence:

1. Admin tabs, visual editor, notices and save/error states work by keyboard.
2. Visible focus indicators are present on admin controls and the frontend language switcher.
3. Language switcher labels work for flag-only, dropdown and inline layouts.
4. Screen-reader names for buttons, menu items and form controls are understandable.
5. WAVE/axe or equivalent spot checks are recorded for admin and representative frontend output.

## Privacy & AI boundary

Required evidence:

1. Privacy policy helper content is visible in WordPress privacy tools.
2. Exporter and eraser are tested with a real admin/translator user.
3. AI is disabled by default and no AI calls occur on public page loads.
4. Provider timeout/failure, rate/cost limit and malformed provider response cases fail safely.
5. The site owner has recorded provider, retention, DPA, processing location and client approval before enabling AI.

## Maintainership rule

Future changes must update this file, `RELEASE-CHECKLIST.md`, `docs/TEST-MATRIX.md` and the relevant module documentation when a new trust boundary, WooCommerce flow, frontend asset, external provider or database query is added.


## Performance/accessibility evidence

Use `docs/PERFORMANCE-ACCESSIBILITY-EVIDENCE.md` as the staging checklist for performance and accessibility checks.
