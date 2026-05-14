## 1.6.5 - Extra per-file i18n/UI code-quality pass

## 1.6.5 - Per-file i18n and quality hardening
- Wrapped remaining visible admin JavaScript labels in WordPress i18n helpers.
- Wrapped CSV preview/import and scan status error messages in translation functions.
- Refreshed POT coverage for newly localized strings.
- Re-ran PHP, JS, JSON, asset-reference, CSS-token, REST and unused-code checks.

- Wrapped remaining admin UI label strings in WordPress i18n helpers.
- Updated the POT catalog with the newly discovered admin UI strings.
- Re-ran PHP, JavaScript, JSON, asset-reference, CSS-token and package integrity checks.

# Changelog

## 1.6.5 - React and design-system modernization

### 1.6.5 per-file hardening follow-up
- Fixed setup REST schema to accept the admin app's `done` current step.
- Aligned translation status REST schema with the workflow statuses used by the admin UI (`reviewed` and `ignored`).
- Added an explicit strings status enum to keep filtering predictable.

### 1.6.5 per-file code-quality pass
- Tightened setup and AI translation REST argument validation.
- Cleaned up visual-editor segment click listeners during React cleanup.

### Extra code-quality check
- Registered the language switcher block editor script with an explicit WordPress handle so script translations can be attached reliably.
- Normalized DeepL language codes from WordPress-style underscores to provider-safe hyphenated uppercase codes.
- Added visual-editor cleanup for marked segments when the React app unmounts.

### Additional second-pass hardening
- Added a React block-editor registration script for the language switcher block.
- Registered the shared design-system and switcher styles for block editor and frontend block rendering.
- Switched admin and visual-editor mounting to `createRoot()` when available, with legacy `render()` fallback.
- Removed old admin CSS `!important` and `outline:none` compatibility overrides where the plugin scope and focus-visible fallback were already specific enough.
- Rebuilt the visual editor runtime as a React/WordPress wp.element component instead of manual DOM-rendered sidebar markup.
- Kept the public language switcher server-rendered for SEO, cache and no-JavaScript safety while retaining a small scoped dropdown controller.
- Moved the language-switcher block registration to block.json metadata with a server render callback.
- Removed arbitrary generated CSS token aliases and aligned admin, switcher and visual editor styles to one semantic design-token system.

## 1.6.5 - Staging repair hardening
- Hardened REST argument schemas and validation for settings, import, scan and translation endpoints.
- Added AI disclosure, request length limiting and an admin warning when AI translation is enabled.
- Improved visual-editor dialog semantics, live status notices, focus trapping and focus return.
- Added scoped focus-visible fallback styles for admin and visual-editor UI.

## 1.6.5 - Legacy cleanup
- Removed two unreferenced legacy helper methods from the automation queue and translation-memory concern.
- Renamed an internal CSS token from legacy wording to a neutral flag color token.
- Kept historical changelog entries intact for release traceability.

## 1.6.5 - File-level audit and runtime guard polish
- Rechecked every packaged PHP, JS, CSS, metadata and documentation file.
- Added frontend-media guards for feeds, robots and sitemap contexts.
- Aligned deactivation flow with the translator role lifecycle hook.
- Cleaned duplicate changelog entries from the 1.6.x release notes.

## 1.6.3 - Feature hardening and workflow polish
- Hardened media translation so runtime URL/image swaps only run on frontend non-default language requests.
- Aligned visual-editor review status with the translator review setting.
- Exposed workflow status labels to translator-capable users.
- Added missing workflow labels for draft, needs review and ignored statuses.
- Improved visual editor permissions with a dedicated translator capability and translator role.
- Added media translation fields for per-language image replacements, alt text and titles.
- Added deeper WooCommerce translation filters for product names, descriptions, attributes, order item names and product terms.
- Improved browser-language redirect matching with quality values and locale-aware language matching.

## 1.6.1 - Multilingual SEO upgrade
- Added per-language canonical URL support for core output, Yoast SEO and Rank Math.
- Added a multilingual XML sitemap endpoint with hreflang alternates for public posts, pages and taxonomies.
- Added admin settings for canonical and multilingual sitemap controls.
- Strengthened translated slug/URL mapping support for sitemap generation.

## 1.5.5 - Admin design-system token polish
- Centralized the remaining admin color, border, shadow and radius values into shared design tokens.
- Reduced hardcoded CSS values across admin, switcher and visual editor stylesheets.
- Kept runtime behavior unchanged; this is a visual maintainability release.

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
- Added Yoast SEO and Rank Math filters for translated SEO titles and descriptions.
- Added lightweight frontend translation performance snapshots and admin-only Server-Timing diagnostics.
- Kept WooCommerce conversion-page safeguards, existing public hooks and fail-open frontend behavior intact.

### 1.6.5 React design-system second-pass
- Prevented the React visual editor from marking its own toolbar/sidebar UI as translatable page content.
- Removed remaining component-local design token aliases from runtime CSS; admin, switcher and visual editor now reference shared design-system tokens directly.
- Repackaged with a stable plugin folder for WordPress upload/install flows.

### 1.6.5 - Visual editor hardening follow-up
- Hid the visual editor entry point when no editable non-default language exists.
- Prevented visual-editor REST saves for inactive, unknown or default languages.
