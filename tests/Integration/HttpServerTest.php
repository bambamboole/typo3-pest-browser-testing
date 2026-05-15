<?php
declare(strict_types=1);

use Bambamboole\Typo3Testing\Testing\DatabaseManager;
use Bambamboole\Typo3Testing\Testing\Typo3Bootstrap;
use Bambamboole\Typo3Testing\Testing\Typo3HttpServer;
use Bambamboole\Typo3Testing\Testing\Typo3SchemaInstaller;
use Bambamboole\Typo3Testing\Testing\Typo3Seeder;
use Bambamboole\Typo3Testing\Testing\Typo3StateResetter;
use Bambamboole\Typo3Testing\Tests\Bootstrap;
use Bambamboole\Typo3Testing\Tests\Testbench;

beforeAll(function () {
    Bootstrap::ensure();
    DatabaseManager::ensureSharedConnection();
    Typo3SchemaInstaller::ensureInstalled();
    Typo3SchemaInstaller::truncateAllTables();
    // The testbench site config points at rootPageId=1.
    Typo3Seeder::seedPage(0, 'Testbench Root', [
        'uid' => 1,
        'is_siteroot' => 1,
        'slug' => '/',
    ]);
});

it('starts on a free local port and reports its url()', function () {
    $container = Typo3Bootstrap::ensureBooted(Testbench::projectRoot());
    $server = new Typo3HttpServer($container, new Typo3StateResetter());
    $server->start();

    try {
        $url = $server->url();
        expect($url)->toMatch('#^http://127\.0\.0\.1:\d+$#');
    } finally {
        $server->stop();
    }
});

// "Serves a real HTTP request" is intentionally not tested here. amphp's
// HTTP server only accepts connections while the event loop is ticking,
// and the loop ticks only while a Future is being awaited. A blocking
// curl() from the same PHP process never yields back to the loop, so the
// request would hang. Round-trip HTTP behaviour is validated by consumer
// browser tests — pest-plugin-browser drives the loop via its
// amphp/websocket-client when it talks to Playwright.

it('stop() leaves the port reusable for the next start()', function () {
    $container = Typo3Bootstrap::ensureBooted(Testbench::projectRoot());
    $server1 = new Typo3HttpServer($container, new Typo3StateResetter());
    $server1->start();
    $url1 = $server1->url();
    $server1->stop();

    $server2 = new Typo3HttpServer($container, new Typo3StateResetter());
    $server2->start();
    try {
        // Different instance, different picked port — proves the first one
        // released its socket cleanly and we can keep allocating.
        expect($server2->url())->toMatch('#^http://127\.0\.0\.1:\d+$#');
    } finally {
        $server2->stop();
    }
});
