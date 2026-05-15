<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use TYPO3\CMS\Core\Session\UserSessionManager;

/**
 * Fixates a backend session for an existing be_users row via TYPO3's own
 * UserSessionManager and returns the JWT cookie value (be_typo_user).
 */
final class BackendSessionForge
{
    public static function forUser(int $userUid): string
    {
        $manager = UserSessionManager::create('BE');
        $anon = $manager->createAnonymousSession();
        $session = $manager->elevateToFixatedUserSession($anon, $userUid);

        return $session->getJwt();
    }
}
