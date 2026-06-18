# WooCommerce, HPOS, CWV and accessibility gates

Use this handoff when the target site includes WooCommerce, heavy page caching, Elementor/ACF content, or a client requires production-grade accessibility/performance evidence.

## WooCommerce/HPOS release gate

- Confirm WooCommerce is active on staging.
- Enable HPOS and verify no compatibility notices, fatal errors or order-storage query errors.
- Complete a test order in each enabled language where possible.
- Test cart, checkout, account, order-pay, thank-you, coupon and payment-provider redirects.
- Keep `safe_mode` enabled unless the client explicitly accepts conversion-flow output-buffer risk.
- Confirm language switching does not clear cart/session unexpectedly and does not alter stored order item data.

## Core Web Vitals gate

Measure at least one representative page with:

- translation disabled;
- translation enabled on a translated language URL;
- page cache/object cache enabled;
- page cache/object cache disabled when diagnosing issues.

Record:

- TTFB delta;
- HTML size before/after translation;
- switcher CSS/JS loading behavior;
- duplicate asset checks;
- CLS/INP issues caused by switcher position, dropdown state or late asset loading.

## Accessibility/admin UX gate

Check by keyboard and with a screen-reader spot check:

- admin tabs and form controls;
- save, import, export and error notices;
- visual editor selection and save/publish flow;
- frontend dropdown switcher;
- inline switcher;
- flag-only switcher.

Expected outcome:

- every interactive element has an accessible name;
- focus is visible;
- dropdown state is announced through `aria-expanded`/menu semantics;
- flag-only mode has text alternatives through labels/titles;
- error states are understandable without relying only on color.


## Performance/accessibility evidence

Use `docs/PERFORMANCE-ACCESSIBILITY-EVIDENCE.md` as the staging checklist for performance and accessibility checks.
