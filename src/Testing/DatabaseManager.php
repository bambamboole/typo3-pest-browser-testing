<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class DatabaseManager
{
    private static ?Connection $connection = null;

    public static function connection(): Connection
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        // TYPO3's ConnectionPool builds and caches a TYPO3 Connection (which
        // extends Doctrine\DBAL\Connection) on first call. Subsequent calls -
        // from both the test runner and HTTP request handler - return the
        // same instance, which is what makes transactional isolation work.
        $conn = (new ConnectionPool())->getConnectionByName('Default');
        $conn->setNestTransactionsWithSavepoints(true);

        return self::$connection = $conn;
    }

    public static function ensureSharedConnection(): void
    {
        self::connection();
    }

    public static function beginTransaction(): void
    {
        self::connection()->beginTransaction();
    }

    public static function rollBack(): void
    {
        $conn = self::connection();
        if ($conn->isTransactionActive()) {
            $conn->rollBack();
        }
    }
}
