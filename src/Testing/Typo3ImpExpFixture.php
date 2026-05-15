<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use RuntimeException;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Impexp\Exception\ImportFailedException;
use TYPO3\CMS\Impexp\Import;

/**
 * Loads a TYPO3 ImpExp `.t3d`/`.xml` export into the shared connection.
 * Cached per fixture path per process; truncate hooks must call forgetAll().
 */
final class Typo3ImpExpFixture
{
    /** @var array<string, bool> */
    private static array $imported = [];

    public static function forgetAll(): void
    {
        self::$imported = [];
    }

    /**
     * @param bool $strict when true, any import error (including missing
     *                     file-reference targets) raises; default is lenient
     *                     because most test fixtures don't include uploaded
     *                     files from fileadmin/.
     */
    public static function importOnce(string $path, bool $strict = false): void
    {
        $real = realpath($path);
        if ($real === false || !is_file($real)) {
            throw new RuntimeException("ImpExp fixture not found: $path");
        }
        if (self::$imported[$real] ?? false) {
            return;
        }

        // Import → DataHandler requires an authenticated BE user; seed and
        // hydrate a global admin so the import has the rights it needs.
        // Stash whatever was there so we can put it back afterwards — leaving
        // a BE_USER live across the suite causes TYPO3 to treat subsequent
        // frontend requests as authenticated backend sessions and try to
        // render the admin toolbar (which then explodes on missing LANG).
        $previousBeUser = $GLOBALS['BE_USER'] ?? null;
        self::ensureBackendUserGlobal();

        $import = GeneralUtility::makeInstance(Import::class);
        $import->setEnableLogging(false);
        // Site configurations live as YAML files in config/sites/, OUTSIDE
        // the test transaction. The import would otherwise mint new
        // camino-1/, camino-2/ directories on disk on every test run.
        $import->disableSiteConfigurationImport();
        // Insert at root; without this $this->pid is null and DataHandler
        // bombs in addDefaultPermittedLanguageIfNotSet on a null pid arg.
        $import->setPid(0);
        // Preserve exported uids — site config references rootPageId=2 by
        // value, so remapping camino root from uid=2 to whatever auto-
        // increment the import picks would break frontend routing.
        $import->setForceAllUids(true);
        $import->loadFile($real);
        $import->checkImportPrerequisites();
        try {
            $import->importData();
        } catch (ImportFailedException $e) {
            // Non-fatal by default: file-reference relations may fail when
            // fileadmin contents aren't part of the fixture. The page tree +
            // tt_content are still written. Pass $strict=true at the call site
            // when missing media should fail the suite.
            if ($strict) {
                $errors = $import->getErrorLog();
                $msg = implode("\n  - ", array_map(
                    static fn ($v): string => is_string($v) ? $v : json_encode($v),
                    $errors,
                ));
                throw new RuntimeException("Import failed:\n  - $msg", 0, $e);
            }
        }

        if ($previousBeUser === null) {
            unset($GLOBALS['BE_USER']);
        } else {
            $GLOBALS['BE_USER'] = $previousBeUser;
        }

        self::$imported[$real] = true;
    }

    private static function ensureBackendUserGlobal(): void
    {
        if (isset($GLOBALS['BE_USER']) && $GLOBALS['BE_USER'] instanceof BackendUserAuthentication) {
            return;
        }
        $uid = BackendUserSeeder::ensureTestAdmin();
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $beUser->setBeUserByUid($uid);
        $beUser->backendCheckLogin();
        $GLOBALS['BE_USER'] = $beUser;
    }

    public static function projectFixture(string $relativePath): string
    {
        return TestingPaths::projectRoot() . '/' . ltrim($relativePath, '/');
    }
}
