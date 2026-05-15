<?php
declare(strict_types=1);

use Bambamboole\Typo3Testing\Command\InitCommand;
use Bambamboole\Typo3Testing\Testing\TestingPaths;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function (): void {
    $this->sandbox = sys_get_temp_dir() . '/typo3-testing-init-' . bin2hex(random_bytes(4));
    mkdir($this->sandbox, 0o755, true);
    putenv("TYPO3_TESTING_PROJECT_ROOT=$this->sandbox");
    // Reset TestingPaths cache.
    $ref = new ReflectionClass(TestingPaths::class);
    $ref->getProperty('projectRoot')->setValue(null, null);

    $this->tester = new CommandTester(new InitCommand());
});

afterEach(function (): void {
    if (is_dir($this->sandbox)) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->sandbox, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->sandbox);
    }
    putenv('TYPO3_TESTING_PROJECT_ROOT');
    $ref = new ReflectionClass(TestingPaths::class);
    $ref->getProperty('projectRoot')->setValue(null, null);
});

it('scaffolds the expected files on an empty project', function () {
    $exit = $this->tester->execute([]);
    expect($exit)->toBe(0);

    foreach ([
        'phpunit.xml',
        'tests/Pest.php',
        'tests/BrowserTestCase.php',
        'tests/Unit/SmokeTest.php',
        'tests/Browser/.gitkeep',
        'tests/Feature/.gitkeep',
        'tests/Unit/.gitkeep',
        'tests/fixtures/.gitkeep',
    ] as $relative) {
        expect(is_file($this->sandbox . '/' . $relative))->toBeTrue("missing: $relative");
    }

    $display = $this->tester->getDisplay();
    expect($display)->toContain('Scaffold complete: 8 created, 0 overwritten, 0 skipped');
});

it('is idempotent: a second run skips existing files', function () {
    $this->tester->execute([]);
    $this->tester->execute([]);

    $display = $this->tester->getDisplay();
    expect($display)->toContain('Scaffold complete: 0 created, 0 overwritten, 8 skipped');
});

it('--force overwrites existing files', function () {
    // First run creates files.
    $this->tester->execute([]);

    // User-modified phpunit.xml.
    file_put_contents($this->sandbox . '/phpunit.xml', '<!-- user edit -->');

    $this->tester->execute(['--force' => true]);

    expect(file_get_contents($this->sandbox . '/phpunit.xml'))->not->toBe('<!-- user edit -->');
    $display = $this->tester->getDisplay();
    expect($display)->toContain('overwritten');
});

it('writes the BrowserTestCase stub with a Tests namespace', function () {
    $this->tester->execute([]);
    $contents = file_get_contents($this->sandbox . '/tests/BrowserTestCase.php');
    expect($contents)->toContain('namespace Tests;');
    expect($contents)->toContain('extends PackageBase');
});

it('returns a successful exit code', function () {
    expect($this->tester->execute([]))->toBe(0);
    expect($this->tester->execute([]))->toBe(0); // even on a no-op second run
});
