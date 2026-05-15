<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use RuntimeException;
use TYPO3\CMS\Core\Database\Schema\SchemaMigrator;
use TYPO3\CMS\Core\Database\Schema\SqlReader;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Builds the full TYPO3 schema (every CREATE TABLE statement TYPO3 + installed
 * extensions know about) on the currently-active Doctrine connection. Used by
 * the SQLite mode where the in-memory DB starts empty on every test run.
 */
final class Typo3SchemaInstaller
{
    private static bool $installed = false;

    public static function ensureInstalled(): void
    {
        if (self::$installed) {
            return;
        }
        $sqlReader = GeneralUtility::makeInstance(SqlReader::class);
        $statements = $sqlReader->getCreateTableStatementArray($sqlReader->getTablesDefinitionString());

        $migrator = GeneralUtility::makeInstance(SchemaMigrator::class);
        // createOnly=true: add tables/fields but don't try to ALTER existing
        // columns. SQLite rejects MySQL-style CHANGE COLUMN syntax, and a
        // re-run on MySQL must be idempotent. createOnly satisfies both.
        $result = $migrator->install($statements, true);

        // SchemaMigrator's "errors" array contains entries for every executed
        // statement; empty values mean success. Only real failures have a
        // non-empty error message.
        $errors = array_filter($result, static fn (string $v): bool => $v !== '');
        if ($errors !== []) {
            $msg = implode("\n", array_map(
                static fn (string $k, string $v): string => "  - $k: $v",
                array_keys($errors),
                array_values($errors),
            ));
            throw new RuntimeException("Schema install errors:\n" . $msg);
        }
        self::$installed = true;
    }

    /**
     * Empties every TYPO3 table on the shared connection. Run after schema
     * install so tests start from a known-empty state regardless of driver.
     */
    public static function truncateAllTables(): void
    {
        $conn = DatabaseManager::connection();
        $platform = $conn->getDatabasePlatform();
        $schema = $conn->createSchemaManager();
        foreach ($schema->listTableNames() as $table) {
            // getTruncateTableSQL emits TRUNCATE on MySQL, DELETE FROM on SQLite.
            $conn->executeStatement($platform->getTruncateTableSQL($conn->quoteIdentifier($table)));
        }
        // Anything that cached "this fixture is already in the DB" must drop
        // that cache — the truncate just made the cache lie.
        Typo3ImpExpFixture::forgetAll();
    }
}
