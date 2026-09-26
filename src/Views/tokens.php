<?php
/** @var string $csrf */
/** @var array $tokens */
/** @var string $newToken */
?>
<section class="card">
    <h2>API Token 管理</h2>
    <p class="hint">Token 用于 PicGo / 程序调用 <code>POST /api/upload</code>，请求头携带 <code>Authorization: Bearer &lt;TOKEN&gt;</code>。服务端仅保存哈希。</p>

    <?php if ($newToken !== ''): ?>
    <div class="alert ok">
        新 Token 已生成（仅显示一次，请妥善保存）：<br>
        <code class="token"><?= \App\View::e($newToken) ?></code>
    </div>
    <?php endif; ?>

    <form method="post" action="/tokens" class="inline-form">
        <input type="hidden" name="_csrf" value="<?= \App\View::e($csrf) ?>">
        <input name="name" placeholder="备注名（如：PicGo-笔记本）">
        <button class="btn primary" type="submit">创建 Token</button>
    </form>

    <table class="tbl">
        <thead><tr><th>名称</th><th>创建时间</th><th>最近使用</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($tokens as $t): ?>
            <tr>
                <td><?= \App\View::e($t['name']) ?></td>
                <td><?= \App\View::e($t['created_at']) ?></td>
                <td><?= \App\View::e($t['last_used'] ?? '从未') ?></td>
                <td>
                    <form method="post" action="/tokens/revoke" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= \App\View::e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button class="btn danger sm" type="submit">吊销</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (count($tokens) === 0): ?>
            <tr><td colspan="4" class="empty">暂无 Token</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
