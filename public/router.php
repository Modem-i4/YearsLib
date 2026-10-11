<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = realpath(__DIR__ . $path);
$publicRoot = realpath(__DIR__);

if (
    $path !== '/' &&
    $file !== false &&
    $publicRoot !== false &&
    str_starts_with($file, $publicRoot . DIRECTORY_SEPARATOR) &&
    is_file($file)
) {
    return false;
}

require __DIR__ . '/index.php';