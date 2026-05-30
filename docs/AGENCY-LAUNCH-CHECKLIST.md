# Agency Launch Checklist

## Positioning
- Primary promise: review-first WordPress translations for agencies that need control, AI speed and import/export workflows.
- Primary audience: WordPress agencies, content teams and site owners managing multilingual client sites.
- Primary CTA: run a scan, review translation status and publish only approved translations.
- Avoid claims about guaranteed translation quality, legal compliance, performance scores or automatic SEO completeness without project testing.

## WordPress.org assets
- Add screenshots for: setup/dashboard, translation table, visual editor, AI batch review, CSV/XLIFF, SEO health, usage export.
- Add a short GIF/video showing: scan -> translate -> review -> publish.
- Keep the readme focused on workflow value, not only technical features.
- Document the AI privacy model clearly: disabled by default, external provider only when configured, server constants/filter preferred.

## Agency sales proof
- Prepare one demo site with Elementor content, WooCommerce product content, SEO fields and CSV/XLIFF roundtrip.
- Prepare one example usage export showing how an agency can rebill AI-assisted translation work.
- Prepare one glossary demo with a brand term, product name and do-not-translate term.
- Prepare one before/after workflow example: missing -> draft -> review -> published.

## Staging acceptance before public launch
- Fresh install, activation, deactivation, uninstall and upgrade path.
- Roles: administrator, editor, dedicated translator, subscriber and logged-out.
- Core workflows: scan, visual edit, manual edit, AI batch, Translation Memory, glossary, CSV/XLIFF, usage export.
- SEO plugins: Yoast, Rank Math, AIOSEO and SEOPress, at least one per staging pass.
- WooCommerce safe-mode: shop, product, cart, checkout, account, order-pay.
- Builder compatibility: Elementor page, normal block page, shortcode output.
- Plugin Check and PHPCS/WPCS report saved with release notes.

## Release decision
- GO: all acceptance tests pass and no high-risk regressions remain.
- GO AFTER FIXES: only non-critical documentation/UI issues remain.
- NO-GO: activation, role permissions, REST writes, checkout/account, import/export, AI key handling or uninstall cleanup fails.
