<?php
/**
 * index.php —— 唯一入口（前端控制器 / 路由分发）
 *
 * 路由一览：
 *   页面：GET /  /login  /register  /dashboard  /tokens  /admin
 *   API ：POST /api/upload   GET /api/images   POST|DELETE /api/delete
 *   资源：GET /i/<name> 原图   GET /t/<name> 缩放转换   GET /d/<name> 下载
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\ImageController;
use App\Response;

$path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

$auth = new AuthController();
$img = new ImageController();
$admin = new AdminController();

// 公开资源（被 EO/ESA 缓存）
if (preg_match('#^/i/(.+)$#', $path, $m)) {
    $img->serveOriginal(urldecode($m[1]));
    return;
}
if (preg_match('#^/t/(.+)$#', $path, $m)) {
    $img->serveThumb(urldecode($m[1]));
    return;
}
if (preg_match('#^/d/(.+)$#', $path, $m)) {
    $img->download(urldecode($m[1]));
    return;
}

// 认证无关页面
if ($path === '/' || $path === '/login') {
    if ($method === 'POST') { $auth->login(); return; }
    $auth->showLogin();
    return;
}
if ($path === '/logout') {
    $auth->logout();
    return;
}
if ($path === '/register') {
    if ($method === 'POST') { $auth->register(); return; }
    $auth->showRegister();
    return;
}

// 登录态页面
if ($path === '/dashboard') {
    $img->dashboard();
    return;
}
if ($path === '/tokens') {
    if ($method === 'POST') { $auth->createToken(); return; }
    $auth->tokensPage();
    return;
}
if ($path === '/tokens/revoke') {
    $auth->revokeToken();
    return;
}

// API
if ($path === '/api/upload') {
    $img->apiUpload();
    return;
}
if ($path === '/api/images') {
    $img->apiList();
    return;
}
if ($path === '/api/delete' || preg_match('#^/api/images/(\d+)$#', $path, $m)) {
    $img->apiDelete();
    return;
}

// 管理后台
if ($path === '/admin') {
    if ($method === 'POST') { $admin->deleteImage(); return; }
    $admin->panel();
    return;
}
if ($path === '/admin/delete-image') {
    $admin->deleteImage();
    return;
}
if ($path === '/admin/set-quota') {
    $admin->setQuota();
    return;
}
if ($path === '/admin/delete-user') {
    $admin->deleteUser();
    return;
}

Response::error('页面不存在', 404, 404);
