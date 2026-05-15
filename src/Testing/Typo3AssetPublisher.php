<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use Psr\Container\ContainerInterface;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\SystemResource\Publishing\SystemResourcePublisherInterface;

/**
 * Programmatically equivalent of `bin/typo3 asset:publish` — iterates every
 * available package and publishes its Resources/Public into _assets/.
 *
 * Consumer projects normally don't need this: cms-composer-installers
 * publishes during `composer install`, and TYPO3 ships the
 * `bin/typo3 asset:publish` command for ad-hoc reruns. The helper exists
 * for the package's own testbench, whose vendor/ is a runtime symlink and
 * therefore skips both paths.
 */
final class Typo3AssetPublisher
{
    private static bool $done = false;

    public static function ensurePublished(ContainerInterface $container): void
    {
        if (self::$done) {
            return;
        }
        $publisher = $container->get(SystemResourcePublisherInterface::class);
        $packageManager = $container->get(PackageManager::class);
        foreach ($packageManager->getAvailablePackages() as $package) {
            $publisher->publishResources($package);
        }
        self::$done = true;
    }
}
