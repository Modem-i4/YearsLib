<?php

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Router.php';
require_once __DIR__ . '/../app/api/db.php';

$routes = [
    'GET' => [
        '/api/health' => static fn (): array => ['ok' => true],
        '/api/health/database' => static function (): array {
            db()->query('SELECT 1');
            return ['ok' => true, 'database' => 'connected'];
        },
    ],
];

(new Router($routes))->dispatch($_SERVER);