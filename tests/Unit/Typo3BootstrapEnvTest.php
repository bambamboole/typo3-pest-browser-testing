<?php
declare(strict_types=1);

use Bambamboole\Typo3Testing\Testing\Typo3Bootstrap;

beforeEach(function (): void {
    // Snapshot known env vars so we can restore them in afterEach.
    $this->snapshot = [];
    foreach (['DB_DRIVER', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'BASE_DOMAIN'] as $key) {
        $this->snapshot[$key] = getenv($key);
    }
});

afterEach(function (): void {
    foreach ($this->snapshot as $key => $value) {
        if ($value === false) {
            putenv($key);
        } else {
            putenv("$key=$value");
        }
    }
});

it('returns the env value when set', function () {
    putenv('BASE_DOMAIN=example.test');
    expect(Typo3Bootstrap::env('BASE_DOMAIN'))->toBe('example.test');
});

it('falls back to the default when the env var is unset', function () {
    putenv('BASE_DOMAIN');
    expect(Typo3Bootstrap::env('BASE_DOMAIN', 'fallback.test'))->toBe('fallback.test');
});

it('falls back to the default when the env var is empty', function () {
    putenv('BASE_DOMAIN=');
    expect(Typo3Bootstrap::env('BASE_DOMAIN', 'fallback.test'))->toBe('fallback.test');
});

it('returns empty string when no default is given and env is unset', function () {
    putenv('UNSET_VAR_XYZ');
    expect(Typo3Bootstrap::env('UNSET_VAR_XYZ'))->toBe('');
});
