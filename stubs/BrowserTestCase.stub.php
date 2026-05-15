<?php
declare(strict_types=1);

namespace Tests;

use Bambamboole\Typo3Testing\Testing\BrowserTestCase as PackageBase;

/**
 * Project-wide browser test base class. Override seed() to lay down the data
 * every browser test in this project should be able to assume — typically a
 * site root page (matching your config/sites/<id>/config.yaml rootPageId) and
 * optionally an ImpExp fixture for richer content.
 *
 * Examples (uncomment and adapt):
 *
 *   use Bambamboole\Typo3Testing\Testing\Typo3ImpExpFixture;
 *   use Bambamboole\Typo3Testing\Testing\Typo3Seeder;
 *
 *   protected function seed(): void
 *   {
 *       // Bare minimum: a site root matching rootPageId in your site config.
 *       Typo3Seeder::seedPage(0, 'My Site', [
 *           'uid' => 1, 'is_siteroot' => 1, 'slug' => '/',
 *       ]);
 *
 *       // Or load a real content tree exported via:
 *       //   vendor/bin/typo3 impexp:export --pid=<root> --levels=999 --table=_ALL
 *       Typo3ImpExpFixture::importOnce(
 *           Typo3ImpExpFixture::projectFixture('tests/fixtures/site.xml'),
 *       );
 *   }
 */
class BrowserTestCase extends PackageBase
{
    protected function seed(): void
    {
        // Fill in your project's baseline seed here.
    }
}
