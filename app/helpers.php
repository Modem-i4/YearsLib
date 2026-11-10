<?php

function norm_path(string $requestUri): string
{
    $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
    $path = rtrim($path, '/');

    return $path === '' ? '/' : $path;
}

function render_page(string $view, array $data = []): void
{
    header('Cache-Control: no-cache, must-revalidate');
    extract($data, EXTR_SKIP);
    require $view;
}

function versioned_asset(string $path): string
{
    $publicPath = __DIR__ . '/../public/' . ltrim($path, '/');
    if (!is_file($publicPath)) {
        return $path;
    }

    $hash = hash_file('sha256', $publicPath);
    return $path . ($hash !== false ? '?v=' . substr($hash, 0, 12) : '');
}
