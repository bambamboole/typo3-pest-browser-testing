<?php

return [
    'BE' => [
        'debug' => false,
        'installToolPassword' => '$argon2i$v=19$m=65536,t=16,p=1$dGVzdGJlbmNoaW5zdGFsbA$dHlwbzNfdGVzdGluZ19wYXNzd29yZF9oYXNoX29mX2luc3RhbGxfdG9vbF9wbGFjZWhvbGRlcg',
        'passwordHashing' => [
            'className' => 'TYPO3\\CMS\\Core\\Crypto\\PasswordHashing\\Argon2iPasswordHash',
            'options' => [],
        ],
    ],
    'DB' => [
        'Connections' => [
            'Default' => [
                'driver' => 'pdo_sqlite',
                'path' => ':memory:',
            ],
        ],
    ],
    'SYS' => [
        'encryptionKey' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
        'sitename' => 'TYPO3 Testing Testbench',
        'devIPmask' => '',
        'trustedHostsPattern' => '.*',
        'displayErrors' => 0,
    ],
];
