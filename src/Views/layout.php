<?php
/** @var string $title */
/** @var string $body */
$user = \App\Auth::sessionUser();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= \App\View::e($title ?? 'CNB 图床') ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="topbar">
    <div class="brand">🖼️ CNB 图床</div>
    <?php if ($user !== null): ?>
    <nav>
        <a href="/dashboard">控制台</a>
        <a href="/tokens">API Token</a>
        <?php if ($user['role'] === 'admin'): ?><a href="/admin">管理后台</a><?php endif; ?>
        <span class="user"><?= \App\View::e($user['username']) ?></span>
        <a href="/logout">退出</a>
    </nav>
    <?php endif; ?>
</header>
<main class="container">
    <?= $body ?>
</main>
<footer class="foot">CNB + EdgeOne/ESA + PHP 自建图床 · MIT</footer>
</body>
</html>
