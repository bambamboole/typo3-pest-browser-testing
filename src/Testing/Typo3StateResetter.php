<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

final class Typo3StateResetter
{
    /** @var array<string,mixed> */
    private array $baselineServer;

    public function __construct()
    {
        $this->baselineServer = $_SERVER;
    }

    public function reset(): void
    {
        // Per-request superglobals reset to the process baseline.
        $_GET = $_POST = $_COOKIE = $_REQUEST = $_FILES = [];
        $_SERVER = $this->baselineServer;

        // Frontend, backend, and language globals.
        // $GLOBALS['LANG'] is deliberately NOT unset here: once any earlier
        // request loaded cms-backend's JavaScript modules (e.g. after a
        // login), the import map still emits VIRTUAL:labels/ entries on
        // subsequent frontend pages, and JavaScriptLabelImportMapEntryResolver
        // dereferences $GLOBALS['LANG']->getLocale() unconditionally. Leaving
        // a stale LANG object in place is safer than null — the resolver only
        // reads its locale name.
        unset($GLOBALS['TSFE'], $GLOBALS['BE_USER']);

        // GeneralUtility singleton cache is intentionally NOT purged here:
        // doing so breaks the next request because Bootstrap::init seeded
        // services that TYPO3 expects to find on every request. Test
        // isolation comes from rolling back transactions, not from purging
        // singletons. If a specific singleton turns out to leak across
        // tests, target it surgically here.
    }
}
