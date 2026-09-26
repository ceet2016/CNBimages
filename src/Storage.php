<?php
/**
 * Storage.php —— 文件存储（落盘到 CNB 应用本地磁盘 + 数据库元数据 + 配额）
 *
 * 安全要点：
 *   - 文件名用随机串，绝不采用用户原始名（防遍历 / 枚举）
 *   - 用 getimagesize 验证真实图片（防伪装）
 *   - 仅把随机名作为路径，绝不拼接用户可控路径
 *   - 上传前校验单文件大小与用户配额
 */

declare(strict_types=1);

namespace App;

use PDO;

final class Storage
{
    private static function baseDir(): string
    {
        return rtrim((string) Config::get('LOCAL_STORAGE_DIR', '/var/www/uploads'), '/');
    }

    /** 校验并保存一张图片，写入数据库，返回图片记录 */
    public static function store(string $tmpPath, string $originalName, array $user): array
    {
        $info = ImageProcessor::info($tmpPath);
        if ($info === null) {
            Response::error('不是有效的图片文件', 2, 422);
        }

        $size = (int) filesize($tmpPath);
        $max = Config::getInt('MAX_FILE_SIZE', 10 * 1024 * 1024);
        if ($size > $max) {
            Response::error('文件超过大小限制（最大 ' . self::human($max) . '）', 2, 422);
        }

        // 配额校验
        $quota = (int) ($user['quota_bytes'] ?? 0);
        if ($quota > 0) {
            $used = self::usedBytes((int) $user['id']);
            if ($used + $size > $quota) {
                Response::error('存储空间不足（配额 ' . self::human($quota) . '）', 2, 422);
            }
        }

        $datePath = date('Y/m/d');
        $storedName = $datePath . '/' . bin2hex(random_bytes(16)) . '.' . $info['ext'];
        $dest = self::baseDir() . '/' . $storedName;
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!move_uploaded_file($tmpPath, $dest) && !rename($tmpPath, $dest)) {
            Response::error('保存文件失败', 3, 500);
        }
        @chmod($dest, 0644);

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO images (user_id, original_name, stored_name, thumb_name, ext, mime, size, width, height)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['id'], $originalName, $storedName, $info['ext'], $info['mime'],
            $size, $info['width'], $info['height'],
        ]);
        $id = (int) $pdo->lastInsertId();

        return self::getById($id);
    }

    /** 删除图片（普通用户仅能删自己的；管理员可删任意） */
    public static function delete(int $imageId, int $userId, bool $isAdmin): bool
    {
        $img = self::getById($imageId);
        if ($img === null) {
            return false;
        }
        if (!$isAdmin && (int) $img['user_id'] !== $userId) {
            return false;
        }
        self::unlink(self::baseDir() . '/' . $img['stored_name']);
        if (!empty($img['thumb_name'])) {
            self::unlink(self::baseDir() . '/' . $img['thumb_name']);
        }
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM images WHERE id = ?')->execute([$imageId]);
        return true;
    }

    public static function getById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM images WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** 通过随机名定位图片（供公开访问路由使用） */
    public static function getByStoredName(string $storedName): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM images WHERE stored_name = ?');
        $stmt->execute([$storedName]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function originalPath(string $storedName): ?string
    {
        $p = self::baseDir() . '/' . $storedName;
        return is_file($p) ? $p : null;
    }

    /** 列表（普通用户只看自己；管理员可看全部） */
    public static function list(int $userId, bool $isAdmin, int $page, int $perPage): array
    {
        $pdo = Database::pdo();
        $where = $isAdmin ? '' : 'WHERE user_id = ?';
        $params = $isAdmin ? [] : [$userId];
        $stmt = $pdo->prepare(
            "SELECT * FROM images $where ORDER BY id DESC LIMIT ? OFFSET ?"
        );
        $args = array_merge($params, [$perPage, max(0, ($page - 1) * $perPage)]);
        $stmt->execute($args);
        return $stmt->fetchAll();
    }

    public static function count(int $userId, bool $isAdmin): int
    {
        $pdo = Database::pdo();
        if ($isAdmin) {
            return (int) $pdo->query('SELECT COUNT(*) FROM images')->fetchColumn();
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM images WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function usedBytes(int $userId): int
    {
        $stmt = Database::pdo()->prepare('SELECT COALESCE(SUM(size),0) FROM images WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // ---------------- URL 构造 ----------------

    public static function publicUrl(string $storedName): string
    {
        return rtrim((string) Config::get('PUBLIC_BASE_URL', ''), '/') . '/i/' . $storedName;
    }

    public static function thumbUrl(string $storedName, int $w = 0, int $h = 0, string $fmt = ''): string
    {
        $base = rtrim((string) Config::get('PUBLIC_BASE_URL', ''), '/') . '/t/' . $storedName;
        $q = [];
        if ($w > 0) {
            $q['w'] = $w;
        }
        if ($h > 0) {
            $q['h'] = $h;
        }
        if ($fmt !== '') {
            $q['fmt'] = $fmt;
        }
        return $q === [] ? $base : $base . '?' . http_build_query($q);
    }

    private static function unlink(string $p): void
    {
        if (is_file($p)) {
            @unlink($p);
        }
    }

    private static function human(int $bytes): string
    {
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($u) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . $u[$i];
    }
}
