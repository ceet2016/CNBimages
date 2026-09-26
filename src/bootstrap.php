<?php
/**
 * bootstrap.php —— 应用引导文件（零依赖）
 *
 * 职责：
 *   1. 注册简易 PSR-4 自动加载（仅针对 App\ 命名空间，无需 Composer）
 *   2. 加载 .env 配置（同时支持容器/CI 注入的真实环境变量，环境变量优先）
 *   3. 设置默认时区
 */

declare(strict_types=1);

// ---------- 1. 自动加载 ----------
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

// ---------- 2. 加载配置 ----------
$envFile = __DIR__ . '/../.env';
App\Config::load($envFile);

// ---------- 3. 时区 ----------
date_default_timezone_set('Asia/Shanghai');
