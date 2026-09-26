<?php /** @var string $error */ /** @var string $csrf */ ?>
<div class="card auth-card">
    <h2>注册账号</h2>
    <?php if ($error !== ''): ?><div class="alert"><?= \App\View::e($error) ?></div><?php endif; ?>
    <form method="post" action="/register">
        <input type="hidden" name="_csrf" value="<?= \App\View::e($csrf) ?>">
        <label>用户名（3-32 字符）
            <input name="username" autocomplete="username" required>
        </label>
        <label>密码（至少 8 位）
            <input type="password" name="password" autocomplete="new-password" required>
        </label>
        <label>确认密码
            <input type="password" name="confirm" autocomplete="new-password" required>
        </label>
        <button class="btn primary" type="submit">注册并登录</button>
    </form>
    <p class="hint"><a href="/login">已有账号？返回登录</a></p>
</div>
