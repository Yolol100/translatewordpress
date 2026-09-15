# Webactueel Translate

Webactueel Translate is a WordPress translation plugin for review-first multilingual workflows. It combines frontend-visible content scanning, manual and visual translation, CSV/XLIFF import and export, optional AI-assisted translation, multilingual SEO foundations and compatibility-aware output translation.

The WordPress-style `readme.txt` remains the detailed distribution documentation and changelog. This `README.md` gives a concise GitHub overview of the project and how to use it.

## What it does

Webactueel Translate is designed for administrators, agencies and translators who want to discover, translate, review and publish multilingual WordPress content with explicit control over the workflow.

Main capabilities include:

- Manage languages from a single WordPress admin interface.
- Scan posts, pages, public post types, Elementor data, ACF fields, WooCommerce content, menus, widgets and taxonomy terms where supported.
- Edit translations manually or in a visual in-context editor.
- Reuse Translation Memory and glossary terminology.
- Import and export translations with CSV and XLIFF workflows.
- Use small, administrator-controlled AI translation batches with review-before-publish behavior.
- Detect missing translations, reused source text, conflicting variants and possible markup problems.
- Translate supported media fields and WooCommerce output.
- Add language switchers with `[webactueel_translate_switcher]`.
- Provide hreflang, language-aware canonical handling and integrations for Yoast SEO and Rank Math.
- Support optional browser-language redirects and conditional publishing.

## Requirements

- WordPress 6.5 or newer.
- PHP 8.1 or newer.
- PHP DOM/ext-xml for frontend HTML output translation.

Optional integrations include Elementor, ACF, WooCommerce, Yoast SEO and Rank Math. The plugin is designed to fail safely when optional integrations are unavailable.

## Installation

1. Copy the plugin directory to `wp-content/plugins/` or upload the packaged plugin ZIP in WordPress.
2. Activate **Webactueel Translate**.
3. Open **Webactueel Translate** in the WordPress admin sidebar.
4. Add the required languages.
5. Run a content scan.
6. Create or import translations, review them and publish when ready.

## Translation workflow

A typical workflow is:

1. Configure source and target languages.
2. Scan the site for translatable content.
3. Translate manually, visually, through CSV/XLIFF, or with an explicitly configured AI provider.
4. Review quality/context warnings.
5. Check multilingual SEO and coverage.
6. Publish reviewed translations.

The plugin intentionally favors review-first operation rather than silently publishing generated translations.

## AI and privacy

AI translation is optional and disabled until configured. When enabled, submitted translation text is sent to the selected external provider, such as OpenAI, DeepL, Google Translate or an explicitly configured OpenAI-compatible endpoint.

Site owners are responsible for checking the provider's processing location, retention, privacy terms and data-processing agreement before sending client or personal data. API keys should preferably be supplied through server constants or the documented filter mechanism rather than stored in WordPress.

The plugin also stores its own language, translation, glossary, scan and log data inside WordPress. See `readme.txt` for the full current privacy notes.

## Repository structure

- `webactueel-translate.php` — plugin bootstrap and metadata.
- `app/` — PHP application code.
- `blocks/` — block-related source where applicable.
- `build/` — built admin/frontend assets.
- `languages/` — translation files.
- `uninstall.php` — plugin cleanup logic.
- `readme.txt` — WordPress distribution documentation and changelog.

## License

GPL-2.0-or-later. See `LICENSE` and the plugin metadata.
