<?php /** @var string $error */ /** @var string $csrf */ ?>
<div class="card auth-card">
    <h2>登录到图床</h2>
    <?php if ($error !== ''): ?><div class="alert"><?= \App\View::e($error) ?></div><?php endif; ?>
    <form method="post" action="/login">
        <input type="hidden" name="_csrf" value="<?= \App\View::e($csrf) ?>">
        <label>用户名
            <input name="username" autocomplete="username" required>
        </label>
        <label>密码
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="btn primary" type="submit">登录</button>
    </form>
    <?php if (\App\Config::getBool('ALLOW_REGISTER', false)): ?>
        <p class="hint"><a href="/register">还没有账号？点击注册</a></p>
    <?php endif; ?>
</div>
