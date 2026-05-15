<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Browsable;
use Pest\Browser\Enums\Device;
use Pest\Browser\Playwright\Client;
use Pest\Browser\Playwright\Context;
use Pest\Browser\Playwright\InitScript;
use Pest\Browser\Playwright\Playwright;
use Pest\Browser\ServerManager;
use Pest\Browser\Support\ComputeUrl;
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
     * pest-plugin-browser's PendingAwaitablePage is final and its public API
     * has no cookie hook, so we replicate buildAwaitablePage() locally and
     * reflect into Pest's private Context::$guid to call Playwright's
     * Browser.Context.addCookies directly. Same pattern as the
     * ServerManager::$http injection in setUpBeforeClass().
     */
    protected function visitAsAdmin(
        string $path = '/typo3/',
        string $username = 'testadmin',
        string $password = 'testadmin-password',
    ): AwaitableWebpage {
        $uid = BackendUserSeeder::ensureTestAdmin($username, $password);
        $jwt = BackendSessionForge::forUser($uid);

        $browser = Playwright::browser(Playwright::defaultBrowserType())->launch();
        $context = $browser->newContext([
            'locale'      => 'en-US',
            'timezoneId'  => 'UTC',
            'colorScheme' => Playwright::defaultColorScheme()->value,
            ...Device::DESKTOP->context(),
        ]);
        $context->addInitScript(InitScript::get());

        $url = ComputeUrl::from($path);
        $host = parse_url($url, PHP_URL_HOST) ?: '127.0.0.1';

        $guid = (new ReflectionProperty(Context::class, 'guid'))->getValue($context);
        $response = Client::instance()->execute($guid, 'addCookies', [
            'cookies' => [[
                'name'     => 'be_typo_user',
                'value'    => $jwt,
                'domain'   => $host,
                'path'     => '/',
                'httpOnly' => true,
                'secure'   => false,
                'sameSite' => 'Strict',
            ]],
        ]);
        foreach ($response as $_) {
            // Consume the Generator so the message round-trips with Playwright.
        }

        // Send a same-origin Referer so TYPO3's ReferrerEnforcer treats the
        // request as SAME_ORIGIN and skips the "referrer-refresh" shim it
        // would otherwise emit for first-hit backend navigations. Without
        // this, goto() returns on the tiny shim page (a JS-driven redirect
        // that races the assertion).
        return new AwaitableWebpage(
            $context->newPage()->goto($url, ['referer' => $url]),
            $url,
        );
    }
}
