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

it('inserts a page with the requested title and pid', function () {
    $uid = Typo3Seeder::seedPage(0, 'Home');

    expect($uid)->toBeGreaterThan(0);

    $row = DatabaseManager::connection()->fetchAssociative(
        'SELECT title, pid, doktype FROM pages WHERE uid = ?',
        [$uid],
    );
    expect($row['title'])->toBe('Home');
    expect((int) $row['pid'])->toBe(0);
    expect((int) $row['doktype'])->toBe(1);
});

it('honors explicit extras like uid and is_siteroot', function () {
    $uid = Typo3Seeder::seedPage(0, 'Site Root', [
        'uid' => 42,
        'is_siteroot' => 1,
        'slug' => '/',
    ]);

    expect($uid)->toBe(42);

    $row = DatabaseManager::connection()->fetchAssociative(
        'SELECT is_siteroot, slug FROM pages WHERE uid = 42',
    );
    expect((int) $row['is_siteroot'])->toBe(1);
    expect($row['slug'])->toBe('/');
});

it('slugifies the title when no slug is given', function () {
    $uid = Typo3Seeder::seedPage(0, 'My Page Title!');
    $slug = DatabaseManager::connection()->fetchOne('SELECT slug FROM pages WHERE uid = ?', [$uid]);
    expect($slug)->toBe('/my-page-title');
});

it('inserts a tt_content row on the given pid', function () {
    $pageUid = Typo3Seeder::seedPage(0, 'A page');
    $contentUid = Typo3Seeder::seedContent($pageUid, 'Hello', 'World');

    $row = DatabaseManager::connection()->fetchAssociative(
        'SELECT pid, header, bodytext, CType, colPos FROM tt_content WHERE uid = ?',
        [$contentUid],
    );
    expect((int) $row['pid'])->toBe($pageUid);
    expect($row['header'])->toBe('Hello');
    expect($row['bodytext'])->toBe('World');
    expect($row['CType'])->toBe('text');
    expect((int) $row['colPos'])->toBe(0);
});
