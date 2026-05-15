<?php

defined('TYPO3') || die();

$EM_CONF['typo3_testing'] = [
    'title' => 'TYPO3 Testing',
    'description' => 'Pest browser testing for TYPO3 — in-process via amphp.',
    'category' => 'misc',
    'author' => 'Manuel Christlieb',
    'author_email' => 'manuel@christlieb.eu',
    'state' => 'beta',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
            'php' => '8.3.0-',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
