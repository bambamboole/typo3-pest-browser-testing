<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use ReflectionClass;
use RuntimeException;

/**
 * Resolves filesystem paths the test harness needs at boot, before TYPO3's
 * own Environment::getProjectPath() is available.
 *
 * The consumer's project root is the directory that owns the root composer.json
 * (containing this package as a require / require-dev dependency). It is the
 * same directory TYPO3 will eventually use as its project path.
 */
final class TestingPaths
{
    private static ?string $projectRoot = null;
    private static ?string $packageRoot = null;

    /**
     * Override with TYPO3_TESTING_PROJECT_ROOT for setups where autodetection
     * fails (e.g. unusual symlink layouts, monorepos with non-standard
     * vendor paths).
     */
    public static function projectRoot(): string
    {
        if (self::$projectRoot !== null) {
            return self::$projectRoot;
        }
        $env = getenv('TYPO3_TESTING_PROJECT_ROOT');
        if (is_string($env) && $env !== '') {
            return self::$projectRoot = rtrim($env, '/');
        }
        if (class_exists(InstalledVersions::class)) {
            $root = InstalledVersions::getRootPackage()['install_path'] ?? null;
            if (is_string($root) && $root !== '') {
                $real = realpath($root);
                return self::$projectRoot = $real !== false ? $real : rtrim($root, '/');
            }
        }
        // Fallback: ClassLoader lives at <project>/vendor/composer/ClassLoader.php
        $reflection = new ReflectionClass(ClassLoader::class);
        $file = $reflection->getFileName();
        if ($file === false) {
            throw new RuntimeException('Unable to determine project root (Composer ClassLoader not on disk).');
        }
        return self::$projectRoot = dirname($file, 3);
    }

    /**
     * This package's own root (where its composer.json lives). Used to locate
     * the bundled stubs/ directory and similar package-internal resources.
     */
    public static function packageRoot(): string
    {
        return self::$packageRoot ??= dirname(__DIR__, 2);
    }
}
