# Staging testplan

## Rollen
Test minimaal als administrator, editor, wat_translator, subscriber en logged-out bezoeker.

## REST en rechten
- Administrator: instellingen, talen, scan, CSV/XLIFF, AI usage en workflow.
- wat_translator: vertaalstrings en workflow lezen/schrijven, geen settings.
- editor: geen vertaalrechten tenzij filter bewust is ingeschakeld.
- subscriber/logged-out: geen admin REST toegang.
- Controleer verlopen of ontbrekende REST nonces.

## Import/export
- CSV preview, import en export met geldig bestand.
- Ongeldig MIME-type en te groot bestand.
- XLIFF import/export met foutieve XML en ontbrekende velden.

## WooCommerce
- Productpagina, cart, coupon, checkout, order-pay, account en order-e-mail.
- Test met safe_mode aan en uit.
- Controleer dat conversiepagina's niet breken door output buffering.

## Builders/cache
- Elementor-pagina met taalkiezer.
- Cacheplugin actief met taalwissel en hreflang.
- Controleer CLS/INP/TTFB na activeren van frontendvertaling.

## SEO
- hreflang broncode.
- Canonicals per taal.
- Sitemap via /?wat_language_sitemap=1.
- Yoast of Rank Math titel/omschrijving wanneer actief.

## Release gates
- PHP lint.
- Zip integrity.
- Plugin Check.
- PHPCS/WPCS.
- Rollback via vorige pluginzip en databasebackup.
