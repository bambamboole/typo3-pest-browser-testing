<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Tests;

use Bambamboole\Typo3Testing\Testing\Typo3AssetPublisher;
use Bambamboole\Typo3Testing\Testing\Typo3Bootstrap;

/**
 * Idempotent process-wide boot for the package's integration tests. Each
 * test file calls Bootstrap::ensure() in its beforeAll hook; the first call
 * configures env vars + boots TYPO3 against the local testbench, subsequent
 * calls are no-ops.
 */
final class Bootstrap
{
    private static bool $done = false;

    public static function ensure(): void
    {
        if (self::$done) {
            return;
        }
        Testbench::ensureReady();
        // These four redirect TYPO3's Environment + the package's path
        // resolution at the testbench. They have to be set here, not in
        // phpunit.xml, because they're computed from the package's
        // filesystem location at runtime. Static config (DB_DRIVER,
        // DB_PATH, BASE_DOMAIN) lives in phpunit.xml.
        putenv('TYPO3_PATH_ROOT=' . Testbench::projectRoot() . '/public');
        putenv('TYPO3_PATH_APP=' . Testbench::projectRoot());
        putenv('TYPO3_PATH_COMPOSER_ROOT=' . Testbench::projectRoot());
        putenv('TYPO3_TESTING_PROJECT_ROOT=' . Testbench::projectRoot());
        $container = Typo3Bootstrap::ensureBooted(Testbench::projectRoot());
        // The testbench has no composer install of its own — its vendor/ is
        // a symlink — so neither cms-composer-installers nor bin/typo3
        // asset:publish has populated public/_assets/. Do it here once per
        // process. Consumer projects rely on the standard paths and don't
        // need this.
        Typo3AssetPublisher::ensurePublished($container);
        self::$done = true;
    }
}
