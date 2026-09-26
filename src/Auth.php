<?php
/**
 * Auth.php —— 鉴权（会话 + API Token）
 *
 * 两套认证：
 *   1. 会话（UI 登录）：基于 PHP 原生 Session + 安全 Cookie。
 *   2. API Token（PicGo / 程序调用）：请求头 Authorization: Bearer <TOKEN>，
 *      服务端只保存 token 的哈希，原始 token 仅展示一次。
 */

declare(strict_types=1);

namespace App;

use PDO;

final class Auth
{
    private const SESSION_KEY = 'uid';

    /** 配置并启动会话 */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $savePath = (string) Config::get('SESSION_SAVE_PATH', '/var/www/data/sessions');
        if (!is_dir($savePath)) {
            mkdir($savePath, 0700, true);
        }
        session_save_path($savePath);
        $secure = Config::getBool('APP_SECURE_COOKIE', false);
        session_set_cookie_params([
            'lifetime' => Config::getInt('SESSION_LIFETIME', 86400),
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** 通过会话或 Bearer Token 解析当前用户（用于 API / UI 通用入口） */
    public static function resolveUser(): ?array
    {
        self::startSession();
        if (!empty($_SESSION[self::SESSION_KEY])) {
            $user = self::getUserById((int) $_SESSION[self::SESSION_KEY]);
            if ($user !== null) {
                return $user;
            }
        }
        $token = self::bearerToken();
        if ($token !== null) {
            return self::userByToken($token);
        }
        return null;
    }

    /** 仅通过会话返回用户（UI 页面用） */
    public static function sessionUser(): ?array
    {
        self::startSession();
        if (empty($_SESSION[self::SESSION_KEY])) {
            return null;
        }
        return self::getUserById((int) $_SESSION[self::SESSION_KEY]);
    }

    public static function loginWithPassword(string $username, string $password): ?array
    {
        $user = self::getUserByUsername($username);
        if ($user === null) {
            return null;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }
        return $user;
    }

    public static function loginUser(array $user): void
    {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $user['id'];
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin(): array
    {
        $user = self::sessionUser();
        if ($user === null) {
            if (self::isApiRequest()) {
                Response::error('未登录或凭证失效', 401, 401);
            }
            Response::redirect('/login');
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if ($user['role'] !== 'admin') {
            Response::error('需要管理员权限', 403, 403);
        }
        return $user;
    }

    // ---------------- Token 管理 ----------------

    public static function createToken(int $userId, string $name): string
    {
        $raw = bin2hex(random_bytes(24));
        $hash = hash('sha256', $raw);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO tokens (user_id, token_hash, name) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $hash, $name]);
        return $raw;
    }

    public static function listTokens(int $userId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, name, last_used, created_at FROM tokens WHERE user_id = ? ORDER BY id DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function revokeToken(int $tokenId, int $userId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM tokens WHERE id = ? AND user_id = ?');
        $stmt->execute([$tokenId, $userId]);
    }

    // ---------------- 内部查询 ----------------

    private static function bearerToken(): ?string
    {
        $header = null;
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = (string) $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if ($header === null) {
            return null;
        }
        $m = [];
        if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
            return $m[1];
        }
        return null;
    }

    private static function userByToken(string $raw): ?array
    {
        $hash = hash('sha256', $raw);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT u.* FROM tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ?');
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $upd = $pdo->prepare("UPDATE tokens SET last_used = datetime('now') WHERE token_hash = ?");
        $upd->execute([$hash]);
        return $row;
    }

    private static function getUserById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private static function getUserByUsername(string $username): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function usernameExists(string $username): bool
    {
        return self::getUserByUsername($username) !== null;
    }

    public static function createUser(string $username, string $password, string $role = 'user'): array
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
        $stmt->execute([$username, $hash, $role]);
        return self::getUserById((int) $pdo->lastInsertId());
    }

    public static function updatePassword(int $userId, string $password): void
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $userId]);
    }

    public static function isApiRequest(): bool
    {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        return str_starts_with($path, '/api/')
            || str_starts_with($path, '/i/')
            || str_starts_with($path, '/t/')
            || str_starts_with($path, '/d/');
    }
}
