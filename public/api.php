<?php

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Router.php';
require_once __DIR__ . '/../app/api/db.php';
require_once __DIR__ . '/../app/api/Controllers/BooksController.php';

$booksController = static fn (): BooksController => new BooksController(db());

$routes = [
    'GET' => [
        '/api/health' => static fn (): array => ['ok' => true],
        '/api/health/database' => static function (): array {
            db()->query('SELECT 1');
            return ['ok' => true, 'database' => 'connected'];
        },
        '/api/books' => static fn (): array => $booksController()->index(),
        '/api/books/{id}' => static fn (string $id): array => $booksController()->show($id),
    ],
    'POST' => [
        '/api/books' => static fn (): array => $booksController()->create(),
    ],
    'PUT' => [
        '/api/books/{id}' => static fn (string $id): array => $booksController()->update($id),
    ],
];

(new Router($routes))->dispatch($_SERVER);