<?php
namespace App\Helpers;
final class Env {
    private static array $data=[];
    public static function load(string $path): void {
        if (!is_file($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
            $line=trim($line); if ($line==='' || str_starts_with($line,'#') || !str_contains($line,'=')) continue;
            [$k,$v]=array_map('trim', explode('=', $line, 2)); self::$data[$k]=trim($v, "\"'");
        }
    }
    public static function get(string $key, mixed $default=null): mixed { return self::$data[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default; }
}
