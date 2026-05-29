<?php

require __DIR__ . '/../vendor/autoload.php';

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');

$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
}

function env_value(string $key, ?string $default = null): ?string
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

if (!is_dir(__DIR__ . '/../storage')) {
    mkdir(__DIR__ . '/../storage', 0775, true);
}

if (!is_dir(__DIR__ . '/../storage/uploads')) {
    mkdir(__DIR__ . '/../storage/uploads', 0775, true);
}
