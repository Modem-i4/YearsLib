<?php

function norm_path(string $requestUri): string
{
    $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
    $path = rtrim($path, '/');

    return $path === '' ? '/' : $path;
}

function render_page(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require $view;
}