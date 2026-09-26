/* 图床前端交互：上传 / 画廊 / 复制 / 下载 / 删除 */
(function () {
    'use strict';
    var dash = document.querySelector('.dash');
    if (!dash) return;

    var CSRF = dash.getAttribute('data-csrf');
    var BASE = dash.getAttribute('data-base') || '';

    var dropzone = document.getElementById('dropzone');
    var fileInput = document.getElementById('fileInput');
    var gallery = document.getElementById('gallery');
    var emptyHint = document.getElementById('empty');
    var loadMore = document.getElementById('loadMore');
    var progress = document.getElementById('progress');

    var page = 1;
    var loading = false;

    // ---------- 上传 ----------
    dropzone.addEventListener('click', function () { fileInput.click(); });
    dropzone.addEventListener('dragover', function (e) { e.preventDefault(); dropzone.classList.add('drag'); });
    dropzone.addEventListener('dragleave', function () { dropzone.classList.remove('drag'); });
    dropzone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropzone.classList.remove('drag');
        if (e.dataTransfer && e.dataTransfer.files) uploadFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', function () { uploadFiles(fileInput.files); fileInput.value = ''; });

    function uploadFiles(files) {
        Array.prototype.forEach.call(files, function (f) {
            var fd = new FormData();
            fd.append('file', f);
            progress.classList.remove('hidden');
            progress.textContent = '上传中：' + f.name;
            fetch('/api/upload', {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF },
                body: fd,
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    prependItem(data);
                    progress.textContent = '上传成功：' + f.name;
                    bumpUsed(data.size || 0);
                } else {
                    progress.textContent = '失败：' + (data.message || '未知错误');
                }
            })
            .catch(function (e) { progress.textContent = '网络错误：' + e; });
        });
    }

    // ---------- 列表 ----------
    function loadImages(reset) {
        if (loading) return;
        loading = true;
        if (reset) page = 1;
        fetch('/api/images?page=' + page + '&perPage=24', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                loading = false;
                if (!data.success) return;
                if (page === 1) gallery.innerHTML = '';
                if (data.images.length === 0 && page === 1) {
                    emptyHint.style.display = 'block';
                } else {
                    emptyHint.style.display = 'none';
                }
                data.images.forEach(function (it) { appendItem(it); });
                var hasMore = (page * 24) < data.total;
                loadMore.hidden = !hasMore;
            })
            .catch(function () { loading = false; });
    }

    function appendItem(it) { gallery.appendChild(renderItem(it)); }
    function prependItem(it) { gallery.insertBefore(renderItem(it), gallery.firstChild); emptyHint.style.display = 'none'; }

    function renderItem(it) {
        var div = document.createElement('div');
        div.className = 'item';
        div.innerHTML =
            '<img class="thumb" src="' + esc(it.thumb || it.url) + '" loading="lazy" alt="">' +
            '<div class="meta">' + esc(it.width + '×' + it.height) + ' · ' + fmtSize(it.size) + '</div>' +
            '<div class="acts">' +
            '<button class="btn sm" data-act="copy">复制</button>' +
            '<a class="btn sm" href="' + esc(it.url) + '" target="_blank">打开</a>' +
            '<button class="btn danger sm" data-act="del">删除</button>' +
            '</div>';
        div.querySelector('[data-act="copy"]').addEventListener('click', function () {
            copy(it.url); this.textContent = '已复制';
            var self = this; setTimeout(function () { self.textContent = '复制'; }, 1200);
        });
        div.querySelector('[data-act="del"]').addEventListener('click', function () {
            if (!confirm('确认删除该图片？')) return;
            fetch('/api/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                credentials: 'same-origin',
                body: JSON.stringify({ id: it.id, _csrf: CSRF })
            })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d.success) div.remove(); });
        });
        return div;
    }

    loadMore.addEventListener('click', function () { page++; loadImages(false); });

    // ---------- 工具 ----------
    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    }); }
    function fmtSize(b) {
        b = Number(b) || 0; var u = ['B', 'KB', 'MB', 'GB']; var i = 0;
        while (b >= 1024 && i < 3) { b /= 1024; i++; } return (i ? b.toFixed(1) : b) + u[i];
    }
    function bumpUsed(delta) {
        var el = document.getElementById('used'); if (!el) return;
        el.textContent = (Number(el.textContent) + (delta || 0));
    }
    function copy(text) {
        if (navigator.clipboard) navigator.clipboard.writeText(text);
    }

    loadImages(true);
})();
