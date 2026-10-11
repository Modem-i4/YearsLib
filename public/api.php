<?php

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Router.php';
require_once __DIR__ . '/../app/api/db.php';
require_once __DIR__ . '/../app/api/Controllers/BooksController.php';
require_once __DIR__ . '/../app/api/Controllers/AdminController.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$booksController = static fn (): BooksController => new BooksController(db());
$adminController = new AdminController();

$routes = [
    'GET' => [
        '/api/health' => static fn (): array => ['ok' => true],
        '/api/health/database' => static function (): array {
            db()->query('SELECT 1');
            return ['ok' => true, 'database' => 'connected'];
        },
        '/api/admin/session' => [$adminController, 'session'],
        '/api/books' => static fn (): array => $booksController()->index(),
        '/api/books/{id}' => static fn (string $id): array => $booksController()->show($id),
    ],
    'POST' => [
        '/api/admin/login' => [$adminController, 'login'],
        '/api/admin/logout' => [$adminController, 'logout'],
        '/api/admin/covers' => [$adminController, 'uploadCover'],
        '/api/books' => static fn (): array => $booksController()->create(),
    ],
    'PUT' => [
        '/api/books/{id}' => static fn (string $id): array => $booksController()->update($id),
    ],
];

(new Router($routes))->dispatch($_SERVER);