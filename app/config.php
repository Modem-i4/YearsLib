<?php

function app_env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    if ($value !== false) {
        return $value;
    }

    static $fileValues = null;
    if ($fileValues === null) {
        $fileValues = [];
        $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (is_file($envFile) && is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                [$key, $entryValue] = array_pad(explode('=', $line, 2), 2, '');
                $key = trim($key);
                if ($key === '') {
                    continue;
                }

                $entryValue = trim($entryValue);
                if (strlen($entryValue) >= 2) {
                    $quote = $entryValue[0];
                    if (($quote === '"' || $quote === "'") && substr($entryValue, -1) === $quote) {
                        $entryValue = substr($entryValue, 1, -1);
                    }
                }
                $fileValues[$key] = $entryValue;
            }
        }
    }

    return $fileValues[$name] ?? $default;
}