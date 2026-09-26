<?php
/**
 * Config.php —— 配置读取
 *
 * 支持两种配置来源（环境变量优先级更高，便于容器 / CNB 部署注入）：
 *   1. 仓库根目录的 .env 文件（本地开发用）
 *   2. 真实环境变量（Docker / CNB 部署时注入）
 */

declare(strict_types=1);

namespace App;

final class Config
{
    /** @var array<string,string> */
    private static array $items = [];

    public static function load(string $envFile): void
    {
        if (!file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), "\"'");
            self::$items[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        // 容器 / CI 注入的真实环境变量优先
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }
        return self::$items[$key] ?? $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $v = strtolower((string) self::get($key, ''));
        return match ($v) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off', '' => $default,
            default => $default,
        };
    }

    /**
     * @param string[] $default
     * @return string[]
     */
    public static function getList(string $key, array $default = [], string $sep = ','): array
    {
        $v = (string) self::get($key, '');
        if ($v === '') {
            return $default;
        }
        return array_filter(array_map('trim', explode($sep, $v)), static fn ($x) => $x !== '');
    }
}
