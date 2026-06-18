# Security review notes

This file documents the security expectations for maintainers and release reviewers.

## Access control

- Settings, imports, exports, scans, workflow publishing, logs, AI jobs and diagnostics must be capability-protected.
- Nonces protect browser-triggered state changes but do not replace capability checks.
- Translator-role access should remain limited to translation workflow actions and must not grant site-wide settings, AI credential or destructive maintenance access.

## REST API

- Every REST route must define `permission_callback`.
- Public routes must be intentionally read-only and documented in code.
- Privileged routes should return `WP_Error` with an appropriate status on capability, nonce, validation or dependency failure.
- REST callbacks should return arrays, `WP_REST_Response` or `WP_Error`; avoid direct output and `exit` in REST callbacks.

## Import/export

- Uploaded CSV/XLIFF files are untrusted.
- Validate extension, MIME, size and parser result.
- XLIFF/XML must reject DTD/entity declarations and parse with external entity loading disabled.
- Missing server dependencies, such as DOM/ext-xml, should produce safe errors instead of fatal errors or empty successful exports.

## AI boundary

- AI is disabled by default.
- API keys should be provided via server constants or filters. Database storage is opt-in only.
- AI calls must be administrator-initiated or explicitly workflow-initiated and review-first.
- Do not expose API keys, raw provider responses or unnecessary personal data in logs, REST responses, frontend markup or exports.

## Release security gate

Before production release, run:

- Syntax checks for PHP and JavaScript.
- Plugin Check where WP-CLI is available.
- PHPCS/WPCS where Composer tooling is available.
- Role tests for logged-out, subscriber, editor, translator role and administrator.
- Import/export edge cases with valid, malformed, empty, oversized and wrong-type files.
