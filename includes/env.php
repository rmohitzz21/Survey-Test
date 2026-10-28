<?php
/**
 * Load key=value pairs from .env into the process environment (does not override
 * already-set env vars — hosting panels / putenv win).
 */
function sp_load_env(?string $path = null): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    $path = $path ?? dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($path) || !is_readable($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if ($key === '') continue;
        // Strip matching single/double quotes
        if (
            (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
            (str_starts_with($val, "'") && str_ends_with($val, "'"))
        ) {
            $val = substr($val, 1, -1);
        }
        if (getenv($key) !== false) continue; // already set by host
        putenv("$key=$val");
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
    }
}

function sp_env(string $key, ?string $default = null): ?string {
    $v = getenv($key);
    if ($v === false || $v === '') return $default;
    return $v;
}
