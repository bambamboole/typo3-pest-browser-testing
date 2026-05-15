<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use ReflectionClass;
use RuntimeException;

final class TestingPaths
{
    private static ?string $projectRoot = null;
    private static ?string $packageRoot = null;

    /**
     * The consumer's project root — the directory that owns the root
     * composer.json. Override with TYPO3_TESTING_PROJECT_ROOT when
     * autodetection fails (unusual symlink layouts, monorepos with
     * non-standard vendor paths).
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

    public static function packageRoot(): string
    {
        return self::$packageRoot ??= dirname(__DIR__, 2);
    }
}
