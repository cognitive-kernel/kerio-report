<?php
declare(strict_types=1);

/*
 * Copy to config.local.php on the server and fill in local credentials.
 * Never commit config.local.php.
 */
return [
    'app' => [
        'name' => 'Kerio Report',
        'timezone' => 'Asia/Tehran',
        'base_url' => 'http://localhost:8080/kerio-report/public',
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'kerio_report',
        'username' => 'kerio',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'kerio' => [
        'url' => 'https://192.168.1.2:4081/admin/api/jsonrpc/',
        'username' => '',
        'password' => '',
        'application' => 'KerioReport',
        'verify_tls' => true,
    ],
];
