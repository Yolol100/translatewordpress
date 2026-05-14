=== Webactueel Translate ===
Contributors: webactueel
Tags: translation, multilingual, csv, elementor, acf
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.6.5
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Frontend-only translation plugin with manual translations, CSV import/export, scanning, a React admin interface and a React-powered visual translation editor.

== Description ==

Webactueel Translate scans frontend-visible WordPress content and lets administrators or translator-role users manage translations manually, visually or via CSV. Version 1.6 adds a stronger product layer: visual editing, media translation, WooCommerce-aware output, browser-language redirects, Yoast/Rank Math SEO filters, hreflang/canonical/sitemap foundations, glossary-aware translation maps and lightweight performance diagnostics.

== Features ==

* One WordPress admin page with tabs.
* Language management.
* Visual translation editor for editing page text in context.
* Dedicated translator role/capability for review-first translation workflows.
* Media translation fields for per-language image replacements, alt text and titles.
* WooCommerce-aware product, variation, attribute, order-item and product-term translation filters.
* Browser-language redirect support with locale/q-value matching and safe request guards.
* Translation table with search and pagination.
* Batch scanner for posts, pages, public post types, Elementor data, ACF fields and WooCommerce products where available.
* CSV export, CSV preview and CSV import.
* Frontend-only output translation with fail-open safety.
* Translation map cache using object cache/transients.
* Compatibility detection for popular multilingual, SEO, cache, form and builder plugins.
* Hreflang output that disables itself when another multilingual plugin is detected unless force override is enabled.
* Shortcode: [webactueel_translate_switcher]
* Requires the PHP DOM/ext-xml extension for frontend HTML output translation; without it, the plugin activates safely and skips frontend replacement with an admin notice.
* Debug-only slow-buffer telemetry with duration, buffer size, map size, replacement count, URL rewrite count and memory peak.
* AI translation foundation for OpenAI and DeepL using constants/filter-based API keys; keys are not stored in the database and submitted text is disclosed to the selected external provider only when AI translation is enabled.
* Review-first workflow statuses for AI/manual translations.
* Yoast SEO and Rank Math title/description filter support for per-language SEO metadata.
* Glossary-aware translation maps for consistent brand and terminology replacement.
* Lightweight frontend performance snapshot and admin-only Server-Timing header for buffer diagnostics.

== Installation ==

1. Upload webactueel-translate-language-dropdowns.zip in WordPress > Plugins > Nieuwe plugin.
2. Activate Webactueel Translate.
3. Open Webactueel Translate in the admin sidebar.
4. Add languages, run a scan and add translations manually or via CSV.


== Performance and compatibility notes ==

Meertalige SEO bevat hreflang, per-taal canonicals, Yoast/Rank Math metadata filters en een optionele XML-sitemap-index via `/?wat_language_sitemap=1`. De sitemap wordt intern gepagineerd zodat grotere sites niet stilzwijgend na de eerste batch posts of termen worden afgekapt.

Frontend output translation is guarded for normal HTML GET responses only. It skips admin, REST, AJAX, cron, feeds, sitemaps, XML-RPC and POST requests, respects a configurable maximum buffer size and is disabled for WooCommerce cart, checkout and account flows when safe mode is enabled. Translation maps use WordPress object cache and transients with plugin-owned invalidation.

This plugin uses plugin-owned custom database tables for translations, languages, glossary, scan jobs and logs. Direct database queries are intentional for those tables and should be reviewed together with their table-name escaping, placeholders, capability checks and cache invalidation behavior.

== Privacy ==

This plugin stores its own settings, languages, translation strings, translation values, scan jobs and logs in WordPress options and custom database tables. Translation strings may contain personal data when personal data exists in the original site content.

When AI translation is enabled, the text submitted for translation is sent to the configured external provider (OpenAI or DeepL) for processing. The provider, model, source text, target language and tone/formality options may be part of that request. API keys are read from constants or filters and are not stored by the plugin. Site owners should only enable AI translation after confirming that the selected provider, processing location, retention terms, data processing agreement and privacy policy fit their legal and client requirements. AI translation is disabled by default and generated translations can remain review-first before publication.

When frontend language detection is enabled, the plugin can store the selected language in the `wat_language` cookie. CSV previews are temporarily copied to a protected temporary directory, tied to the administrator who created them and removed after import or expiry. The admin interface may store dashboard preferences in user meta and local browser storage. The plugin registers WordPress privacy policy helper text plus exporter and eraser callbacks for administrator preferences.

== Changelog ==

= 1.6.5 =
* Added paginated multilingual sitemap output and a clearer AI/privacy disclosure for release readiness.
* Rebuilt the visual editor runtime as a React/WordPress wp.element component.
* Aligned admin, switcher and visual editor styles to one semantic design-token system.
* Registered the language switcher block from block.json metadata with an explicit editor script handle.
* Hardened REST argument schemas, AI translation limits, DeepL language-code handling and visual editor accessibility.
* Improved visual editor permissions with a dedicated translator capability and translator role.
* Added media translation fields for per-language image replacements, alt text and titles.
* Added deeper WooCommerce translation filters for product names, descriptions, attributes, order item names and product terms.
* Improved browser-language redirect matching with quality values and locale-aware language matching.

= 1.6.1 =
* Added per-language canonical URL support for core output, Yoast SEO and Rank Math.
* Added a multilingual XML sitemap endpoint with hreflang alternates for public posts, pages and taxonomies.
* Added admin settings for canonical and multilingual sitemap controls.

= 1.5.2 =
* Unified admin, switcher and visual editor styling around one shared design-token stylesheet.
* Removed stale generated-CSS comments and old runtime references.
* Repacked as a clean production build after syntax and package checks.

= 1.5.1 =
* Cleaned design-system consistency across admin and frontend UI.
* Scoped visual editor styling to reduce theme CSS impact.

= 1.5.0 =
* Added modern AI translation foundation for OpenAI and DeepL with review-first workflow safeguards.
* Added AI & workflow admin screen plus settings for provider, model, tone and review behavior.
* Added Yoast SEO and Rank Math filters for translated title and description output.
* Added lightweight frontend performance snapshots and admin-only Server-Timing diagnostics.
* Preserved safe WooCommerce checkout/cart/account behavior and existing public APIs.
