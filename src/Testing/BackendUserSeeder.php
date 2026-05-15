<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Inserts (or refreshes) a backend admin user inside the shared transaction so
 * tests can log in with known credentials. The user vanishes again on rollback.
 */
final class BackendUserSeeder
{
    public static function ensureTestAdmin(
        string $username = 'testadmin',
        string $password = 'testadmin-password',
    ): int {
        $conn = DatabaseManager::connection();

        $hashFactory = GeneralUtility::makeInstance(PasswordHashFactory::class);
        $hash = $hashFactory->getDefaultHashInstance('BE')->getHashedPassword($password);

        $existingUid = (int) ($conn->fetchOne(
            'SELECT uid FROM be_users WHERE username = ?',
            [$username]
        ) ?: 0);

        if ($existingUid > 0) {
            $conn->update('be_users', [
                'password' => $hash,
                'admin'    => 1,
                'disable'  => 0,
                'deleted'  => 0,
                'tstamp'   => time(),
            ], ['uid' => $existingUid]);
            return $existingUid;
        }

        $conn->insert('be_users', [
            'username' => $username,
            'password' => $hash,
            'admin'    => 1,
            'disable'  => 0,
            'deleted'  => 0,
            'realName' => 'Test Admin',
            'email'    => 'testadmin@example.test',
            'crdate'   => time(),
            'tstamp'   => time(),
        ]);

        return (int) $conn->lastInsertId();
    }
}
