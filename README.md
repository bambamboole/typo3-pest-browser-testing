# bambamboole/typo3-testing

In-process Pest browser testing for TYPO3 14.

Browser requests are served by an `amphp/http-server` running inside the same PHP process as the test runner. Tests share a single Doctrine connection with the HTTP request handler, so every test wraps in a transaction that rolls back at `tearDown` — no per-test DB dump or re-import.

Frontend HTML, asset routes, backend session cookies, JS module import maps, and request-token CSRF all work end-to-end against the real TYPO3 application.

## Install

```bash
composer require --dev bambamboole/typo3-testing
vendor/bin/typo3 testing:init
```

The init command is idempotent — re-run it any time, existing files are kept (use `--force` to overwrite).

## Configure

After init, edit `phpunit.xml`. The required env var is `BASE_DOMAIN` — it must match the host in your `config/sites/<id>/config.yaml`. Pick a driver:

| `DB_DRIVER` | Notes |
|---|---|
| `sqlite` (default) | `DB_PATH=:memory:` — fastest, zero external state. |
| `mysql` | Set `DB_HOST/PORT/NAME/USER/PASSWORD`. Used DB is wiped on every process. |

Both drivers run the same lifecycle: create schema once per process, truncate every table once per test class, run `seed()`, then transactionally per test.

## Write your seed

Edit `tests/BrowserTestCase.php::seed()` to lay down what every browser test in your project should assume — typically a site root matching your site config plus optional content.

```php
use Bambamboole\Typo3Testing\Testing\Typo3ImpExpFixture;
use Bambamboole\Typo3Testing\Testing\Typo3Seeder;

protected function seed(): void
{
    // Minimal: explicit site root matching rootPageId in your site config.
    Typo3Seeder::seedPage(0, 'My Site', [
        'uid' => 1, 'is_siteroot' => 1, 'slug' => '/',
    ]);

    // Or, for richer content, load an ImpExp export of your dev DB:
    //   vendor/bin/typo3 impexp:export --pid=<root> --levels=999 --table=_ALL
    //   mv public/fileadmin/user_upload/_temp_/importexport/*.xml tests/fixtures/
    Typo3ImpExpFixture::importOnce(
        Typo3ImpExpFixture::projectFixture('tests/fixtures/site.xml'),
    );
}
```

## Write tests

```php
// tests/Browser/HomepageTest.php
it('renders the homepage', function () {
    visit('/')->assertSee('My Site');
});

// tests/Browser/AdminTest.php
it('logs into the backend', function () {
    $this->loginToAdmin()->assertSee('Dashboard');
});
```

`loginToAdmin($username = 'testadmin', $password = 'testadmin-password')` seeds an admin user inside the test transaction and submits the backend login form.

## Useful helpers

- `Typo3Seeder::seedPage($pid, $title, $extras = [])` / `seedContent($pid, $header, $body, $extras = [])` — factory inserts.
- `BackendUserSeeder::ensureTestAdmin($user, $pass)` — idempotent admin upsert with TYPO3's password hasher.
- `Typo3ImpExpFixture::importOnce($path)` — loads a `.t3d` / `.xml` ImpExp export via TYPO3's native importer.
- `Typo3ImpExpFixture::projectFixture($relative)` — resolves a path relative to your project root.

## Trade-offs

In-process means **fast** (sub-second per test once warm) but also means TYPO3 state has to be reset between requests. `Typo3StateResetter` handles superglobals, `TSFE`, `BE_USER`. Some things deliberately persist across requests:

- `GeneralUtility` singletons (purging them mid-process breaks subsequent requests; TYPO3 isn't designed to be re-bootstrapped).
- `$GLOBALS['LANG']` (frontend code paths assume it's set once any backend module is loaded).

If a TYPO3 internal cache turns out to leak test data, add a targeted reset to `Typo3StateResetter`. The class is intentionally extensible.

## Requirements

- PHP 8.3+
- TYPO3 14.3+
- ext-pcntl (for amphp signal handling)
- Node + Playwright (`pest-plugin-browser` installs Chromium on first run)

## License

MIT.
