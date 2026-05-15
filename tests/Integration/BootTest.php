<?php
declare(strict_types=1);

use Bambamboole\Typo3Testing\Testing\Typo3Bootstrap;
use Bambamboole\Typo3Testing\Tests\Bootstrap;
use Psr\Container\ContainerInterface;
use TYPO3\CMS\Core\Http\Application;

beforeAll(fn() => Bootstrap::ensure());

it('boots TYPO3 against the testbench project root', function () {
    $container = Typo3Bootstrap::ensureBooted(
        \Bambamboole\Typo3Testing\Tests\Testbench::projectRoot(),
    );

    expect($container)->toBeInstanceOf(ContainerInterface::class);
    expect($container->has(Application::class))->toBeTrue();
});

it('returns the same container on repeated boot calls', function () {
    $a = Typo3Bootstrap::ensureBooted(\Bambamboole\Typo3Testing\Tests\Testbench::projectRoot());
    $b = Typo3Bootstrap::ensureBooted(\Bambamboole\Typo3Testing\Tests\Testbench::projectRoot());
    expect($a)->toBe($b);
});

it('overrides the DB connection config for SQLite mode', function () {
    Typo3Bootstrap::ensureBooted(\Bambamboole\Typo3Testing\Tests\Testbench::projectRoot());
    expect($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driver'])
        ->toBe('pdo_sqlite');
});
