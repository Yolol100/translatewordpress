# Webactueel Translate v2.4.2 - Design, UX en werking QA

## Scope

Gecontroleerd op basis van de pluginbestanden in deze releasebuild. Dit is een statische audit met lokale syntax- en pakketcontroles. Runtimegedrag in een echte WordPress-installatie is niet volledig verifieerbaar vanuit de zip alleen.

## Verdict

**GO AFTER STAGING TESTS**

De plugin is releasewaardig als kandidaatbuild, maar geen definitieve productie-GO zonder WordPress-stagingtest met echte rollen, REST-nonces, imports/exports, frontend rendering, WooCommerce-flows en Plugin Check.

## UX/design-audit

### Bevestigd verbeterd

- De admin-interface heeft een duidelijke fallback wanneer de React/admin bundle niet mount.
- De fallback bevat nu directe acties voor instellingen, REST-health en visuele editor.
- De workflowtab heeft duidelijke statusmetingen: score, ontbrekend, review, verouderd, contextconflicten en workflowtaken.
- De native workflow UI gebruikt WordPress-componenten, badges, panels, responsive grid en live statusmeldingen.

### Waarschijnlijk goed

- De UX is bruikbaar voor agencies omdat vertaalwerk is gescheiden van beheerinstellingen.
- De translator role krijgt geen settingsrechten, waardoor de interface veiliger te delegeren is.
- De onboarding/recommendations endpoint geeft goede bouwstenen voor een startflow.

### Handmatig testen nodig

- Of alle tabs in de React-admin logisch openen na browser refresh en localStorage/tab-sync.
- Of foutmeldingen uit REST/API failures in de UI voldoende concreet zijn voor niet-technische gebruikers.
- Of de visuele editor met Elementor, WooCommerce en cacheplugins consistent werkt.
- Of keyboard focus, contrast en statusmeldingen goed zijn in browser/screen-reader spotchecks.

## Technische werking-checklist

| Onderdeel | Status | Opmerking |
|---|---:|---|
| PHP syntax | OK | Alle PHP-bestanden linten zonder syntaxfout. |
| REST permission callbacks | OK | Alle zichtbare `register_rest_route` blokken hebben permission callbacks. |
| Admin asset scope | OK | Admin assets worden alleen op plugin-adminpagina geladen. |
| Release zip | OK | Zip bevat alleen de pluginmap, geen node_modules, logs, caches of testoutput. |
| Version metadata | OK | Header, stable tag, WAT_VERSION en POT zijn naar 2.4.2 gezet. |
| Direct DB reads | OK met context | Plugin-owned custom tables; PHPCS-context toegevoegd bij QA endpoints. |
| Rollen/capabilities | Handmatig testen nodig | Test admin, editor, wat_translator, subscriber en logged-out. |
| Import/export | Handmatig testen nodig | Test CSV/XLIFF happy path, fout bestand, verlopen preview-token en grote import. |
| WooCommerce | Handmatig testen nodig | Test cart, checkout, order-pay, account, coupons en e-mails. |

## Kan weg

Geen bestanden zijn in deze statische controle veilig te verwijderen.

### Handmatig controleren voordat je ooit opruimt

- `docs/COMPETITOR-COMPARISON.md`, `docs/ROADMAP.md`, `docs/WORDPRESS-ORG-COPY.md`: nuttig voor product/release, maar mogelijk niet nodig in een WordPress.org distributiezip.
- `QA-REPORT.txt` en `DESIGN-QA-REPORT.md`: nuttig voor agency/staging handoff; mogelijk verwijderen uit publieke distributie als je een strakke WordPress.org package wilt.
- `build/admin/workflow-tab.js`: alleen verwijderen als bewezen is dat de huidige admin bundle dit niet meer enqueue't of dynamisch verwacht.

## Bewust behouden

- `SECURITY.md` en `CHANGELOG.md`: nuttig voor releasevertrouwen en onderhoud.
- `languages/*.pot`: nodig voor vertaalbaarheid.
- `build/*` assets: nodig voor admin, frontend switcher en visual editor.
- Legacy REST namespace in de hoofdservice: behouden voor backward compatibility.
- Custom database queries: behouden omdat de plugin eigen tabellen beheert.

## Uitgevoerde veilige fixes

- Admin fallback verbeterd met duidelijke actieknoppen en betere fouttekst.
- Kleine fallback CSS toegevoegd binnen bestaande admin CSS-scope.
- Versie naar 2.4.2 gezet in header, stable tag, WAT_VERSION, POT, changelog en QA-rapport.
- DESIGN-QA-REPORT.md toegevoegd.
- PHPCS-contextcomment toegevoegd bij plugin-owned direct database reads in QA endpoints.

## Staging testplan

1. Activeer plugin op schone staging met WP_DEBUG aan.
2. Open adminpagina als administrator en controleer alle tabs.
3. Test rolrechten als administrator, editor, `wat_translator`, subscriber en logged-out.
4. Test REST endpoints met geldige nonce, ongeldige nonce en zonder login.
5. Draai scan, CSV export, CSV preview/import, XLIFF export/import.
6. Test AI uitgeschakeld, AI zonder key, AI met serverconstante/filter en provider timeout.
7. Test frontendvertaling op normale pagina, Elementor-pagina en WooCommerce flows.
8. Draai Plugin Check en PHPCS/WPCS in een echte WordPress-testomgeving.
9. Controleer browserconsole, PHP error log en netwerkfouten.
10. Test uninstall met en zonder data-delete instelling.

## Rollback

Gebruik de vorige zip `translatewordpress-main-v2.4.0-executed.zip` als rollbackbuild. De v2.4.2 wijzigingen zijn beperkt tot fallback-UX, documentatie, versievelden en PHPCS-context; er is geen datamigratie toegevoegd.
