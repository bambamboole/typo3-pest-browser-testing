<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use Pest\Browser\Browsable;
use Pest\Browser\ServerManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionProperty;

class BrowserTestCase extends TestCase
{
    use Browsable;

    private static ?Typo3HttpServer $server = null;
    protected static ?ContainerInterface $container = null;
    /** @var array<class-string, bool> */
    private static array $seededClasses = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (self::$server === null) {
            self::$container = Typo3Bootstrap::ensureBooted(TestingPaths::projectRoot());

            DatabaseManager::ensureSharedConnection();
            Typo3SchemaInstaller::ensureInstalled();

            $resetter = new Typo3StateResetter();
            self::$server = new Typo3HttpServer(self::$container, $resetter);
            self::$server->start();

            // Pest's ServerManager hardcodes Laravel detection via function_exists('app_path')
            // and otherwise falls back to NullableHttpServer. We inject our driver by
            // overwriting the private $http property.
            $manager = ServerManager::instance();
            $prop = new ReflectionProperty(ServerManager::class, 'http');
            $prop->setValue($manager, self::$server);

            register_shutdown_function(static function (): void {
                self::$server?->stop();
            });
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!isset(self::$seededClasses[static::class])) {
            // Wipe everything, then call the per-class seed() override.
            // Runs once per class before the first test's transaction so the
            // seed data persists across the class while per-test changes
            // still roll back at tearDown.
            Typo3SchemaInstaller::truncateAllTables();
            $this->seed();
            self::$seededClasses[static::class] = true;
        }
        DatabaseManager::beginTransaction();
    }

    /**
     * Per-class seed hook. Override in your project's Tests\BrowserTestCase
     * (run once per class, after the DB has been truncated, before the first
     * per-test transaction). Typical contents: a `Typo3Seeder::seedPage()`
     * call for your site root, optionally followed by a
     * `Typo3ImpExpFixture::importOnce()` for richer content.
     *
     * Default is a no-op so tests can run against an empty schema.
     */
    protected function seed(): void
    {
    }

    protected function tearDown(): void
    {
        DatabaseManager::rollBack();
        self::$server?->flush();
        parent::tearDown();
    }

    /**
     * Seeds a test admin (idempotent within the test's transaction), opens the
     * backend login page, submits the form, and returns the resulting page.
     */
    protected function loginToAdmin(
        string $username = 'testadmin',
        string $password = 'testadmin-password',
    ): \Pest\Browser\Api\AwaitableWebpage {
        BackendUserSeeder::ensureTestAdmin($username, $password);

        return $this->visit('/typo3/')
            ->fill('input[name="username"]', $username)
            ->fill('input[name="p_field"]', $password)
            ->click('button[type="submit"]');
    }
}
