<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use TYPO3\CMS\Core\Database\Connection;

/**
 * Programmatic test data seeding. Factory-style helpers your test class can
 * call from its `seed()` override (run once per class, before the first
 * transaction).
 *
 * `seedPage()` / `seedContent()` write through the shared TYPO3 Connection
 * so the in-process HTTP request handler sees the same rows.
 *
 * To create a site root, pass `is_siteroot => 1` plus the explicit `uid` you
 * referenced from `config/sites/<id>/config.yaml`'s rootPageId:
 *
 *     Typo3Seeder::seedPage(0, 'Site', ['uid' => 1, 'is_siteroot' => 1, 'slug' => '/']);
 */
final class Typo3Seeder
{
    /**
     * Insert a page record. Returns the new uid.
     *
     * @param array<string, mixed> $extras
     */
    public static function seedPage(int $pid, string $title, array $extras = []): int
    {
        $conn = DatabaseManager::connection();
        return self::insertPage($conn, array_merge([
            'pid'     => $pid,
            'title'   => $title,
            'doktype' => 1,
            'slug'    => '/' . self::slugify($title),
            'sorting' => 1024,
        ], $extras));
    }

    /**
     * Insert a tt_content row. Returns the new uid.
     *
     * @param array<string, mixed> $extras
     */
    public static function seedContent(int $pid, string $header, ?string $bodytext = null, array $extras = []): int
    {
        $conn = DatabaseManager::connection();
        $now = time();
        $row = array_merge([
            'pid'      => $pid,
            'CType'    => 'text',
            'header'   => $header,
            'bodytext' => $bodytext,
            'sorting'  => 1024,
            'crdate'   => $now,
            'tstamp'   => $now,
            'colPos'   => 0,
        ], $extras);
        $conn->insert('tt_content', $row);
        return (int) $conn->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function insertPage(Connection $conn, array $row): int
    {
        $now = time();
        $row = array_merge([
            'pid'             => 0,
            'doktype'         => 1,
            'hidden'          => 0,
            'deleted'         => 0,
            'crdate'          => $now,
            'tstamp'          => $now,
            'sorting'         => 1024,
            'perms_userid'    => 1,
            'perms_groupid'   => 0,
            'perms_user'      => 31,
            'perms_group'     => 31,
            'perms_everybody' => 0,
        ], $row);
        $conn->insert('pages', $row);
        return (int) ($row['uid'] ?? $conn->lastInsertId());
    }

    private static function slugify(string $title): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
        return $slug === '' ? 'page' : $slug;
    }
}
