<?php
/** @var string $csrf */
/** @var string $username */
/** @var string $role */
/** @var int $used */
/** @var int $quota */
/** @var string $fmts */
/** @var string $baseUrl */
?>
<div class="dash" data-csrf="<?= \App\View::e($csrf) ?>" data-fmts="<?= \App\View::e($fmts) ?>" data-base="<?= \App\View::e($baseUrl) ?>">
    <section class="card uploader">
        <h2>上传图片</h2>
        <div id="dropzone" class="dropzone">
            <p>把图片拖到这里，或 <span class="link">点击选择</span></p>
            <input type="file" id="fileInput" accept="image/*" multiple hidden>
        </div>
        <div id="progress" class="progress hidden"></div>
        <p class="hint">支持 <?= \App\View::e($fmts) ?>；单文件最大 <?= \App\View::e(\App\Config::get('MAX_FILE_SIZE', 10485760) / 1048576) ?>MB。
            也可用 PicGo 通过 API Token 上传（设置 → API Token）。</p>
    </section>

    <section class="card">
        <div class="row-between">
            <h2>我的图片</h2>
            <span class="quota">已用 <b id="used"><?= $used ?></b> / <?= $quota > 0 ? $quota : '不限' ?> 字节</span>
        </div>
        <div id="gallery" class="gallery"></div>
        <div id="empty" class="empty">还没有图片，先上传一张吧。</div>
        <button id="loadMore" class="btn" hidden>加载更多</button>
    </section>
</div>

<script src="/assets/app.js"></script>
