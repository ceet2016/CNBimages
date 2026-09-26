<?php
/**
 * AuthController.php —— 登录 / 注册 / 登出 / API Token 管理
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Csrf;
use App\Response;
use App\View;

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::sessionUser() !== null) {
            Response::redirect('/dashboard');
        }
        Response::html(View::render('layout', [
            'title' => '登录',
            'body'  => View::render('login', ['error' => '', 'csrf' => Csrf::token()]),
        ]));
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showLogin();
            return;
        }
        Csrf::requireValid();
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $user = Auth::loginWithPassword($username, $password);
        if ($user === null) {
            Response::html(View::render('layout', [
                'title' => '登录',
                'body'  => View::render('login', ['error' => '用户名或密码错误', 'csrf' => Csrf::token()]),
            ]));
        }
        Auth::loginUser($user);
        Response::redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect('/login');
    }

    public function showRegister(): void
    {
        if (!Config::getBool('ALLOW_REGISTER', false)) {
            Response::error('当前未开放注册', 403, 403);
        }
        if (Auth::sessionUser() !== null) {
            Response::redirect('/dashboard');
        }
        Response::html(View::render('layout', [
            'title' => '注册',
            'body'  => View::render('register', ['error' => '', 'csrf' => Csrf::token()]),
        ]));
    }

    public function register(): void
    {
        if (!Config::getBool('ALLOW_REGISTER', false)) {
            Response::error('当前未开放注册', 403, 403);
        }
        Csrf::requireValid();
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirm'] ?? '');

        if (mb_strlen($username) < 3 || mb_strlen($username) > 32) {
            $this->registerError('用户名长度需为 3-32 个字符');
        }
        if (mb_strlen($password) < 8) {
            $this->registerError('密码至少 8 位');
        }
        if ($password !== $confirm) {
            $this->registerError('两次输入的密码不一致');
        }
        if (Auth::usernameExists($username)) {
            $this->registerError('用户名已被占用');
        }
        $user = Auth::createUser($username, $password, 'user');
        Auth::loginUser($user);
        Response::redirect('/dashboard');
    }

    private function registerError(string $msg): void
    {
        Response::html(View::render('layout', [
            'title' => '注册',
            'body'  => View::render('register', ['error' => $msg, 'csrf' => Csrf::token()]),
        ]));
    }

    // ---------------- API Token ----------------

    public function tokensPage(): void
    {
        $user = Auth::requireLogin();
        Response::html(View::render('layout', [
            'title' => 'API Token',
            'body'  => View::render('tokens', [
                'csrf'   => Csrf::token(),
                'tokens' => Auth::listTokens($user['id']),
                'newToken' => '',
            ]),
        ]));
    }

    public function createToken(): void
    {
        $user = Auth::requireLogin();
        Csrf::requireValid();
        $name = trim((string) ($_POST['name'] ?? 'default'));
        $raw = Auth::createToken($user['id'], $name);
        Response::html(View::render('layout', [
            'title' => 'API Token',
            'body'  => View::render('tokens', [
                'csrf'   => Csrf::token(),
                'tokens' => Auth::listTokens($user['id']),
                'newToken' => $raw,
            ]),
        ]));
    }

    public function revokeToken(): void
    {
        $user = Auth::requireLogin();
        Csrf::requireValid();
        $id = (int) ($_POST['id'] ?? 0);
        Auth::revokeToken($id, $user['id']);
        Response::redirect('/tokens');
    }
}
