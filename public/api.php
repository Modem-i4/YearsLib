<?php

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Router.php';
require_once __DIR__ . '/../app/api/db.php';
require_once __DIR__ . '/../app/api/Controllers/BooksController.php';
require_once __DIR__ . '/../app/api/Controllers/AdminController.php';
require_once __DIR__ . '/../app/api/Controllers/LoansController.php';
require_once __DIR__ . '/../app/api/Controllers/TagsController.php';

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
$loansController = static fn (): LoansController => new LoansController(db());
$tagsController = static fn (): TagsController => new TagsController(db());

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
        '/api/admin/books/{bookId}/loans' => static fn (string $bookId): array => $loansController()->index($bookId),
        '/api/admin/tags' => static fn (): array => $tagsController()->index(),
    ],
    'POST' => [
        '/api/admin/login' => [$adminController, 'login'],
        '/api/admin/logout' => [$adminController, 'logout'],
        '/api/admin/covers' => [$adminController, 'uploadCover'],
        '/api/books' => static fn (): array => $booksController()->create(),
        '/api/admin/books/{bookId}/loans' => static fn (string $bookId): array => $loansController()->create($bookId),
        '/api/admin/loans/{loanId}/return' => static fn (string $loanId): array => $loansController()->returnBook($loanId),
        '/api/admin/tags' => static fn (): array => $tagsController()->create(),
    ],
    'PUT' => [
        '/api/books/{id}' => static fn (string $id): array => $booksController()->update($id),
        '/api/admin/loans/{loanId}' => static fn (string $loanId): array => $loansController()->update($loanId),
        '/api/admin/tags/{tagId}' => static fn (string $tagId): array => $tagsController()->update($tagId),
    ],
    'DELETE' => [
        '/api/admin/tags/{tagId}' => static fn (string $tagId): array => $tagsController()->delete($tagId),
    ],
];

(new Router($routes))->dispatch($_SERVER);
