<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Tests;

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
        // TYPO3's Environment reads these at runtime via getenv(); the
        // host project's composer autoload set them to the host paths, so
        // we override before booting to point everything at the testbench.
        putenv('TYPO3_PATH_ROOT=' . Testbench::projectRoot() . '/public');
        putenv('TYPO3_PATH_APP=' . Testbench::projectRoot());
        putenv('TYPO3_PATH_COMPOSER_ROOT=' . Testbench::projectRoot());
        putenv('TYPO3_TESTING_PROJECT_ROOT=' . Testbench::projectRoot());
        putenv('DB_DRIVER=sqlite');
        putenv('DB_PATH=:memory:');
        putenv('BASE_DOMAIN=testbench.test');
        Typo3Bootstrap::ensureBooted(Testbench::projectRoot());
        self::$done = true;
    }
}
