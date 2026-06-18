# Performance and accessibility evidence gate

This file records the performance and accessibility checks for release validation. It is intentionally practical: runtime results still need to be attached by the staging owner for the target stack.

## Performance/CWV safeguards

- Frontend switcher assets are registered but only enqueued when floating mode is enabled, the shortcode/block is present, an active widget contains the switcher, or the explicit `wat_should_enqueue_switcher_assets` filter returns true.
- The frontend switcher script is loaded in the footer with the WordPress `defer` strategy.
- The visual editor script is only loaded for users who can translate and only when `wat_visual_editor=1` is present; it is also loaded in the footer with the `defer` strategy.
- The switcher JavaScript has a single-run guard to prevent duplicate binding when cache/optimization plugins concatenate or replay scripts.
- Frontend output translation remains guarded by safe request checks and skips admin, AJAX, REST, cron, non-GET requests, feeds, robots, sitemaps and conversion-critical WooCommerce pages when safe mode is enabled.
- Frontend performance snapshot logging is opt-in so normal translated page views do not write a diagnostic option by default.

## Performance/CWV runtime evidence required

Record these values before production release:

| Page | Translation off TTFB | Translation on TTFB | HTML size delta | Switcher assets expected? | Duplicate assets? | Notes |
|---|---:|---:|---:|---|---|---|
| Home |  |  |  |  |  |  |
| Page/post |  |  |  |  |  |  |
| Product/cart/checkout if WooCommerce is active |  |  |  |  |  |  |

## Accessibility/admin UX safeguards

- The frontend dropdown uses a real `<button>` with `aria-haspopup`, `aria-controls`, `aria-expanded`, `aria-describedby` and a hidden usage hint.
- Dropdown menu items use `role="menuitem"`; the menu exposes vertical orientation.
- Keyboard support covers Enter, Space, Escape, Arrow Up/Down, Home and End.
- Focus is visibly styled on frontend switcher links/buttons and admin controls.
- Flag chips are decorative (`aria-hidden="true"`), while language names/codes and flag-only labels provide the accessible name.
- Reduced-motion preferences are respected in frontend and admin CSS.

## Accessibility/admin UX runtime evidence required

Record these checks before production release:

| Surface | Keyboard path pass? | Focus visible? | Screen-reader name clear? | Console clean? | Tool used | Notes |
|---|---|---|---|---|---|---|
| Admin settings tabs |  |  |  |  |  |  |
| Import/export screens |  |  |  |  |  |  |
| Visual editor |  |  |  |  |  |  |
| Frontend dropdown switcher |  |  |  |  |  |  |
| Frontend inline/flag switcher |  |  |  |  |  |  |

## Release policy

Do not treat this document as runtime proof by itself. Mark performance and accessibility as release-ready only after the target staging environment records the matching measurements.
