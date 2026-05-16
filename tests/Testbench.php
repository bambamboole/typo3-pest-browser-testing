<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Tests;

use Bambamboole\Typo3Testing\Testing\TestingPaths;
use RuntimeException;

/**
 * Helpers for booting TYPO3 against the package-local testbench fixture.
 *
 * The testbench is a minimal TYPO3 project (config/, public/) shipped in
 * `packages/typo3-testing/workbench/`. Booting it requires a Composer
 * vendor/ at the same level — we don't commit one, so this class symlinks
 * an already-installed vendor/ in at runtime. Two layouts are supported:
 *
 *   - Standalone (`packages/typo3-testing/vendor/`) — when the package is
 *     checked out and `composer install`ed at its own root, e.g. on CI.
 *   - In-tree (host-project `vendor/`) — when the package is consumed via
 *     a Composer path repo inside a larger TYPO3 project, like in this
 *     repo's `packages/typo3-testing/` development layout.
 */
final class Testbench
{
    public static function projectRoot(): string
    {
        return TestingPaths::packageRoot() . '/workbench';
    }

    public static function ensureReady(): void
    {
        self::linkVendor();
        self::ensureVarDir();
    }

    private static function linkVendor(): void
    {
        $link = self::projectRoot() . '/vendor';
        if (is_link($link)) {
            return;
        }
        if (is_dir($link)) {
            // Refuse to nuke a real directory — only manage symlinks.
            return;
        }
        $candidates = [
            TestingPaths::packageRoot() . '/vendor',
            dirname(TestingPaths::packageRoot(), 2) . '/vendor',
        ];
        foreach ($candidates as $vendor) {
            if (is_dir($vendor) && is_file($vendor . '/autoload.php')) {
                symlink($vendor, $link);
                return;
            }
        }
        throw new RuntimeException(
            'Testbench needs a vendor/ to link in. Run `composer install` either at '
            . 'the package root or in a host project that has this package on a path repo.',
        );
    }

    private static function ensureVarDir(): void
    {
        $var = self::projectRoot() . '/var';
        if (!is_dir($var) && !@mkdir($var, 0o755, true) && !is_dir($var)) {
            throw new RuntimeException("Could not create testbench var/ at $var");
        }
    }
}
