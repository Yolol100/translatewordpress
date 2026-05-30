=== Webactueel Translate ===
Contributors: webactueel
Tags: translation, multilingual, csv, elementor, acf
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 2.4.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Frontend translation plugin with manual translations, CSV/XLIFF import/export, scanning and a visual translation editor.

Built for review-first agency workflows: scan content, translate manually or with AI assistance, review changes, then publish with more control.

== Description ==

Webactueel Translate scans frontend-visible WordPress content and lets administrators or translator-role users manage translations manually, visually or via CSV. The current release includes a stronger workflow layer: visual editing, managed AI batches, quality and context reporting, media translation, WooCommerce-aware output, browser-language redirects, Yoast/Rank Math SEO filters, hreflang/canonical/sitemap foundations, glossary-aware translation maps and lightweight performance diagnostics.

== Features ==

* One WordPress admin page with tabs.
* Language management.
* Visual translation editor for editing page text in context.
* Dedicated translator role/capability for review-first translation workflows.
* Managed AI batch translation foundations for small, review-first administrator-controlled batches.
* Translation quality report data for missing translations, review work, identical source/target text and possible broken markup.
* Translation context warnings for reused source text, conflicting translation variants and multi-context strings.
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
* AI translation foundation for OpenAI, DeepL and OpenAI-compatible providers. API keys should be supplied via server constants or the wat_ai_api_key filter. Database storage through the admin UI is disabled by default and only works when explicitly enabled by WAT_ENABLE_DB_AI_CREDENTIALS or the wat_allow_db_ai_credentials filter. Submitted text is disclosed to the selected external provider only when AI translation is enabled.
* Review-first workflow statuses for AI/manual translations.
* Yoast SEO and Rank Math title/description filter support for per-language SEO metadata.
* Glossary-aware translation maps for consistent brand and terminology replacement.
* Lightweight frontend performance snapshot and admin-only Server-Timing header for buffer diagnostics.

== Installation ==

1. Upload webactueel-translate-language-dropdowns.zip in WordPress > Plugins > Nieuwe plugin.
2. Activate Webactueel Translate.
3. Open Webactueel Translate in the admin sidebar.
4. Add languages, run a scan and add translations manually, visually, with managed AI batches or via CSV.


== Performance and compatibility notes ==

Meertalige SEO bevat hreflang, per-taal canonicals, Yoast/Rank Math metadata filters en een optionele XML-sitemap-index via `/?wat_language_sitemap=1`. De sitemap wordt intern gepagineerd zodat grotere sites niet stilzwijgend na de eerste batch posts of termen worden afgekapt.

Frontend output translation is guarded for normal HTML GET responses only. It skips admin, REST, AJAX, cron, feeds, sitemaps, XML-RPC and POST requests, respects a configurable maximum buffer size and is disabled for WooCommerce cart, checkout and account flows when safe mode is enabled. Translation maps use WordPress object cache and transients with plugin-owned invalidation.

This plugin uses plugin-owned custom database tables for translations, languages, glossary, scan jobs and logs. Direct database queries are intentional for those tables and should be reviewed together with their table-name escaping, placeholders, capability checks and cache invalidation behavior.

Managed AI batches are administrator-only, deliberately small and review-first. Long strings above the provider request limit are skipped rather than blocking the full batch. Generated translations should be reviewed before publication.

Context warnings are read-only workflow diagnostics. They do not change translations automatically; they help translators find reused source text, repeated contexts and conflicting translation variants before publication.

== Privacy ==

This plugin stores its own settings, languages, translation strings, translation values, scan jobs and logs in WordPress options and custom database tables. Translation strings may contain personal data when personal data exists in the original site content.

When AI translation is enabled, the text submitted for translation is sent to the configured external provider (OpenAI, DeepL or an OpenAI-compatible provider) for processing. The provider, model, source text, target language and tone/formality options may be part of that request. API keys can be read from server constants or supplied through the wat_ai_api_key filter. Saving keys through the plugin settings is disabled by default; it only stores keys in the WordPress database with autoload disabled when `WAT_ENABLE_DB_AI_CREDENTIALS` or the `wat_allow_db_ai_credentials` filter explicitly allows this. Site owners should only enable AI translation after confirming that the selected provider, processing location, retention terms, data processing agreement and privacy policy fit their legal and client requirements. AI translation is disabled by default and generated translations can remain review-first before publication.

External AI services used only when enabled/configured:

* OpenAI API: https://openai.com/policies/service-terms/ and https://openai.com/policies/privacy-policy/
* DeepL API: https://www.deepl.com/pro-license and https://www.deepl.com/privacy/
* OpenAI-compatible providers: the site owner controls the endpoint and must document/review that provider's terms, privacy policy, retention and data-processing terms before use.

When frontend language detection is enabled, the plugin can store the selected language in the `wat_language` cookie. CSV previews are temporarily copied to a protected temporary directory, tied to the administrator who created them and removed after import or expiry. The admin interface may store dashboard preferences in user meta and local browser storage. The plugin registers WordPress privacy policy helper text plus exporter and eraser callbacks for administrator preferences.

== Changelog ==

= 2.4.4 =
* Improved frontend language switcher menu-button accessibility and keyboard behavior.
* Added stronger menuitem tabindex handling for dropdown language navigation.
* Aligned switcher and workflow visual states closer to WordPress admin theme variables.
* Added reduced-motion safeguards for switcher and native workflow micro-interactions.
* Rechecked Translation Memory: exact-match auto-apply is active before AI provider calls.

= 2.1.1 =
* Enhanced the visual editor with keyboard-accessible click-to-edit segments, live status feedback and Translation Memory suggestions.
* Added a visual editor preview endpoint for existing translations and memory matches before saving.

= 2.0.0 =
* Added first-class XLIFF 1.2 export for professional translation workflows.
* Added secure XLIFF import with target-language validation, XML NONET parsing and existing string matching by hash.
* Added REST endpoints for /xliff/export and /xliff/import alongside CSV/XLIFF import/export.

= 1.9.0 =
* Added workflow job assignment support with assignee and due date metadata for AI translation jobs.
* Added REST endpoints for assignment dashboards and translator job visibility.


= 1.8.0 =
- Added AI usage ledger, exact translation-memory reuse, AI glossary enforcement, content-change outdated status and workflow metric for outdated translations.

= 1.7.4 =
* Security/workflow: REST translation saves now respect the review-first workflow for non-admin translators, preventing direct publish/review status when review is required.
* Security/workflow: translation routes use `can_translate`; scan and CSV import/export routes use dedicated capabilities so editors and the dedicated `wat_translator` role can use the review-first workflow, not only administrators.
* Reliability: AI batch `run_batch()` now performs an atomic per-cursor claim so concurrent run-batch requests can no longer double-translate strings or corrupt job counters.
* Reliability: AI rate limiter replaced the non-atomic transient counter with an atomic options-table UPSERT counter that is safe under concurrency on shared hosting; matching uninstall cleanup added.
* Reliability: scan batch runner now enforces a server-side wall-clock budget and resumes from the cursor instead of risking a fatal mid-batch timeout.

= 1.7.2 =
* Added read-only translation context warnings for reused source text, conflicting translation variants and multi-context strings.
* Exposed context warnings through the workflow REST API for future dashboard review cards.
* Documented context warnings as diagnostics that do not automatically change translations.

= 1.7.1 =
* Added managed AI translation jobs with administrator-only REST endpoints for enqueueing, reading and running small batches.
* Added review-first AI batch worker support with provider failure pausing, progress counters, cursor tracking and overlong-string skipping.
* Added translation quality report data for missing translations, review work, identical source/target text and possible broken markup.
* Exposed workflow quality data through REST for future admin dashboard/publication checks.

= 1.7.0 =
* Preserved WordPress-safe HTML in AI translation input and provider output instead of stripping all markup.
* Added DeepL HTML tag handling when translated text contains markup.
* Made AI API-key status include server constants and the wat_ai_api_key filter.
* Hardened AI API-key handling: server constants/filter are preferred and database credential storage is opt-in via WAT_ENABLE_DB_AI_CREDENTIALS or the wat_allow_db_ai_credentials filter.
* Completed uninstall cleanup for AI credentials, plugin options, transients, translator capabilities and temporary CSV preview files.
* Relaxed REST AI model validation for safe OpenAI-compatible custom model identifiers while preserving provider-side normalization.
* Hardened scanner decoding for serialized arrays by disabling class instantiation.
* Completed CSV preview localization and preserved WordPress-safe HTML in preview rows.

= 1.6.46 =
* Release-candidate hardening for WordPress.org readiness.
* Removed activation-time automatic plugin deletion/replacement behavior.
* Clarified AI API-key storage and external-service privacy disclosures.
* Hardened AI remote requests with no redirects, unsafe URL rejection and response-size limits.
* Sanitized and length-limited AI provider output before returning translations.
* Aligned plugin, block, asset and translation-template version metadata.

= 1.6.32 =
* Laatste release-polish: block assetversies gelijkgetrokken met pluginversie.
* Vertaaltemplate opgeschoond zodat oude verwijderde UI-labels niet meer als actuele strings blijven staan.

= 1.6.30 =
* Fixed REST settings validation for OpenAI-compatible AI providers.
* Added support for saving the custom compatible endpoint from settings.
* Rechecked PHP, JavaScript, JSON and ZIP integrity.

= 1.6.29 =
* Admin opgeschoond per tab op klantwaarde.
* AI provider/model testopties toegevoegd onder Instellingen.
* Oude ongebruikte admin automation-code verwijderd.

= 1.6.28 =
* Filterselects tonen nu nog maar één pijl.
* Dashboard opgeschoond: het volledige “Aan de slag”-blok is verwijderd zodat alleen de vier actiekaarten blijven staan.
* Dashboard opgeschoond en vertaalfilters visueel gelijkgetrokken.
* Adminnavigatie opgeschoond naar klantwaarde: Overzicht, Vertalingen, Visuele editor, CSV & back-up en Instellingen.
* AI-workflow verplaatst naar Instellingen zodat alleen hoofdflows als tab zichtbaar blijven.
* Visuele editor beperkt tot noodzakelijke status, acties en gebruiksadvies.
* Overzicht uitgebreid met publicatiecheck voor talen, scan, open vertalingen en hreflang.
* Import/export hernoemd naar CSV & back-up en gecombineerd met woordenlijstbeheer.

= 1.6.21 =
* Bruikbare vertaalmodus-tab met status, workflow, use-cases en publicatiechecklist.
* Improved admin notice/toast styling so saved messages match the plugin UI.
* Added a sticky unsaved-changes save bar for settings screens.


= 1.6.19 =
* Fixed admin translation filter layout so the search field and language/status/source dropdowns align on one row with consistent styling.

= 1.6.18 =
* Added stricter CSV export response headers for browser download hardening.
* Added explicit upload error handling before CSV preview processing.
* Made remaining CSV import validation messages translatable.
* Re-ran static PHP, JavaScript, JSON and ZIP integrity.

= 1.6.17 =
* Added visible controls for browser redirect, remember-language cookies, media translation, WooCommerce deep translation, and visual-editor review workflow.
* Added dependency states for frontend, SEO, AI and cache sub-controls so controls no longer appear independently active when their parent setting is off.
* Verified settings defaults against REST arguments and admin UI coverage.

= 1.6.16 =
* Fixed admin settings layout rhythm for language/switcher cards.
* Improved AI workflow checklist spacing and icon/text alignment.

= 1.6.13 =
* Added per-user AI translation request throttling before external provider calls.
* Added the `wat_ai_rate_limit_per_minute` filter for controlled site-specific tuning.
* Kept public hooks, REST routes, database schema, option names, shortcodes and frontend behavior unchanged.


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
