<?php

call_user_func(static function () {
    $classLoader = require dirname(__DIR__) . '/vendor/autoload.php';
    \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::run();

    $container = \TYPO3\CMS\Core\Core\Bootstrap::init($classLoader);

    if ($container->has(\TYPO3\CMS\Core\Http\Application::class)) {
        $container->get(\TYPO3\CMS\Core\Http\Application::class)->run();
        return;
    }

    $container->get(\TYPO3\CMS\Install\Http\Application::class)->run();
});
