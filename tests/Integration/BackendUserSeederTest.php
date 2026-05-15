<?php
declare(strict_types=1);

use Bambamboole\Typo3Testing\Tests\Bootstrap;

beforeAll(fn() => Bootstrap::ensure());

use Bambamboole\Typo3Testing\Testing\BackendUserSeeder;
use Bambamboole\Typo3Testing\Testing\DatabaseManager;
use Bambamboole\Typo3Testing\Testing\Typo3SchemaInstaller;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

beforeEach(function () {
    DatabaseManager::ensureSharedConnection();
    Typo3SchemaInstaller::ensureInstalled();
    Typo3SchemaInstaller::truncateAllTables();
});

it('inserts a new admin user with the given credentials', function () {
    $uid = BackendUserSeeder::ensureTestAdmin('alice', 'wonderland');

    expect($uid)->toBeGreaterThan(0);

    $row = DatabaseManager::connection()->fetchAssociative(
        'SELECT username, admin, disable, deleted FROM be_users WHERE uid = ?',
        [$uid],
    );
    expect($row['username'])->toBe('alice');
    expect((int) $row['admin'])->toBe(1);
    expect((int) $row['disable'])->toBe(0);
    expect((int) $row['deleted'])->toBe(0);
});

it('upserts when the user already exists', function () {
    $uid1 = BackendUserSeeder::ensureTestAdmin('bob', 'first-password');
    $uid2 = BackendUserSeeder::ensureTestAdmin('bob', 'second-password');

    expect($uid1)->toBe($uid2);

    $count = (int) DatabaseManager::connection()->fetchOne(
        'SELECT COUNT(*) FROM be_users WHERE username = ?',
        ['bob'],
    );
    expect($count)->toBe(1);
});

it('writes a TYPO3-verifiable password hash', function () {
    BackendUserSeeder::ensureTestAdmin('carol', 'sekret');

    $hash = DatabaseManager::connection()->fetchOne(
        'SELECT password FROM be_users WHERE username = ?',
        ['carol'],
    );

    $factory = GeneralUtility::makeInstance(PasswordHashFactory::class);
    $hasher = $factory->get($hash, 'BE');
    expect($hasher->checkPassword('sekret', $hash))->toBeTrue();
    expect($hasher->checkPassword('wrong', $hash))->toBeFalse();
});
