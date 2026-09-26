<?php
/**
 * Csrf.php —— 跨站请求伪造防护
 *
 * 仅用于「会话（UI）」场景下的状态变更请求；API（Bearer Token）不走 CSRF。
 */

declare(strict_types=1);

namespace App;

final class Csrf
{
    private const KEY = 'csrf_token';

    public static function token(): string
    {
        Auth::startSession();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION[self::KEY];
    }

    /** 校验请求中的 CSRF Token（从 POST 字段或 X-CSRF-Token 头） */
    public static function verify(): bool
    {
        Auth::startSession();
        $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || $sent === '') {
            return false;
        }
        $expected = $_SESSION[self::KEY] ?? '';
        return $expected !== '' && hash_equals($expected, $sent);
    }

    /** 失败时统一报错（API 返回 JSON，UI 返回 403 页面） */
    public static function requireValid(): void
    {
        if (!self::verify()) {
            if (Auth::isApiRequest()) {
                Response::error('CSRF 校验失败', 403, 403);
            }
            Response::error('CSRF 校验失败', 403, 403);
        }
    }
}
