<?php
/**
 * AdminController.php —— 管理员控制台（统计 / 删除图片 / 配额 / 删除用户）
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Csrf;
use App\Database;
use App\Response;
use App\Storage;
use App\View;

final class AdminController
{
    public function panel(): void
    {
        $admin = Auth::requireAdmin();
        $pdo = Database::pdo();

        $totalImages = (int) $pdo->query('SELECT COUNT(*) FROM images')->fetchColumn();
        $totalSize = (int) $pdo->query('SELECT COALESCE(SUM(size),0) FROM images')->fetchColumn();
        $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $users = $pdo->query('SELECT id, username, role, quota_bytes, created_at FROM users ORDER BY id')->fetchAll();
        $recent = Storage::list($admin['id'], true, 1, 30);

        Response::html(View::render('layout', [
            'title' => '管理后台',
            'body'  => View::render('admin', [
                'csrf'        => Csrf::token(),
                'totalImages' => $totalImages,
                'totalSize'   => $totalSize,
                'totalUsers'  => $totalUsers,
                'users'       => $users,
                'recent'      => $recent,
                'allowReg'    => Config::getBool('ALLOW_REGISTER', false),
            ]),
        ]));
    }

    public function deleteImage(): void
    {
        Auth::requireAdmin();
        Csrf::requireValid();
        $id = (int) ($_POST['id'] ?? 0);
        Storage::delete($id, 0, true);
        Response::redirect('/admin');
    }

    public function setQuota(): void
    {
        Auth::requireAdmin();
        Csrf::requireValid();
        $userId = (int) ($_POST['user_id'] ?? 0);
        $quota = self::parseBytes((string) ($_POST['quota'] ?? '0'));
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE users SET quota_bytes = ? WHERE id = ?')->execute([$quota, $userId]);
        Response::redirect('/admin');
    }

    public function deleteUser(): void
    {
        Auth::requireAdmin();
        Csrf::requireValid();
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            Response::redirect('/admin');
        }
        $pdo = Database::pdo();
        // 先清理该用户的图片文件
        $rows = $pdo->prepare('SELECT stored_name, thumb_name FROM images WHERE user_id = ?');
        $rows->execute([$userId]);
        $base = rtrim((string) Config::get('LOCAL_STORAGE_DIR', '/var/www/uploads'), '/');
        foreach ($rows->fetchAll() as $r) {
            foreach ([$r['stored_name'], $r['thumb_name']] as $n) {
                if (!empty($n) && is_file($base . '/' . $n)) {
                    @unlink($base . '/' . $n);
                }
            }
        }
        // 删除用户（外键级联删除图片元数据与 token）
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        Response::redirect('/admin');
    }

    private static function parseBytes(string $s): int
    {
        $s = trim($s);
        if ($s === '' || $s === '0') {
            return 0;
        }
        $units = ['k' => 1, 'm' => 2, 'g' => 3, 't' => 4];
        $last = strtolower(substr($s, -1));
        $num = (float) $s;
        if (isset($units[$last])) {
            $num *= 1024 ** $units[$last];
        }
        return (int) $num;
    }
}
