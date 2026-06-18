# Security and stability gates

This file complements `docs/SECURITY-REVIEW.md` with release evidence requirements.

## Privileged actions

For every route/action that reads or mutates privileged data, record:

- action name or REST route;
- allowed capability/role;
- expected success role;
- expected failure roles;
- nonce behavior where browser-triggered;
- sanitization/validation used for primary inputs;
- output escaping/download headers.

## Compatibility smoke tests

Minimum release smoke test:

- clean activation;
- deactivate/reactivate;
- upgrade from previous stable ZIP;
- rollback to previous stable ZIP;
- uninstall with retention;
- uninstall with explicit data deletion;
- WP_DEBUG enabled staging visit to admin, frontend and REST routes;
- server without DOM/ext-xml for safe fallback;
- server with DOM/ext-xml for frontend translation/XLIFF behavior.

## Package rule

The ZIP should ship only runtime code, build assets, language files, license, readme, uninstall and maintainership docs. Do not ship local review logs, patch files, source maps, `node_modules`, `.git`, editor metadata or CI-only fixtures.
