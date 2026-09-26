<?php
/**
 * ImageController.php —— 仪表盘 / 上传 / 列表 / 删除 / 原图 / 缩略转换 / 下载
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Csrf;
use App\ImageProcessor;
use App\RateLimit;
use App\Response;
use App\Storage;
use App\View;

final class ImageController
{
    public function dashboard(): void
    {
        $user = Auth::requireLogin();
        $used = Storage::usedBytes($user['id']);
        $total = (int) ($user['quota_bytes'] ?? 0);
        Response::html(View::render('layout', [
            'title' => '图床控制台',
            'body'  => View::render('dashboard', [
                'csrf'         => Csrf::token(),
                'username'      => $user['username'],
                'role'         => $user['role'],
                'used'         => $used,
                'quota'        => $total,
                'fmts'         => implode(',', ImageProcessor::supportedFmts()),
                'baseUrl'      => rtrim((string) Config::get('PUBLIC_BASE_URL', ''), '/'),
            ]),
        ]));
    }

    // ---------------- 上传（PicGo 与 UI 通用） ----------------

    public function apiUpload(): void
    {
        $user = Auth::resolveUser();
        if ($user === null) {
            Response::error('未授权：请登录或在请求头携带 Authorization: Bearer <TOKEN>', 401, 401);
        }
        // 会话态（UI）需 CSRF；Bearer Token（API）免 CSRF
        $isApi = isset($_SERVER['HTTP_AUTHORIZATION']) || isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        if (!$isApi) {
            Csrf::requireValid();
        }

        // 速率限制：每用户每分钟最多 30 次
        if (!RateLimit::hit('upload:' . $user['id'], 30, 60)) {
            Response::error('上传过于频繁，请稍后再试', 429, 429);
        }

        $file = $this->getUploadedFile();
        if ($file === null) {
            Response::error('未检测到上传文件（字段名应为 file）', 1, 400);
        }

        $img = Storage::store((string) $file['tmp_name'], (string) ($file['name'] ?? 'upload'), $user);

        $url = Storage::publicUrl($img['stored_name']);
        Response::success($url, [
            'id'        => $img['id'],
            'filename'  => $img['stored_name'],
            'width'     => $img['width'],
            'height'    => $img['height'],
            'size'      => $img['size'],
            'thumb_url' => Storage::thumbUrl($img['stored_name'], (int) Config::get('THUMB_MAX', 500)),
        ]);
    }

    public function apiList(): void
    {
        $user = Auth::resolveUser();
        if ($user === null) {
            Response::error('未授权', 401, 401);
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['perPage'] ?? 24)));
        $isAdmin = $user['role'] === 'admin';
        $rows = Storage::list($user['id'], $isAdmin, $page, $perPage);
        $items = array_map(static function ($r) {
            return [
                'id'        => $r['id'],
                'name'      => $r['stored_name'],
                'url'       => Storage::publicUrl($r['stored_name']),
                'thumb'     => Storage::thumbUrl($r['stored_name'], (int) Config::get('THUMB_MAX', 500)),
                'size'      => $r['size'],
                'width'     => $r['width'],
                'height'    => $r['height'],
                'created_at'=> $r['created_at'],
            ];
        }, $rows);
        Response::json([
            'success' => true,
            'page'    => $page,
            'total'   => Storage::count($user['id'], $isAdmin),
            'images'  => $items,
        ]);
    }

    public function apiDelete(): void
    {
        $user = Auth::resolveUser();
        if ($user === null) {
            Response::error('未授权', 401, 401);
        }
        $isApi = isset($_SERVER['HTTP_AUTHORIZATION']) || isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        if (!$isApi) {
            Csrf::requireValid();
        }
        $id = (int) ($_POST['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            Response::error('缺少图片 ID', 1, 400);
        }
        $ok = Storage::delete($id, (int) $user['id'], $user['role'] === 'admin');
        if (!$ok) {
            Response::error('删除失败（无权限或图片不存在）', 2, 403);
        }
        Response::success('deleted', ['id' => $id]);
    }

    // ---------------- 公开访问（被 EO/ESA 缓存加速） ----------------

    public function serveOriginal(string $storedName): void
    {
        $path = $this->safePath($storedName);
        if ($path === null) {
            Response::error('图片不存在', 404, 404);
        }
        $this->emitFile($path, false);
    }

    public function download(string $storedName): void
    {
        $path = $this->safePath($storedName);
        if ($path === null) {
            Response::error('图片不存在', 404, 404);
        }
        $this->emitFile($path, true);
    }

    /** 按需缩放 / 格式转换（带磁盘缓存） */
    public function serveThumb(string $storedName): void
    {
        $src = $this->safePath($storedName);
        if ($src === null) {
            Response::error('图片不存在', 404, 404);
        }
        $w = min(4000, max(0, (int) ($_GET['w'] ?? 0)));
        $h = min(4000, max(0, (int) ($_GET['h'] ?? 0)));
        $fmt = (string) ($_GET['fmt'] ?? '');
        $quality = min(100, max(1, (int) ($_GET['q'] ?? 85)));
        if (!in_array($fmt, ImageProcessor::supportedFmts(), true)) {
            $fmt = 'webp';
        }

        $cacheDir = rtrim((string) Config::get('CACHE_DIR', '/var/www/cache'), '/');
        $cacheKey = md5($storedName . '|' . $w . '|' . $h . '|' . $fmt . '|' . $quality);
        $cacheFile = $cacheDir . '/' . $cacheKey . '.' . $fmt;

        if (!is_file($cacheFile)) {
            if (!ImageProcessor::resize($src, $cacheFile, $w, $h, $fmt, $quality)) {
                // 处理失败则回退原图
                $this->emitFile($src, false);
                return;
            }
        }
        $this->emitFile($cacheFile, false);
    }

    // ---------------- 内部 ----------------

    private function safePath(string $storedName): ?string
    {
        $clean = ltrim($storedName, '/');
        if ($clean === '' || str_contains($clean, '..') || str_contains($clean, "\0")) {
            return null;
        }
        $base = realpath(rtrim((string) Config::get('LOCAL_STORAGE_DIR', '/var/www/uploads'), '/'));
        if ($base === false) {
            return null;
        }
        $target = realpath($base . '/' . $clean);
        if ($target === false || strpos($target, $base . '/') !== 0 || !is_file($target)) {
            return null;
        }
        return $target;
    }

    private function emitFile(string $path, bool $download): void
    {
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=2592000, immutable');
        if ($download) {
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        }
        readfile($path);
        exit;
    }

    /** 从 $_FILES 或原始 body 获取上传内容 */
    private function getUploadedFile(): ?array
    {
        foreach (['file', 'image', 'upload', 'pic'] as $field) {
            if (isset($_FILES[$field]) && is_array($_FILES[$field]) && !empty($_FILES[$field]['tmp_name'])) {
                return $_FILES[$field];
            }
        }
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $tmp = tempnam(sys_get_temp_dir(), 'up_');
            if ($tmp === false || file_put_contents($tmp, $raw) === false) {
                return null;
            }
            return [
                'name'     => 'raw_' . time() . '.bin',
                'type'     => (string) ($_SERVER['CONTENT_TYPE'] ?? 'application/octet-stream'),
                'tmp_name' => $tmp,
                'error'    => UPLOAD_ERR_OK,
                'size'     => strlen($raw),
            ];
        }
        return null;
    }
}
