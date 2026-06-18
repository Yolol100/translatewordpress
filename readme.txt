=== Webactueel Translate ===
Contributors: webactueel
Tags: translation, multilingual, csv, elementor, acf
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 2.7.13
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Frontend translation plugin with manual translations, CSV/XLIFF import/export, scanning and a visual translation editor.

Built for review-first agency workflows: scan content, translate manually or with AI assistance, review changes, then publish with more control.

For maintainers, the package includes `RELEASE-CHECKLIST.md` and the `docs/` folder with architecture, database, security and staging-test notes. These files are intended for agency handoff, release review and future maintenance.

== Description ==

Webactueel Translate scans frontend-visible WordPress content and lets administrators or translator-role users manage translations manually, visually or via CSV. The current release includes a stronger workflow layer: visual editing, managed AI batches, quality and context reporting, media translation, WooCommerce-aware output, browser-language redirects, Yoast/Rank Math SEO filters, hreflang/canonical/sitemap foundations, glossary-aware translation maps and lightweight performance diagnostics.

== Features ==

* One WordPress admin page with tabs.
* Language management.
* Visual translation editor for editing page text in context, with manual AI suggestions, Translation Memory reuse and explicit review/publish actions.
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
* AI translation foundation for OpenAI, DeepL, Google Translate and OpenAI-compatible providers. API keys should be supplied via server constants or the wat_ai_api_key filter. Database storage through the admin UI is disabled by default and only works when explicitly enabled by WAT_ENABLE_DB_AI_CREDENTIALS or the wat_allow_db_ai_credentials filter. Submitted text is disclosed to the selected external provider only when AI translation is enabled.
* Review-first workflow statuses for AI/manual translations.
* Yoast SEO and Rank Math title/description filter support for per-language SEO metadata.
* Glossary-aware translation maps for consistent brand and terminology replacement.
* Opt-in frontend performance snapshot and admin-only Server-Timing header for buffer diagnostics.
* Multilingual SEO checks and translation coverage endpoints for publish-readiness checks.
* Optional switcher mode that hides globally empty target languages until translations are published or reviewed.
* AI context profile for site context, audience, preferred terminology and do-not-translate terms.
* WooCommerce mixed-language risk reporting for safer product/cart/checkout staging checks.
* Optional gettext discovery for dynamic WordPress/theme/plugin strings with a domain allow-list and per-request throttle.
* Optional runtime output-buffer discovery for unknown frontend text nodes and translatable attributes.
* Scanner support for navigation menus, active widgets and public taxonomy terms.
* Optional conditional publish mode that hides/redirects unpublished target languages until coverage is complete.
* Google Translate API provider option alongside OpenAI, DeepL and OpenAI-compatible providers.

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

When AI translation is enabled, the text submitted for translation is sent to the configured external provider (OpenAI, DeepL, Google Translate or an OpenAI-compatible provider) for processing. The provider, model, source text, target language and tone/formality options may be part of that request. API keys can be read from server constants or supplied through the wat_ai_api_key filter. Saving keys through the plugin settings is disabled by default; it only stores keys in the WordPress database with autoload disabled when `WAT_ENABLE_DB_AI_CREDENTIALS` or the `wat_allow_db_ai_credentials` filter explicitly allows this. Site owners should only enable AI translation after confirming that the selected provider, processing location, retention terms, data processing agreement and privacy policy fit their legal and client requirements. AI translation is disabled by default and AI translations can remain review-first before publication.

External AI services used only when enabled/configured:

* OpenAI API: https://openai.com/policies/service-terms/ and https://openai.com/policies/privacy-policy/
* DeepL API: https://www.deepl.com/pro-license and https://www.deepl.com/privacy/
* Google Cloud Translation API: https://cloud.google.com/terms and https://policies.google.com/privacy
* OpenAI-compatible providers: the site owner controls the endpoint and must document/review that provider's terms, privacy policy, retention and data-processing terms before use.

When frontend language detection is enabled, the plugin can store the selected language in the `wat_language` cookie. CSV previews are temporarily copied to a protected temporary directory, tied to the administrator who created them and removed after import or expiry. The admin interface may store dashboard preferences in user meta and local browser storage. The plugin registers WordPress privacy policy helper text plus exporter and eraser callbacks for administrator preferences.

== Upgrade Notice ==

= 2.7.13 =
Restores safe CSV fallback matching for empty or invalid hashes while keeping SHA-256 hash-based matching strict. Stage CSV/XLIFF imports before production.

= 2.7.12 =
Fixes release version drift for the language-switcher block/assets and tightens plugin hash validation for CSV/XLIFF imports. Stage before production.

= 2.7.11 =
Release-hygiene release. Production ZIP now ships only runtime files; admin and frontend JS/CSS are minified (whitespace/comment-only, behaviour identical to source) and dead build stubs were removed. No runtime, REST or database changes. Stage before production.

= 2.7.10 =
Fixes release packaging and build safety. Stage before production.

= 2.7.9 =
Final metadata/API-provider hygiene release. Fixes admin asset fallback version and Google Translate response decoding. Stage before production.

= 2.7.8 =
Verifies the 2.7.7 discovery/provider release and fixes Google Translate capability reporting plus duplicate performance-monitor registration. Stage before production.

= 2.7.7 =
Adds opt-in dynamic string discovery, scanner coverage for menus/widgets/terms, Google Translate provider support and conditional language publish controls. Enable new discovery features on staging first.

= 2.7.6 =
Admin JS and CSS are now minified for production (whitespace-level only; behaviour identical to source). Source files stay readable under src/admin. No runtime, REST or database changes. Verify the admin tabs load normally on staging.

= 2.7.5 =
Full admin interface restyle: one unified WordPress-native + SaaS design across all tabs, rebuilt from scratch. CSS only — backend, REST and database unchanged. Verify every tab (Overzicht, Vertalingen, Workflow, Visuele editor, CSV & back-up, Instellingen, Systeemcontrole) on staging.

= 2.7.4 =
Translations tab redesign aligned with the provided screenshot layout. Keeps backend and database unchanged. Verify filters, edit modal, pagination UI and REST loading on staging.

= 2.6.8 =
Admin dashboard source/UX maintenance release. Keeps backend and database unchanged. Verify admin dashboard, language modal, tabs and REST loading on staging.

= 2.6.6 =
Performance/accessibility release. Adds deferred frontend script loading, clearer switcher semantics and a performance/accessibility validation template. No database migration required.

= 2.6.4 =
Release-readiness and maintainability hardening package. Adds private-plugin update protection, clearer operational docs, database-contract documentation and a stricter release/test matrix. No database migration required.

= 2.6.3 =
Improved visual-editor JavaScript reliability and strengthened admin tab event handling. No database migration required.

= 2.6.2 =
Frontend hardening release. Fixes clipboard fallback handling and allows visual-editor source URLs on configured language domains. Test admin copy actions and visual editor domain setups on staging.

= 2.6.1 =
Visual workflow reliability release. Fixes complex markup handling in the visual editor, AI rate-limit handling, privacy export/erase coverage, URL scheme handling, XLIFF sniffing and custom endpoint guidance. Verify on staging before production.

= 2.6.0 =
Visual translation workflow release candidate. Adds manual AI suggestions, explicit review/publication actions and stronger in-context editor guidance. Test the visual editor, REST permissions and AI settings on staging before production.

= 2.5.0 =
Feature release. Adds SEO/coverage diagnostics, AI context profile controls, switcher visibility control and WooCommerce mixed-language risk reporting. Run staging checks before production.

= 2.4.18 =
Documentation-only release polish. Run the included RELEASE-CHECKLIST.md on staging before production, especially after the previous internal refactor pass.

= 2.4.17 =
Technical maintenance release after the internal refactor pass. Install on staging first and verify language routing, settings/AI credentials, AI batch jobs, translation map/cache behavior, REST permissions, WooCommerce safe-mode pages and CSV/XLIFF import-export before production.

== Changelog ==

= 2.7.13 =
* Restored CSV preview/import fallback matching for rows with empty or invalid hashes when validated original text, source context and language data are present.
* Kept hash-based matching limited to valid plugin-generated SHA-256 hashes.
* No database schema, REST namespace, shortcode, option key or asset handle changes.

= 2.7.12 =
* Synced plugin, readme, block metadata, editor asset fallback and POT header versions to 2.7.12.
* Tightened CSV preview/import and XLIFF hash handling to accept only plugin-generated SHA-256 hashes.
* No database schema, REST namespace, shortcode, option key or asset handle changes.

= 2.7.11 =
* Production ZIP now contains runtime files only; build inputs (src/, tools/, package.json) are no longer shipped.
* Admin and frontend JS/CSS are minified for production (whitespace/comment-only via no-mangle, no-compress; behaviour proven identical to source).
* Removed unused native-workflow.js and native-workflow.css build stubs.
* No runtime, REST, hook, option, slug or database changes. Synced plugin, readme, package and asset fallback versions to 2.7.11.

= 2.7.10 =
* Repacked the plugin with normal ZIP paths so WordPress/Linux extraction does not create backslash file names.
* Hardened the admin asset build helper so missing esbuild no longer silently overwrites production assets with unminified copies.
* Added WAT_ALLOW_UNMINIFIED_BUILD=1 as an explicit local debug escape hatch only.
* Synced plugin, readme, package, block and asset fallback versions to 2.7.10.

= 2.7.9 =
* Synced the admin asset fallback version and build helper to the active plugin version.
* Removed double encoding from the Google Translate API key query argument.
* Decoded Google Translate HTML entities before storing sanitized provider output.

= 2.7.8 =
* Fixed Google Translate provider capability/API-key status reporting so the admin API reflects `google_translate` consistently.
* Added Google Translate to AI batch capability metadata.
* Removed duplicate PerformanceMonitor registration to avoid duplicate metrics/header hooks.
* Synced plugin, readme, package and block metadata versions to 2.7.8.

= 2.7.7 =
* Added optional gettext discovery for dynamic WordPress, theme, plugin and WooCommerce strings. It is off by default, frontend-only, domain-allowlisted and throttled per request.
* Added optional runtime output-buffer discovery for unknown text nodes and translatable attributes. It is off by default and capped per page.
* Extended the scanner to include navigation menus, active widgets and public taxonomy terms after full/WooCommerce scans.
* Added Google Translate API as an external translation provider, with `WAT_GOOGLE_TRANSLATE_API_KEY` support.
* Added optional conditional publish mode: target languages can be hidden and redirected until all discovered strings are published/reviewed.
* Existing REST route names, hooks, table names and core translation storage are kept intact.

= 2.7.6 =
* Build pipeline now minifies the admin JavaScript and stylesheet for production. Minification is whitespace-level only (whitespace and comments removed); identifiers and logic are left unchanged, so behaviour is identical to the source.
* The readable source files under src/admin remain the single source of truth; build/admin now contains the minified output that WordPress enqueues. No enqueue handles, option keys, REST routes or database changes.
* Admin JavaScript reduced by roughly 20% and the stylesheet by roughly 18% in transfer size.
* CSS verified equivalent (identical selectors and declarations); JavaScript verified equivalent via syntax-tree analysis (only standard whitespace and safe canonical forms differ). Build now requires esbuild as a dev dependency (run npm install before npm run build).

= 2.7.5 =
* Rebuilt the entire admin stylesheet from scratch into one unified WordPress-native design system with light SaaS polish, shared across all seven tabs.
* Removed all legacy per-screen CSS treatments (workflow-exact, visual-exact, system-redesign, dashboard) and the per-tab navigation overrides that made each tab look different.
* Harmonised the top tab navigation, page headers/heroes, stat cards, panels, badges, tables, progress bars, forms, modals, toasts, empty states and system-status UI so every screen uses the same tokens, colours, spacing, radii and shadows.
* Uses the WordPress admin palette (primary #2271b1, text #1d2327, borders #dcdcde, page #f0f0f1) with the WordPress notice colours for success/warning/danger states.
* CSS-only release. No changes to JavaScript logic, hooks, option keys, slugs, REST routes, database schema or the shared frontend design-system stylesheet.

= 2.7.4 =
* Replaced the active Vertalingen screen with the screenshot-aligned translation management layout.
* Added the compact Vertalingen header, status summary card, status filter tabs, filter bar, translation table and pagination UI.
* Kept the existing seven-tab navigation: Overzicht, Vertalingen, Workflow, Visuele editor, CSV & back-up, Instellingen and Systeemcontrole.
* No backend endpoint, REST permission, database schema, scan, CSV/XLIFF, AI or WooCommerce logic changes.

= 2.6.8 =
* Added editable admin dashboard source snapshot and rebuild helper so the React dashboard is no longer only present in build assets.
* Improved dashboard “Doeltaal toevoegen” actions so they open the language modal through the existing settings/language backend.
* Removed obsolete dashboard progress CSS left from the previous admin overview.
* No database schema changes.

= 2.6.6 =
* Performance/CWV hardening: frontend switcher and visual-editor scripts now use WordPress footer/defer loading where applicable.
* Performance snapshot logging is now opt-in by default to avoid frontend database writes on normal translated page views.
* Accessibility/admin UX hardening: dropdown menu semantics now expose vertical menu orientation and list semantics are explicit.
* Added `docs/PERFORMANCE-ACCESSIBILITY-EVIDENCE.md` for staging performance/accessibility validation.
* No database schema changes.

= 2.6.4 =
* Added private-plugin `Update URI` protection to avoid accidental WordPress.org update collisions.
* Added release, architecture, database-contract, security-review and staging test documentation for maintainable agency handoff.
* Expanded release gates for Plugin Check, PHPCS/WPCS, role-based REST tests, import/export edge cases and WooCommerce staging checks.
* No runtime behavior or database schema changes.

= 2.6.3 =
* Removed redundant visual-editor bulk-status state declaration found during the repeated scan loop.
* Hardened admin/native-workflow tab event dispatch with CustomEvent and popstate fallbacks for more resilient browser behavior.
* No database schema changes.

= 2.6.2 =
* Fixed admin shortcode/themecode copy feedback so clipboard failures do not show a false success message and older/insecure browsers use a safe textarea fallback.
* Allowed visual-editor source URL tracking for configured language-domain hosts while continuing to reject unrelated external URLs.

= 2.6.1 =
* Hardened visual-editor segment selection and live preview updates so complex buttons, links, icons and builder markup are not flattened during in-context editing.
* Fixed AI rate-limit cleanup so active counters for other users are not removed during the same minute window.
* Expanded privacy export/erase handling for AI usage metadata and assigned workflow jobs.
* Skips non-HTTP URL schemes during frontend URL rewriting.
* Makes XLIFF upload sniffing more tolerant of longer XML preambles while keeping DTD/entity blocking intact.
* Clarified custom OpenAI-compatible endpoint allowlist requirements in the admin UI.

= 2.6.0 =
* Enhanced the visual translation editor with explicit review-first save and publish actions.
* Added a manual AI suggestion endpoint for selected visual-editor segments; suggestions are only created after an authenticated translator action, never automatically on public pageviews.
* Reuses Translation Memory suggestions before paid/provider AI calls where possible.
* Added safer visual-editor REST status handling so non-admin translator submissions respect the review-required workflow setting.
* Improved visual-editor UI copy, keyboard workflow and AI/memory suggestion visibility.

= 2.5.0 =
* Added multilingual SEO check and translation coverage reporting endpoints for release/readiness dashboards.
* Added WooCommerce mixed-language risk reporting with explicit manual checkout/cart/account test guidance.
* Added optional switcher setting to hide target languages without published/reviewed translations while keeping default/current language visible.
* Added AI context profile settings for site context, target audience, brand terminology and do-not-translate terms; OpenAI/OpenAI-compatible requests now include the profile when enabled.
* Kept behavior safe by avoiding live public-page AI translation, reverse-proxy behavior or WooCommerce checkout output translation changes.

= 2.4.18 =
* Added RELEASE-CHECKLIST.md with reproducible package, static, staging, import/export, AI/privacy, WooCommerce/HPOS, uninstall and rollback checks.
* Synced plugin, readme, block metadata, asset fallback versions and translation-template project metadata to 2.4.18.
* No intentional feature, route, option, database schema, text-domain or frontend behavior changes.

= 2.4.17 =
* Release documentation hardening: added an explicit Upgrade Notice for the refactor maintenance release so site owners know to run staging checks for language routing, settings/AI credentials, AI jobs, translation maps, REST permissions, WooCommerce safe-mode pages and CSV/XLIFF import-export.
* Synced plugin, readme, block metadata, asset fallback versions and translation-template project metadata to 2.4.17.
* No intentional feature, route, option, database schema, text-domain or frontend behavior changes.

= 2.4.16 =
* Technical refactor release: split language routing, settings/AI policy, AI job queue and translation repository internals into smaller focused services while preserving public methods, hooks, routes, options and database table names.
* Kept the existing public facades for LanguageRouter, Settings, TranslationJobQueue and TranslationRepository to reduce backwards-compatibility risk.
* Synced plugin, readme, block metadata and asset fallback versions after the refactor validation pass.
* No intentional feature, route, option, database schema or text-domain changes.

= 2.4.15 =
* Release-gate hardening: exposed the strict frontend request guard in the REST settings schema.
* Workflow hardening: CSV/XLIFF imports by non-admin import users now respect review-first publishing rules.
* Validation hardening: AI job assignees and due dates are validated at REST boundary level.
* AI-boundary hardening: OpenAI-compatible model identifiers now default to an allow-list and can only be extended through the dedicated model filter.
* Hardened frontend output translation request guards for previews, builder edit modes, service endpoints and non-HTML file-like requests.
* Added a strict frontend guard setting, enabled by default, so agencies can explicitly relax edge-case behavior by filter/settings only.
* Tightened CSV upload validation for empty files and reduced fallback acceptance when MIME sniffing is unavailable.
* Hardened OpenAI-compatible model names and AI system instructions against untrusted source-text instructions.
* Synced release translation template and AI credential messaging so localized installs no longer mention default database storage for AI API keys.

= 2.4.13 =
* CSS architecture update: restored reproducible source CSS, central design tokens, modular component CSS and scoped build output with minimal specificity.
* Design consistency hardening: canonical admin grids, table wrappers and mobile actions normalized.
* Hardened visual editor REST validation with explicit length limits for selected source text, translations, selectors and same-site URLs.
* Aligned visual editor translator permissions with the normal translation workflow while keeping review-required submissions in needs_review for non-admin users.
* Restricted visual-editor saves to plain text segments to prevent accidental inline HTML from being stored through the frontend editing surface.
* Added admin CSS normalization for grids, action rows and responsive table scrolling to keep layouts consistent across the admin interface.

= 2.4.11 =
* Fixed the frontend language switcher dropdown loading its behaviour script twice on pages containing the switcher block, which made the menu toggle open and immediately closed. The block now reuses the same single switcher script as the shortcode and floating switcher instead of a separate, outdated module, so only one keyboard-accessible script is enqueued and run.
* Removed an unused no-op admin script and synced build asset version fallbacks; no behaviour change.

= 2.4.10 =
* Expanded WordPress privacy policy helper text for translation content, AI provider sharing, API-key handling, language cookies, temporary CSV previews and AI usage/log retention considerations.
* Added REST argument schemas for CSV/XLIFF export parameters and XLIFF import language filters.
* Added a WordPress Playground blueprint starter and runtime QA command documentation for Plugin Check, staging, WooCommerce/HPOS and privacy checks.

= 2.4.9 =
* Centralized shared CSS tokens for admin UI, switcher UI and z-index layers.
* Reduced frontend switcher `!important` usage and moved more styling to themeable CSS variables.
* Added documented z-index strategy with overridable CSS variables.
* Reduced broad PHPCS disables around REST/database code and switched selected custom-table queries to `%i` identifiers.
* Fixed visual editor ARIA restoration and AI cost estimate source column.
* Fixed REST CSV/XLIFF export responses so downloads are served as raw files instead of JSON-encoded strings.
* Aligned release package naming, folder naming and asset versions for the market build.
* Improved admin focus styling so focused fields keep a visible outline alongside the custom focus ring.

= 2.4.5 =
* Fixed opt-in uninstall cleanup so the AI usage table is removed together with the other plugin-owned tables.
* Fixed the health-check schema version value to read the stored database schema option.
* Added the AI usage table to the database table health-check.

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
* Removed outdated CSS comments and old runtime references.
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
