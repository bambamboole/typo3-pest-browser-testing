<?php
declare(strict_types=1);

use Bambamboole\Typo3Testing\Testing\DatabaseManager;
use Bambamboole\Typo3Testing\Testing\Typo3SchemaInstaller;
use Bambamboole\Typo3Testing\Tests\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * The single trick that makes per-test transactions work: when TYPO3's
 * runtime asks ConnectionPool for the Default connection, it must get the
 * same instance the test runner uses through DatabaseManager. That way a
 * BEGIN started by the test wraps every write TYPO3 does on its behalf —
 * middleware, DataHandler, anything else — and a single rollBack discards
 * the lot at tearDown.
 *
 * These tests exercise that claim directly at the DBAL level. The HTTP
 * server is not in scope: it's just one consumer of the same Connection,
 * and the higher-level browser tests (in consumer projects) cover its
 * end-to-end behaviour.
 */
beforeAll(fn() => Bootstrap::ensure());

beforeEach(function () {
    DatabaseManager::ensureSharedConnection();
    Typo3SchemaInstaller::ensureInstalled();
    Typo3SchemaInstaller::truncateAllTables();
});

it('returns the same Connection instance via DatabaseManager and ConnectionPool', function () {
    expect((new ConnectionPool())->getConnectionByName('Default'))
        ->toBe(DatabaseManager::connection());
});

it('writes done through ConnectionPool are visible via DatabaseManager', function () {
    $marker = bin2hex(random_bytes(4));

    DatabaseManager::beginTransaction();
    try {
        // The code path every TYPO3 middleware / DataHandler / service uses.
        (new ConnectionPool())->getConnectionForTable('sys_registry')->insert('sys_registry', [
            'entry_namespace' => 'shared-tx-test',
            'entry_key'       => $marker,
            'entry_value'     => 'a:0:{}',
        ]);

        // Test runner reads through DatabaseManager — same connection, same txn.
        $value = DatabaseManager::connection()->fetchOne(
            'SELECT entry_value FROM sys_registry WHERE entry_key = ?',
            [$marker],
        );
        expect($value)->toBe('a:0:{}');
    } finally {
        DatabaseManager::rollBack();
    }
});

it('rollBack discards rows written through either side of the connection', function () {
    $markerA = bin2hex(random_bytes(4));
    $markerB = bin2hex(random_bytes(4));

    DatabaseManager::beginTransaction();
    DatabaseManager::connection()->insert('sys_registry', [
        'entry_namespace' => 'shared-tx-test',
        'entry_key'       => $markerA,
        'entry_value'     => 'a:0:{}',
    ]);
    (new ConnectionPool())->getConnectionForTable('sys_registry')->insert('sys_registry', [
        'entry_namespace' => 'shared-tx-test',
        'entry_key'       => $markerB,
        'entry_value'     => 'a:0:{}',
    ]);
    DatabaseManager::rollBack();

    // Outside any transaction now — both rows must be gone.
    $count = (int) DatabaseManager::connection()->fetchOne(
        "SELECT COUNT(*) FROM sys_registry WHERE entry_namespace = 'shared-tx-test'",
    );
    expect($count)->toBe(0);
});

it('treats TYPO3-internal beginTransaction/commit as savepoints inside the outer txn', function () {
    // TYPO3's DataHandler and other internals frequently wrap their work in
    // their own begin/commit. setNestTransactionsWithSavepoints(true) — set
    // up in DatabaseManager — makes those calls SAVEPOINT / RELEASE SAVEPOINT
    // instead of real COMMIT, so the outer test transaction still owns the
    // visibility (and the ability to roll everything back).
    $marker = bin2hex(random_bytes(4));

    DatabaseManager::beginTransaction();
    $conn = (new ConnectionPool())->getConnectionForTable('sys_registry');

    $conn->beginTransaction(); // -> SAVEPOINT
    $conn->insert('sys_registry', [
        'entry_namespace' => 'shared-tx-test',
        'entry_key'       => $marker,
        'entry_value'     => 'a:0:{}',
    ]);
    $conn->commit(); // -> RELEASE SAVEPOINT; the row is NOT permanently committed

    // The row is visible while the outer transaction is open...
    expect((int) DatabaseManager::connection()->fetchOne(
        'SELECT COUNT(*) FROM sys_registry WHERE entry_key = ?',
        [$marker],
    ))->toBe(1);

    DatabaseManager::rollBack();

    // ...and gone after the outer rollBack, despite the inner "commit".
    expect((int) DatabaseManager::connection()->fetchOne(
        'SELECT COUNT(*) FROM sys_registry WHERE entry_key = ?',
        [$marker],
    ))->toBe(0);
});
