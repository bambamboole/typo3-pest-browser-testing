<?php
declare(strict_types=1);

use Bambamboole\Typo3Testing\Tests\Bootstrap;
use Bambamboole\Typo3Testing\Tests\Testbench;

beforeAll(fn() => Bootstrap::ensure());

use Bambamboole\Typo3Testing\Testing\BackendUserSeeder;
use Bambamboole\Typo3Testing\Testing\DatabaseManager;
use Bambamboole\Typo3Testing\Testing\Typo3ImpExpFixture;
use Bambamboole\Typo3Testing\Testing\Typo3SchemaInstaller;
use Bambamboole\Typo3Testing\Testing\Typo3Seeder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Impexp\Export;

beforeEach(function () {
    DatabaseManager::ensureSharedConnection();
    Typo3SchemaInstaller::ensureInstalled();
    Typo3SchemaInstaller::truncateAllTables();
    Typo3ImpExpFixture::forgetAll();

    // TYPO3's GeneralUtility::getFileAbsFileName rejects paths outside the
    // project root, so the fixture must live under the testbench (which is
    // the TYPO3 project for these tests). var/ is gitignored.
    $varDir = Testbench::projectRoot() . '/var';
    is_dir($varDir) || mkdir($varDir, 0o755, true);
    $this->fixture = $varDir . '/typo3-testing-impexp-' . bin2hex(random_bytes(4)) . '.xml';

    // Re-use the package's own Typo3Seeder to build a known set of rows,
    // then export through TYPO3's native impexp:export. We can't easily
    // build a hand-crafted .xml here because the TYPO3 format is internal,
    // so we round-trip: seed, export, truncate, import, assert.
    $pid = Typo3Seeder::seedPage(0, 'Imported Root', ['uid' => 100, 'is_siteroot' => 1]);
    Typo3Seeder::seedContent(100, 'Imported Header', 'Imported body');

    // Export (like Import) drives DataHandler, which insists on a BE_USER
    // global plus a LANG service. Both come from the same factory chain.
    $uid = BackendUserSeeder::ensureTestAdmin();
    $be = GeneralUtility::makeInstance(BackendUserAuthentication::class);
    $be->setBeUserByUid($uid);
    $be->backendCheckLogin();
    $GLOBALS['BE_USER'] = $be;
    $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
        ->createFromUserPreferences($be);

    $exporter = GeneralUtility::makeInstance(Export::class);
    $exporter->setPid($pid);
    $exporter->setLevels(999);
    $exporter->setTables(['_ALL']);
    $exporter->setExportFileName(basename($this->fixture, '.xml'));
    $exporter->setExportFileType('xml');
    $exporter->process();
    // For XML output, render() returns the XML content directly — no need
    // to bounce through TYPO3's FAL (which our testbench DB doesn't have
    // sys_file_storage records for).
    file_put_contents($this->fixture, $exporter->render());

    // Wipe again so the import is the only thing populating the DB.
    Typo3SchemaInstaller::truncateAllTables();
    Typo3ImpExpFixture::forgetAll();
});

afterEach(function () {
    @unlink($this->fixture);
});

it('imports pages and tt_content from an XML fixture', function () {
    Typo3ImpExpFixture::importOnce($this->fixture);

    $conn = DatabaseManager::connection();
    $pages = (int) $conn->fetchOne('SELECT COUNT(*) FROM pages');
    $content = (int) $conn->fetchOne('SELECT COUNT(*) FROM tt_content');

    expect($pages)->toBe(1);
    expect($content)->toBe(1);

    $title = $conn->fetchOne('SELECT title FROM pages WHERE uid = 100');
    expect($title)->toBe('Imported Root');
});

it('is idempotent: a second importOnce is a no-op', function () {
    Typo3ImpExpFixture::importOnce($this->fixture);
    Typo3ImpExpFixture::importOnce($this->fixture);

    $count = (int) DatabaseManager::connection()->fetchOne('SELECT COUNT(*) FROM pages');
    expect($count)->toBe(1);
});

it('throws when the fixture file is missing', function () {
    Typo3ImpExpFixture::importOnce('/does/not/exist.xml');
})->throws(RuntimeException::class, 'ImpExp fixture not found');
