# bambamboole/typo3-testing

In-process Pest browser testing for TYPO3 14.

Browser requests are served by an `amphp/http-server` running inside the same PHP process as the test runner. Tests share a single Doctrine connection with the HTTP request handler, so every test wraps in a transaction that rolls back at `tearDown` — no per-test DB dump or re-import.

Frontend HTML, asset routes, backend session cookies, JS module import maps, and request-token CSRF all work end-to-end against the real TYPO3 application.

## Requirements

- PHP 8.3+
- TYPO3 14.3+
- ext-pcntl (for amphp signal handling)
- Node + Playwright (`pest-plugin-browser` installs Chromium on first run)

## Install

```bash
composer require --dev bambamboole/typo3-testing
vendor/bin/typo3 testing:init
```

`testing:init` is idempotent — re-run it any time, existing files are kept (use `--force` to overwrite). It scaffolds `phpunit.xml`, `tests/Pest.php`, `tests/BrowserTestCase.php`, a smoke test, and the `tests/Browser|Feature|Unit|fixtures/` directories.

## Using it in a TYPO3 project

This is the typical case: you have a real site with `config/sites/<id>/`, you've run `composer install`, and you want browser tests for the live application.

```bash
composer require --dev bambamboole/typo3-testing
vendor/bin/typo3 testing:init
```

Edit `phpunit.xml` — at minimum set `BASE_DOMAIN` to match the host in `config/sites/<id>/config.yaml`. Pick a database driver:

| `DB_DRIVER` | Notes |
|---|---|
| `sqlite` (default) | `DB_PATH=:memory:` — fastest, zero external state. |
| `mysql` | Set `DB_HOST/PORT/NAME/USER/PASSWORD`. Used DB is wiped on every process. |

Edit `tests/BrowserTestCase.php::seed()` to lay down what every browser test should assume — typically a site root matching `rootPageId` in your site config, optionally followed by an ImpExp fixture of your dev content:

```php
use Bambamboole\Typo3Testing\Testing\Typo3ImpExpFixture;
use Bambamboole\Typo3Testing\Testing\Typo3Seeder;

protected function seed(): void
{
    Typo3Seeder::seedPage(0, 'My Site', [
        'uid' => 1, 'is_siteroot' => 1, 'slug' => '/',
    ]);

    Typo3ImpExpFixture::importOnce(
        Typo3ImpExpFixture::projectFixture('tests/fixtures/site.xml'),
    );
}
```

Generate the fixture by exporting your dev DB:

```bash
vendor/bin/typo3 impexp:export --pid=<root> --levels=999 --table=_ALL
mv public/fileadmin/user_upload/_temp_/importexport/*.xml tests/fixtures/site.xml
```

Run the suite:

```bash
vendor/bin/pest --testsuite=browser
```

## Using it while building a TYPO3 extension

Extensions are their own repos and don't ship a TYPO3 install. The convention is a `.Build/` (or similarly named) directory inside your extension that hosts a minimal TYPO3 project, with your extension symlinked or path-required into its `vendor/`. The package treats that as the project root.

```
my-extension/
├── Classes/
├── Configuration/
├── Tests/
├── composer.json
└── .Build/                      ← TYPO3 testbench
    ├── composer.json
    ├── config/sites/main/config.yaml
    ├── public/index.php
    └── (composer install here)
```

```bash
# Inside .Build/
composer require --dev bambamboole/typo3-testing
vendor/bin/typo3 testing:init
```

Then run tests from the extension root pointing at the testbench's `phpunit.xml`:

```bash
vendor/bin/pest -c .Build/phpunit.xml --testsuite=browser
```

Everything else — seeding, the `BrowserTestCase`, backend login helpers — is identical to the in-project case.

## Asset publishing

TYPO3 emits backend CSS/JS from `public/_assets/<hash>/...`. Those paths are populated by `cms-composer-installers` during `composer install`, so a normal project workflow needs no extra action. If you ever see the backend render unstyled, re-publish:

```bash
vendor/bin/typo3 asset:publish
```

## Write tests

```php
// tests/Browser/HomepageTest.php
it('renders the homepage', function () {
    visit('/')->assertSee('My Site');
});

// tests/Browser/AdminTest.php — log in by submitting the form
it('logs into the backend', function () {
    $this->loginToAdmin()->assertSee('Dashboard');
});

// tests/Browser/AdminTest.php — log in by pre-forging the session
it('jumps straight to the backend', function () {
    $this->visitAsAdmin()->assertSee('Test Admin');
});
```

Two backend login styles ship with the package:

- **`loginToAdmin($user = 'testadmin', $pw = 'testadmin-password')`** — seeds an admin user inside the test transaction and submits the form on `/typo3/`. Closest to a real user flow.
- **`visitAsAdmin($path = '/typo3/', ...)`** — seeds the user, fixates a real `be_sessions` row via TYPO3's `UserSessionManager`, and hands the resulting JWT to Playwright as an `HttpOnly` cookie before navigating. No form fill, ~10× faster.

Both leave clean state — the user and session disappear with the rollback at `tearDown`.

## Useful helpers

- `Typo3Seeder::seedPage($pid, $title, $extras = [])` / `seedContent($pid, $header, $body, $extras = [])` — factory inserts.
- `BackendUserSeeder::ensureTestAdmin($user, $pass)` — idempotent admin upsert using TYPO3's password hasher.
- `BackendSessionForge::forUser($uid)` — returns a `be_typo_user` JWT for a seeded user. Powers `visitAsAdmin()`; useful directly if you're driving the cookie yourself.
- `Typo3ImpExpFixture::importOnce($path)` — loads a `.t3d` / `.xml` ImpExp export via TYPO3's native importer.
- `Typo3ImpExpFixture::projectFixture($relative)` — resolves a path relative to your project root.
- `DatabaseManager::connection()` — the shared Doctrine `Connection`. Same instance as `(new ConnectionPool())->getConnectionByName('Default')`, so any TYPO3-internal write through `ConnectionPool` lands in the same transaction as your test's writes.

## Trade-offs

In-process means **fast** (sub-second per test once warm) but also means TYPO3 state has to be reset between requests. `Typo3StateResetter` handles superglobals, `TSFE`, `BE_USER`. Some things deliberately persist across requests:

- `GeneralUtility` singletons — purging them mid-process breaks subsequent requests; TYPO3 isn't designed to be re-bootstrapped.
- `$GLOBALS['LANG']` — frontend code paths assume it's set once any backend module is loaded.

If a TYPO3 internal cache turns out to leak test data, add a targeted reset to `Typo3StateResetter`. The class is intentionally extensible.

## License

MIT.
