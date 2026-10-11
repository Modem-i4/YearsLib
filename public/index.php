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

if (!isset($routes[$path])) {
    http_response_code(404);
    echo 'Сторінку не знайдено';
    exit;
}

render_page($routes[$path]['view'], $routes[$path]);