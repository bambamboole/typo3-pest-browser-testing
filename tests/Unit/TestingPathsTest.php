<?php
declare(strict_types=1);

use Bambamboole\Typo3Testing\Testing\TestingPaths;

beforeEach(function (): void {
    // The env-var override path is the only branch we can exercise without
    // mucking with Composer state, so we use it and reset between tests.
    $this->previousEnv = getenv('TYPO3_TESTING_PROJECT_ROOT');
    // Reset the static cache via reflection so each test starts clean.
    $ref = new ReflectionClass(TestingPaths::class);
    $prop = $ref->getProperty('projectRoot');
    $prop->setValue(null, null);
});

afterEach(function (): void {
    if ($this->previousEnv === false) {
        putenv('TYPO3_TESTING_PROJECT_ROOT');
    } else {
        putenv("TYPO3_TESTING_PROJECT_ROOT=$this->previousEnv");
    }
    $ref = new ReflectionClass(TestingPaths::class);
    $prop = $ref->getProperty('projectRoot');
    $prop->setValue(null, null);
});

it('honors TYPO3_TESTING_PROJECT_ROOT when set', function () {
    putenv('TYPO3_TESTING_PROJECT_ROOT=/some/explicit/path');
    expect(TestingPaths::projectRoot())->toBe('/some/explicit/path');
});

it('strips a trailing slash from the env override', function () {
    putenv('TYPO3_TESTING_PROJECT_ROOT=/some/explicit/path/');
    expect(TestingPaths::projectRoot())->toBe('/some/explicit/path');
});

it('returns the package root pointing at the package directory', function () {
    $root = TestingPaths::packageRoot();
    expect(is_dir($root))->toBeTrue();
    expect(is_file($root . '/composer.json'))->toBeTrue();
    expect(is_dir($root . '/src/Testing'))->toBeTrue();
});

it('falls back to Composer when the env var is unset', function () {
    putenv('TYPO3_TESTING_PROJECT_ROOT');
    // Whatever the resolver returns, it must contain a vendor/autoload.php.
    expect(is_file(TestingPaths::projectRoot() . '/vendor/autoload.php'))->toBeTrue();
});
