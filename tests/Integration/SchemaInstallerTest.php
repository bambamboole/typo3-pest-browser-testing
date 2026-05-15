<?php
declare(strict_types=1);

use Bambamboole\Typo3Testing\Tests\Bootstrap;

beforeAll(fn() => Bootstrap::ensure());

use Bambamboole\Typo3Testing\Testing\DatabaseManager;
use Bambamboole\Typo3Testing\Testing\Typo3SchemaInstaller;
use Bambamboole\Typo3Testing\Testing\Typo3Seeder;

beforeEach(function () {
    DatabaseManager::ensureSharedConnection();
    Typo3SchemaInstaller::ensureInstalled();
    Typo3SchemaInstaller::truncateAllTables();
});

it('creates every TYPO3 core table from TCA + ext_tables.sql', function () {
    $tables = DatabaseManager::connection()->createSchemaManager()->listTableNames();

    expect($tables)->toContain('pages');
    expect($tables)->toContain('tt_content');
    expect($tables)->toContain('be_users');
    expect($tables)->toContain('be_sessions');
    expect($tables)->toContain('sys_registry');
});

it('truncate empties every table', function () {
    Typo3Seeder::seedPage(0, 'Will be wiped');
    $conn = DatabaseManager::connection();
    expect((int) $conn->fetchOne('SELECT COUNT(*) FROM pages'))->toBe(1);

    Typo3SchemaInstaller::truncateAllTables();
    expect((int) $conn->fetchOne('SELECT COUNT(*) FROM pages'))->toBe(0);
});
