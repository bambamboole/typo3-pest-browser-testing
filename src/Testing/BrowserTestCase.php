<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Browsable;
use Pest\Browser\ServerManager;
use Pest\Browser\Support\ComputeUrl;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

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
            // and otherwise falls back to NullableHttpServer. The fork exposes setHttp() so
            // we can register our TYPO3-backed driver instead.
            ServerManager::instance()->setHttp(self::$server);

            register_shutdown_function(static function (): void {
                self::$server?->stop();
            });
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!isset(self::$seededClasses[static::class])) {
            Typo3SchemaInstaller::truncateAllTables();
            $this->seed();
            self::$seededClasses[static::class] = true;
        }
        DatabaseManager::beginTransaction();
    }

    /** Per-class seed hook. Override in your project's Tests\BrowserTestCase. */
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
    ): AwaitableWebpage {
        BackendUserSeeder::ensureTestAdmin($username, $password);

        return $this->visit('/typo3/')
            ->fill('input[name="username"]', $username)
            ->fill('input[name="p_field"]', $password)
            ->click('button[type="submit"]');
    }

    /**
     * Drops the user into the backend with a pre-fixated session — no form
     * fill. Seeds the user, persists a real be_sessions row via TYPO3's own
     * UserSessionManager, then hands the resulting JWT to Playwright as an
     * HttpOnly cookie before navigating.
     *
     * The same-origin Referer is required so TYPO3's ReferrerEnforcer treats
     * the request as SAME_ORIGIN and skips the "referrer-refresh" shim it
     * would otherwise emit for first-hit backend navigations. Without it,
     * goto() returns on the tiny JS-driven shim page that races the assertion.
     */
    protected function visitAsAdmin(
        string $path = '/typo3/',
        string $username = 'testadmin',
        string $password = 'testadmin-password',
    ): PendingAwaitablePage {
        $uid = BackendUserSeeder::ensureTestAdmin($username, $password);
        $jwt = BackendSessionForge::forUser($uid);
        $url = ComputeUrl::from($path);

        return $this->visit($path, ['referer' => $url])
            ->withCookie('be_typo_user', $jwt, [
                'httpOnly' => true,
                'secure'   => false,
                'sameSite' => 'Strict',
            ]);
    }
}
