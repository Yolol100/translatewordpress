# Changelog

## 2.4.0
- Privacy: workflowjobs geven e-mailadressen van toegewezen vertalers alleen nog terug aan beheerders.
- Healthcheck: controleert nu ook de AI-verbruiktabel zodat systeemcontrole geen onterecht groen signaal geeft.
- Visual editor: live preview vervangt alleen nog simpele tekst-elementen en slaat inline markup niet meer lokaal plat.
- Setup API: wizard-stapwaarden gelijkgetrokken met de actuele adminflow.
- Cleanup: oude `wat-premium-app-header` CSS-restanten verwijderd.

- Workflow-controles laden pas nadat er een taal gekozen is, zodat er geen lege language REST-fouten ontstaan bij eerste render.
- Assignee-lijst is admin-only gemaakt om vertalergegevens en e-mailadressen niet onnodig via REST bloot te stellen.
- Systeemcontrole toont nu de juiste databaseversie via `wat_db_version`.
- Dashboard blokkeert de visuele editor tot setup en scan klaar zijn.
- Scan verwerkt grote sites rustiger in veilige stappen in plaats van onbeperkt door de browser te recursen.
- Kleine cleanup van oude workflow-CSS en een dubbele visual-editor-tekstconditie.

## 2.3.3
- Cleanup: oude no-op workflow-scripts verwijderd nadat workflow volledig binnen de React TabPanel draait.
- Cleanup: admin enqueue opgeschoond zodat er geen leeg compatibiliteitsscript meer wordt geladen.
- Cleanup: obsolete workflow-root CSS-selectors en dubbele CSS-declaratie verwijderd.
- Release: block.json, asset fallback en POT-metadata gelijkgetrokken met de pluginversie.

## 2.3.2
- Assetversie gelijkgetrokken met pluginversie zodat WordPress/browsercache de juiste adminbundles laadt.
- Workflowpaneel verplaatst naar de echte React TabPanel-content; de losse native-workflow-root is vervangen door een no-op compatibiliteitshandle.
- Vertalers krijgen toegang tot de pluginpagina via `wat_manage_translations`; Setup en Geavanceerd blijven alleen zichtbaar voor beheerders.
- Dashboard en taaloverzicht zijn leesbaar voor vertalers met beperkte, niet-technische settingsdata.
- Taal toevoegen versimpeld: snelle taalkeuze is de hoofdroute, handmatige locale/RTL/flag-velden staan achter Handmatig aanpassen.
- Readme bijgewerkt naar de actuele klantflow.

## 2.3.1
- Vereenvoudigde adminnavigatie naar een klantvriendelijke hoofdflow: Start, Setup, Scan & vertaal, Controle & publicatie, Visuele controle en Geavanceerd.
- CSV/XLIFF/import/export/logs/systeemcontrole gebundeld achter Geavanceerd zodat dagelijkse vertalers minder technische ruis zien.
- Labels aangescherpt naar klanttaal: beschermde woorden, eerder vertaalde teksten, AI-verbruik en publicatiekwaliteit.
- Oude `wat_tab=tools` en `wat_tab=status` routes mappen automatisch naar Geavanceerd.
- Stable tag in readme gelijkgetrokken met de pluginversie.

## 2.3.0
- Added per-client AI usage CSV export (line items + summary footer) for agency re-billing, with spreadsheet formula-injection guard.
- Added admin-post `wat_ai_usage_export` endpoint (nonce + manage_options protected) and exposed its URL to the admin UI.
- Added `AiUsageLedger::export_rows()` and `export_csv()` with period and target-language filters.

## 2.2.0
- Added SEO meta auto-detection: scans the active SEO plugin's custom title and meta description (Yoast, Rank Math, AIOSEO, SEOPress) into the translation queue.
- Added automatic sync of translated SEO titles/descriptions back into post meta so SeoMetaManager renders them on the front end.
- SEO fields are re-queued on post save and only published/reviewed translations are surfaced as live SEO output.
- Unified the `wat_after_translation_saved` event to fire from every translation writer (manual, visual editor, AI batch, CSV, XLIFF).

## 2.1.1
- Replaced the workflow pseudo-tab DOM injection with a real React TabPanel tab and URL-synced visibility.
- Removed the legacy workflow-tab DOM mutation script by keeping it as a no-op compatibility handle.
- Restricted XLIFF import/export REST endpoints to administrators and added a bounded XLIFF upload size check.
- Kept the admin shell visible for the active tab list while only hiding the empty workflow tab panel content.

## 2.1.0
- Enhanced the front-end visual editor with keyboard-activatable click-to-edit segments.
- Added a visual editor preview endpoint for existing translations and Translation Memory suggestions.
- Added status, source, live-region and Ctrl/Cmd+Enter save feedback in the visual editor panel.

## 2.0.0
- Added first-class XLIFF 1.2 export for outsourced professional translation workflows.
- Added secure XLIFF import with target-language validation and hash/source fallback matching.
- Added REST endpoints for `/xliff/export` and `/xliff/import` alongside CSV workflows.

## 1.9.0
- Added workflow job assignment support for AI translation jobs.
- Added REST endpoints for translator assignees, assigned jobs and due dates.
- Added assignment summary data for the native workflow dashboard.


## 1.6.47

- Preserved WordPress-safe HTML in AI translation input and provider output.
- Added DeepL HTML tag handling for translated markup.
- Made AI API-key detection include server constants and the `wat_ai_api_key` filter.
- Added optional `WAT_DISABLE_DB_AI_CREDENTIALS` support.
- Expanded opt-in uninstall cleanup for credentials, plugin options, transients, role capabilities and temporary CSV files.
- Relaxed REST validation for safe OpenAI-compatible model identifiers while keeping provider normalization.
- Hardened scanner decoding for serialized arrays by disabling class instantiation.
- Completed CSV preview localization and preserved safe HTML in preview rows.


## 1.6.46
- Security/release hardening: removed activation-time automatic plugin deletion, clarified AI API-key storage behavior, sanitized AI provider output, and aligned block/language asset versions.
- Release compliance: corrected readme/POT AI-key storage disclosure and documented external AI service privacy/terms references.

## 1.6.45
- Admin UI consistentiepatch: uniforme buttons, velden, cards, spacing, tabellen en AI-connect layout.
- Overbodige AI-connect uitlegregel verwijderd zodat het blok rustiger oogt.


## 1.6.37 - Stronger old-plugin replacement
- Hardened activation-time replacement detection for old or renamed Webactueel Translate / WAT language dropdown builds using plugin headers, slugs and code fingerprints.
- Keeps deletion restricted to identifiable Webactueel/WAT translation builds to avoid removing unrelated multilingual plugins.

## 1.6.35 - Replacement cleanup hardening
- Added activation-time replacement cleanup for older Webactueel Translate / language-dropdown installations, using WordPress deactivation and deletion APIs before continuing setup.
- Aligned release metadata for the patched package.

## 1.6.32
- Laatste release-polish: block assetversies gelijkgetrokken met pluginversie.
- Vertaaltemplate opgeschoond zodat oude verwijderde UI-labels niet meer als actuele strings blijven staan.

## 1.6.30
- Fixed REST settings validation for the OpenAI-compatible AI provider.
- Added REST support for saving the custom OpenAI-compatible endpoint.
- Verified PHP, JavaScript, JSON and ZIP integrity after the AI settings cleanup.

## 1.6.29
- Admin tabs opnieuw beoordeeld op klantwaarde.
- AI-instellingen uitgebreid met providerkeuze, modelkeuze, custom OpenAI-compatible endpoint en testvertaling.
- Oude, niet meer gebruikte automation-tab code uit de admin bundle verwijderd.

## 1.6.28
- Filterselects tonen nu nog maar één pijl door de native browser-arrow uit te schakelen.
- Dashboard opgeschoond: het volledige “Aan de slag”-blok is verwijderd zodat alleen de vier actiekaarten blijven staan.
- Dashboard opgeschoond: publicatiecheckblok verwijderd en statussen naar vier compacte items gebracht.
- Vertaalfilters visueel gelijkgetrokken zonder dubbele browser/component-border.
- Admin navigatie opgeschoond naar klantwaarde: Overzicht, Vertalingen, Visuele editor, CSV & back-up en Instellingen.
- AI-workflow verplaatst naar Instellingen en Visuele editor teruggebracht tot noodzakelijke acties.

## 1.6.23
- Reworked the Visuele editor tab into an essential-only workflow screen with the required status, actions, and settings links.
- Removed noisy guidance cards from the visual editor workspace and clarified when to use table/CSV instead.

## 1.6.21
- Vervangt de lege vertaalmodus-tab door een bruikbaar werkdashboard met status, workflow, use-cases en publicatiechecklist.

- Improved admin toast styling so saved messages visually match the plugin design.
- Added a sticky settings save bar for unsaved changes.


## 1.6.19 - Admin filter layout fix
- Aligned the translation search and filter controls in one consistent responsive grid.
- Normalized TextControl and SelectControl sizing, border radius, spacing, and focus states.

## 1.6.18 - Release compliance polish
- Added stricter CSV export response headers for browser download hardening.
- Added explicit upload error handling before CSV preview processing.
- Made remaining CSV import validation messages translatable.
- Re-ran static PHP and WordPress quality checks before packaging.

## 1.6.17 - Settings QA completeness
- Added visible controls for browser redirect, remember language, media translation, WooCommerce deep translation, and visual-editor review workflow.
- Added dependency states so frontend, SEO, AI, and cache sub-controls no longer appear independently active when their parent setting is off.
- Verified settings defaults against REST argument allow-list and admin UI coverage.

## 1.6.16
- Fixed admin settings layout rhythm for language/switcher cards.
- Removed card styling from language-column wrappers so cards no longer visually collide.
- Improved AI workflow checklist spacing and icon/text alignment.

## 1.6.15 - Canonical admin UI system
- Consolidated the wp-admin interface into one canonical design-system layer.
- Removed the parallel wat-ui token layer and neutralized legacy radius/shadow regressions.
- Normalized cards, grids, forms, buttons, tabs, spacing and responsive behavior across all admin tabs.
- Reworked import/export alignment and removed AI-workflow orphan-card sizing.
- Kept plugin routes, settings, database schema, public hooks and frontend behavior unchanged.

## 1.6.13 - AI request throttling hardening

- Added a per-user AI translation rate limit before external provider calls to reduce accidental provider-cost spikes and abuse risk.
- Exposed the `wat_ai_rate_limit_per_minute` filter so site owners can tune or disable the throttle intentionally.

## 1.6.12 - Safe structure cleanup
- Removed exact duplicate admin CSS rules within the same cascade context.
- Extracted shared sitemap alternate-link generation into one private helper without changing sitemap output.
- Split CSV upload validation and temporary-directory handling into focused traits while keeping the public upload/import behavior unchanged.
- Left the generated admin JavaScript bundle intact because no source build pipeline is shipped in the release package.

## 1.6.11 - WordPress best-practice 2026 hardening
- Replaced visual editor configuration localization with explicit inline JSON configuration before the frontend script.
- Added a block-native script module for the language switcher block while preserving the classic script for shortcode and floating switcher usage.
- Avoided duplicate classic switcher script loading for the block on WordPress versions with Script Modules support.
- Added libxml error cleanup on DOM parse failure paths.
- Kept public hooks, REST routes, database schema, option names, shortcodes and frontend behavior unchanged.

## 1.6.10 - Final per-file review cleanup
- Scoped remaining admin CSS utility selectors under the plugin admin root to reduce WordPress admin leakage risk.
- Re-ran file-by-file syntax, asset and release checks.

## 1.6.9 - Final admin polish verification

- Tightened admin wrapper CSS selectors to reduce cross-plugin leakage risk.
- Made remaining small admin-visible strings translatable and Dutch.
- Localized media helper and privacy-policy helper text for the Dutch admin experience.
- Re-ran PHP, JS, JSON and ZIP integrity checks after the UI polish pass.

## 1.6.8 - Admin UI polish
- Compactere WordPress-native admin layout met betere maximale breedte, cards, tabs en grids.
- Adminlabels consequent Nederlands gemaakt.
- Woordenlijst-filterlabel gededupliceerd.
- Vertalingen-, tools-, AI-workflow- en instellingenschermen visueel opgeschoond.

## 1.6.7 - Audit hardening and release cleanup
- Hardened the CSV preview temporary directory boundary: custom `wat_csv_temp_dir` values must now resolve inside allow-listed temp/upload bases, with `wat_csv_temp_dir_allowed_bases` available for deliberate extensions.
- Added provider-aware AI model validation through `Settings::sanitize_ai_model()` and a filterable `wat_allowed_ai_models` allow-list.
- Loaded bundled translations explicitly with `load_plugin_textdomain()` during `plugins_loaded`.
- Improved language-switcher asset reliability for shortcode, block and active widget contexts; the block now also declares the frontend dropdown script handle.
- Consolidated noisy 1.6.5 release notes into a cleaner changelog structure.

## 1.6.5 - React, design-system and release-readiness hardening
- Rebuilt the visual editor runtime as a React/WordPress `wp.element` component with improved dialog semantics, live status notices, focus trapping, cleanup and focus return.
- Registered the language switcher block from `block.json` metadata with an explicit editor script handle and shared switcher/design-system styles.
- Aligned admin, switcher and visual editor styles to one semantic design-token system and removed stale generated-CSS references.
- Hardened REST argument schemas and validation for settings, import, scan, setup, translation statuses and AI translation flows.
- Added AI disclosure, request length limiting and an admin warning when AI translation is enabled.
- Improved visual editor permissions with a dedicated translator capability and translator role.
- Added media translation fields for per-language image replacements, alt text and titles.
- Added deeper WooCommerce translation filters for product names, descriptions, attributes, order item names and product terms.
- Improved browser-language redirect matching with quality values and locale-aware language matching.
- Added paginated multilingual sitemap output and clearer privacy/readiness documentation.

## 1.6.3 - Feature hardening and workflow polish
- Hardened media translation so runtime URL/image swaps only run on frontend non-default language requests.
- Aligned visual-editor review status with the translator review setting.
- Exposed workflow status labels to translator-capable users.
- Added missing workflow labels for draft, needs review and ignored statuses.
- Added frontend-media guards for feeds, robots and sitemap contexts.
- Aligned deactivation flow with the translator role lifecycle hook.

## 1.6.1 - Multilingual SEO upgrade
- Added per-language canonical URL support for core output, Yoast SEO and Rank Math.
- Added a multilingual XML sitemap endpoint with hreflang alternates for public posts, pages and taxonomies.
- Added admin settings for canonical and multilingual sitemap controls.
- Strengthened translated slug/URL mapping support for sitemap generation.

## 1.5.5 - Admin design-system token polish
- Centralized the remaining admin color, border, shadow and radius values into shared design tokens.
- Reduced hardcoded CSS values across admin, switcher and visual editor stylesheets.
- Kept runtime behavior unchanged; this was a visual maintainability release.

## 1.5.3 - Final design-system consistency pass
- Aligned shared design tokens across admin, switcher and visual editor styles.
- Removed versioned CSS comments from runtime stylesheets.
- Normalized color, shadow and focus values to shared CSS custom properties.
- Revalidated PHP, JavaScript and package integrity.

## 1.5.2 - Unified design-system polish
- Added a shared design-token stylesheet used by admin, frontend switcher and visual editor UI.
- Removed stale generated-CSS version comments from runtime stylesheets.
- Kept runtime UI scoped to plugin-owned classes to avoid theme/admin pollution.
- Repacked as a clean production build after PHP lint, JS syntax and zip integrity checks.

## 1.5.1 - Design-system cleanup
- Removed stale generated-CSS references and old admin URL alias code.
- Unified admin, language switcher and visual editor CSS around one Webactueel Translate design-token system.
- Scoped visual-editor design variables to the active editor state to avoid global theme pollution.

## 1.5.0 - Modern product layer
- Added AI translation foundation for OpenAI and DeepL using constants/filter-based API keys.
- Added AI & workflow admin screen with provider, model, tone, review and performance controls.
- Added Yoast SEO and Rank Math filters for translated title and description output.
- Added lightweight frontend translation performance snapshots and admin-only Server-Timing diagnostics.
- Kept WooCommerce conversion-page safeguards, existing public hooks and fail-open frontend behavior intact.

## 1.7.0 Gutenberg/React polish patch

- Prefer `wp.element.createRoot()` for the admin React mount, with `wp.element.render()` kept as a fallback.
- Stop mutating the global `wp.components` object for fallback components; local wrappers are used instead.
- Add an editable `src/admin/` source entry mirroring the compiled admin asset.
- Add `package.json` with `@wordpress/scripts` build/lint/format commands.
- Add `docs/GUTENBERG-REACT-BUILD.md` with build and manual UX/a11y release checks.
