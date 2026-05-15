<?php
declare(strict_types=1);

use Bambamboole\Typo3Testing\Testing\BrowserTestCase;
use Bambamboole\Typo3Testing\Tests\Bootstrap;

// File-load-time boot: must run BEFORE BrowserTestCase::setUpBeforeClass() so
// TYPO3_TESTING_PROJECT_ROOT is pointed at the testbench (the package's tests
// run with CWD at the package root, where InstalledVersions would otherwise
// resolve the root package to the package itself instead of the testbench).
Bootstrap::ensure();

uses(BrowserTestCase::class);

/**
 * The flagship end-to-end demonstration of the package: the test runner
 * forges a real be_sessions row inside its open transaction and hands the
 * resulting JWT to Playwright as a cookie. TYPO3's HTTP request handler
 * resolves the cookie back to the same row over the shared Connection,
 * loads the user, and renders the backend. No login form is touched.
 *
 * If this test goes red, the package's core claim — that test-runner DB
 * state is visible to the in-process HTTP handler within a single
 * transaction — has regressed.
 */
it('lands in the backend without filling the login form', function () {
    // The single proof we care about: the toolbar shows the logged-in
    // user's realName, and the login form's password field is absent.
    // Together they confirm the forged session cookie was accepted and
    // TYPO3 skipped the form entirely. The specific landing module
    // varies by which backend extensions the consumer ships (the
    // package's testbench omits cms-dashboard, so TYPO3 picks the
    // first available module instead).
    $this->visitAsAdmin()
        ->assertSee('Test Admin')
        ->assertDontSee('Password')->screenshot();
});
