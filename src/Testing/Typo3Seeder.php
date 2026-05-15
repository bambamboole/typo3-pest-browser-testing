<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use TYPO3\CMS\Core\Database\Connection;

/** Factory inserts for pages and tt_content over the shared Connection. */
final class Typo3Seeder
{
    /** @param array<string, mixed> $extras */
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

    /** @param array<string, mixed> $extras */
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
