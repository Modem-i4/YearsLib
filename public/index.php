<?php

require __DIR__ . '/../app/helpers.php';

$path = norm_path($_SERVER['REQUEST_URI'] ?? '/');

if (str_starts_with($path, '/api')) {
    require __DIR__ . '/api.php';
    exit;
}

$routes = [
    '/' => ['title' => 'Шкільна бібліотека', 'view' => __DIR__ . '/../app/views/library.php'],
    '/admin' => ['title' => 'Адміністрування', 'view' => __DIR__ . '/../app/views/admin.php'],
];

if (preg_match('~^/book/([1-9][0-9]*)$~', $path, $matches)) {
    render_page(__DIR__ . '/../app/views/library.php', [
        'title' => 'Перегляд книги',
        'book_id' => (int)$matches[1],
    ]);
    exit;
}

if (preg_match('~^/admin/literature/([1-9][0-9]*)$~', $path, $matches)) {
    render_page(__DIR__ . '/../app/views/admin.php', [
        'title' => 'Облік видачі – Бібліотека Years',
        'book_id' => (int)$matches[1],
    ]);
    exit;
}

if (!isset($routes[$path])) {
    http_response_code(404);
    echo 'Сторінку не знайдено';
    exit;
}

render_page($routes[$path]['view'], $routes[$path]);
