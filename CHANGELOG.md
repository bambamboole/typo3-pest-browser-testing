# Changelog

## 0.1.0 — unreleased

Initial release. In-process amphp-based Pest browser testing for TYPO3 14.

- `Typo3HttpServer` — implements `Pest\Browser\Contracts\HttpServer`, serves PSR-7 requests through `TYPO3\CMS\Core\Http\Application` on a dynamic local port. Rewrites Set-Cookie domain, Location header, and HTML body absolute URLs from the configured `BASE_DOMAIN` back to the local origin so Playwright never bounces out to the real DNS host.
- `Typo3SchemaInstaller` — runs `SchemaMigrator::install(createOnly: true)` once per process, then `truncateAllTables()` once per class so MySQL and SQLite start from the same empty state.
- `Typo3Seeder::seedPage` / `seedContent` — factory inserts.
- `BackendUserSeeder::ensureTestAdmin` — idempotent admin via TYPO3's password hasher.
- `Typo3ImpExpFixture::importOnce` — programmatic ImpExp import; handles BE_USER hydration, force-uid preservation, and disables site-config-on-disk writing.
- `StaticFileServer` — serves `public/_assets/`, `public/fileadmin/`, etc. directly instead of dispatching to TYPO3 for every CSS/JS/image request.
- `testing:init` CLI command — scaffolds `phpunit.xml`, `tests/Pest.php`, `tests/BrowserTestCase.php`, and the `tests/{Unit,Feature,Browser,fixtures}/` directory structure. Idempotent.
- `DB_DRIVER=sqlite` (default) with `DB_PATH=:memory:` for fastest local runs; `DB_DRIVER=mysql` for full fidelity.
