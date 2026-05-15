<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Command;

use Bambamboole\Typo3Testing\Testing\TestingPaths;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'testing:init',
    description: 'Scaffold phpunit.xml, tests/ structure, and a host BrowserTestCase. Idempotent — existing files are kept unless --force.',
)]
final class InitCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files instead of skipping them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $projectRoot = TestingPaths::projectRoot();
        $stubRoot = TestingPaths::packageRoot() . '/stubs';

        $targets = [
            'phpunit.xml'                  => $stubRoot . '/phpunit.xml.stub',
            'tests/Pest.php'               => $stubRoot . '/Pest.php.stub',
            'tests/BrowserTestCase.php'    => $stubRoot . '/BrowserTestCase.stub.php',
            'tests/Browser/.gitkeep'       => null,
            'tests/Feature/.gitkeep'       => null,
            'tests/Unit/.gitkeep'          => null,
            'tests/Unit/SmokeTest.php'     => $stubRoot . '/SmokeTest.stub.php',
            'tests/fixtures/.gitkeep'      => null,
        ];

        $created = 0;
        $skipped = 0;
        $overwritten = 0;

        foreach ($targets as $relative => $stub) {
            $target = $projectRoot . '/' . $relative;
            $dir = dirname($target);
            if (! is_dir($dir) && ! @mkdir($dir, 0o755, true) && ! is_dir($dir)) {
                $io->error("Could not create directory: $dir");
                return Command::FAILURE;
            }

            $exists = is_file($target);
            if ($exists && ! $force) {
                $io->writeln("  <fg=gray>skip</> $relative <fg=gray>(already exists)</>");
                $skipped++;
                continue;
            }

            $contents = $stub !== null
                ? (string) @file_get_contents($stub)
                : '';
            file_put_contents($target, $contents);

            if ($exists) {
                $io->writeln("  <fg=yellow>overwrite</> $relative");
                $overwritten++;
            } else {
                $io->writeln("  <fg=green>create</>    $relative");
                $created++;
            }
        }

        $io->newLine();
        $io->success(sprintf(
            'Scaffold complete: %d created, %d overwritten, %d skipped.',
            $created,
            $overwritten,
            $skipped,
        ));
        $io->writeln('<comment>Next steps:</comment>');
        $io->writeln('  1. Edit <info>phpunit.xml</info> — set <info>BASE_DOMAIN</info> to the host your site config uses.');
        $io->writeln('  2. Edit <info>tests/BrowserTestCase.php::seed()</info> — add page records, ImpExp fixtures, etc.');
        $io->writeln('  3. Run <info>vendor/bin/pest --testsuite=browser</info>.');

        return Command::SUCCESS;
    }
}
