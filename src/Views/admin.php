<?php
/** @var string $csrf */
/** @var int $totalImages */
/** @var int $totalSize */
/** @var int $totalUsers */
/** @var array $users */
/** @var array $recent */
/** @var bool $allowReg */
?>
<section class="card">
    <h2>管理后台</h2>
    <div class="stats">
        <div class="stat"><b><?= $totalImages ?></b><span>图片总数</span></div>
        <div class="stat"><b><?= $totalSize ?></b><span>占用字节</span></div>
        <div class="stat"><b><?= $totalUsers ?></b><span>用户数</span></div>
        <div class="stat"><b><?= $allowReg ? '开放' : '关闭' ?></b><span>注册开关</span></div>
    </div>
</section>

<section class="card">
    <h2>用户管理</h2>
    <table class="tbl">
        <thead><tr><th>ID</th><th>用户名</th><th>角色</th><th>配额(字节)</th><th>创建时间</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= \App\View::e($u['username']) ?></td>
                <td><?= \App\View::e($u['role']) ?></td>
                <td><?= $u['quota_bytes'] ?></td>
                <td><?= \App\View::e($u['created_at']) ?></td>
                <td>
                    <form method="post" action="/admin/set-quota" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= \App\View::e($csrf) ?>">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input class="sm" name="quota" placeholder="如 5G / 0=不限">
                        <button class="btn sm" type="submit">设配额</button>
                    </form>
                    <form method="post" action="/admin/delete-user" class="inline-form"
                          onsubmit="return confirm('确认删除该用户及其所有图片？');">
                        <input type="hidden" name="_csrf" value="<?= \App\View::e($csrf) ?>">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button class="btn danger sm" type="submit">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="card">
    <h2>最近图片（可在此删除）</h2>
    <table class="tbl">
        <thead><tr><th>ID</th><th>文件</th><th>大小</th><th>时间</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
            <tr>
                <td><?= $r['id'] ?></td>
                <td><a href="<?= \App\View::e(\App\Storage::publicUrl($r['stored_name'])) ?>" target="_blank"><?= \App\View::e($r['stored_name']) ?></a></td>
                <td><?= $r['size'] ?></td>
                <td><?= \App\View::e($r['created_at']) ?></td>
                <td>
                    <form method="post" action="/admin/delete-image" class="inline-form"
                          onsubmit="return confirm('确认删除该图片？');">
                        <input type="hidden" name="_csrf" value="<?= \App\View::e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button class="btn danger sm" type="submit">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (count($recent) === 0): ?>
            <tr><td colspan="5" class="empty">暂无图片</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
