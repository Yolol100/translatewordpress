# Repository 13/37 — Webactueel Translate: browsertaalvoorkeur

Datum: **5 november 2026**. Baseline `main`: `01b5a9f480ade72e827c4e32c0295027e4eb07f3`.
Concept: [PR #2](https://github.com/Yolol100/translatewordpress/pull/2), branch `quality/browser-language-qvalue-20261105`. Pluginversie: `2.7.82`.

## Bevestigde fout en reparatie

**WAT-001 — ongeldige Accept-Language-gewichten (laag/middel, routingbetrouwbaarheid)**

De bestaande `BrowserLanguageRedirector::browser_language_quality()` caste de waarde na `q=` direct naar een float, waarna de waarde werd afgekapt naar het bereik 0-1. Hierdoor wordt een ongeldige waarde `q=1.2` behandeld als `1.0` (hoogste prioriteit). Ook suffixen zoals `q=0.9junk`, exponenten zoals `1e309`, meer dan drie decimalen en herhaalde `q`-parameters konden daardoor onjuist als geldige voorkeur tellen.

- **Test eerst rood:** [GitHub Actions #41973435149](https://github.com/Yolol100/translatewordpress/actions/runs/41973435149), PHP 8.1 en 8.3, beide `Invalid Accept-Language quality (above one): expected 0.000, got 1.0`.
- **Fix in bestaande parser:** vereis de exacte RFC9110 `qvalue`-vorm, drie decimalen maximaal, `q=1.000` toegestaan, ongeldig/duplicaat/onbekende parameter => gewicht `0.0`; compliant headers blijven onveranderd. Geen frontend URL-, cookie-, vertaal- of AI-endpointgedrag buiten deze methode aangepast.
- **Eerste groen:** [CI #41973524699](https://github.com/Yolol100/translatewordpress/actions/runs/41973524699), PHP 8.1 en 8.3. Na het auditlog de exacte definitieve head opnieuw controleren.

## Publieke referenties en waarom

1. [RFC 9110 §12.4.2, §12.5.4](https://www.rfc-editor.org/rfc/rfc9110.html): `Accept-Language` gebruikt `qvalue = 0[.0–3 cijfers] / 1[.max 3 nullen]`; ongeldige waarden niet normaliseren alsof zij valide zijn.
2. [MDN Accept-Language](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Accept-Language): correcte sortering op q-gewicht en voorbeeldheaders.
3. [10up WP Scaffold](https://github.com/10up/wp-scaffold): test eerst het bestaande gedrag; pas de oorspronkelijke parser aan en veranker in CI.
4. [WordPress HTTP/redirect API](https://developer.wordpress.org/reference/functions/wp_safe_redirect/): browserredirects moeten met geldige doelen en gecontroleerde instellingen werken. De PR verandert geen redirectdoelen.

## Niet aangetoond / releasegrenzen

- Regressietest oefent de **echte private q-parser** via `ReflectionMethod`, niet een live browserredirect. Er zijn geen echte browser-, taal-/cookie- of SEO-hreflang-tests uitgevoerd.
- De bestaande CI controleert PHP-syntaxis en nu q-parsing op twee PHP-versies, **niet** installatie met WordPress/MySQL, sitepagina's, WPML/Polylang, Elementor, WooCommerce of CDN/cache.
- URL-mapping, import/export CSV/XLIFF, wisseling van talen en AI-gegevensverkeer vereisen aparte integratie- en stagingcontroles.
- PHP/JS-generated build bestanden, vertalingen en compatibility code blijven intact. Geen schijnbare duplicaten zonder runtimebewijs verwijderd.
- Geen API, echte cookies, externe vertaalprovider, productiepagina, optie of gebruiker gemuteerd. Versienummer en release ZIP blijven onveranderd. Sluit de concept-PR als rollback; geen merge/release zonder eigenaar- en onafhankelijke staging-QA.
