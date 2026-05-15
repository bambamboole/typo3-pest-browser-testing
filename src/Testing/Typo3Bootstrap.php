<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use Composer\Autoload\ClassLoader;
use Psr\Container\ContainerInterface;
use RuntimeException;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;

final class Typo3Bootstrap
{
    private static ?ContainerInterface $container = null;
    private static bool $environmentInitialized = false;

    public static function ensureBooted(string $projectRoot): ContainerInterface
    {
        if (self::$container !== null) {
            return self::$container;
        }

        $classLoader = require $projectRoot . '/vendor/autoload.php';
        if (! $classLoader instanceof ClassLoader) {
            throw new RuntimeException('vendor/autoload.php did not return a ClassLoader');
        }

        self::applyTestDatabaseConfig();

        if (! self::$environmentInitialized) {
            SystemEnvironmentBuilder::run();
            self::$environmentInitialized = true;
        }

        $container = Bootstrap::init($classLoader);

        // Bootstrap re-loads config/system/settings.php which overwrites our
        // DB override. Re-apply it now so ConnectionPool reads the test config.
        self::applyTestDatabaseConfig();

        return self::$container = $container;
    }

    public static function env(string $key, string $default = ''): string
    {
        $fromEnv = getenv($key);
        if ($fromEnv !== false && $fromEnv !== '') {
            return $fromEnv;
        }
        return $default;
    }

    private static function applyTestDatabaseConfig(): void
    {
        $driver = self::env('DB_DRIVER', 'mysql');
        if ($driver === 'sqlite') {
            $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default'] = [
                'driver' => 'pdo_sqlite',
                'path'   => self::env('DB_PATH', ':memory:'),
            ];
            return;
        }
        $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default'] = [
            'driver'   => 'pdo_mysql',
            'host'     => self::env('DB_HOST', '127.0.0.1'),
            'port'     => (int) self::env('DB_PORT', '3306'),
            'dbname'   => self::env('DB_NAME', 'typo3_test'),
            'user'     => self::env('DB_USER', 'root'),
            'password' => self::env('DB_PASSWORD', ''),
            'charset'  => 'utf8mb4',
        ];
    }
}
