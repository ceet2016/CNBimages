<?php
/**
 * Response.php —— 统一响应（JSON / 重定向 / HTML）
 *
 * JSON 返回结构特意同时提供顶层的 `result` 与 `data.url`：
 *   - PicGo「自定义 Web 图床」插件（web-uploader）的 JSON 路径常用 `result`
 *   - PicGo「custom-api-uploader」插件的取值路径常用 `data.url`
 */

declare(strict_types=1);

namespace App;

final class Response
{
    public static function json(array $data, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(string $url, array $extra = []): void
    {
        self::json(array_merge([
            'success' => true,
            'code'    => 0,
            'message' => 'ok',
            'result'  => $url,
            'data'    => ['url' => $url],
        ], $extra), 200);
    }

    public static function error(string $message, int $code = 1, int $httpCode = 400): void
    {
        self::json([
            'success' => false,
            'code'    => $code,
            'message' => $message,
            'result'  => null,
        ], $httpCode);
    }

    public static function redirect(string $url, int $code = 302): void
    {
        http_response_code($code);
        header('Location: ' . $url);
        exit;
    }

    public static function html(string $html, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
}
