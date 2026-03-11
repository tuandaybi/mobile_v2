<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Server</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.cdnfonts.com/css/sf-pro-display');
        body { font-family: 'SF Pro Display', sans-serif; background: linear-gradient(135deg, #cbd5e1 0%, #f8fafc 100%); background-attachment: fixed; overflow: hidden; }
        .apple-glass { background: rgba(255, 255, 255, 0.45); backdrop-filter: blur(30px) saturate(180%); border: 1px solid rgba(255, 255, 255, 0.3); }
        #modalOverlay { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); backdrop-filter: blur(0px); pointer-events: none; opacity: 0; z-index: 100; }
        #modalOverlay.active { backdrop-filter: blur(15px); pointer-events: auto; opacity: 1; }
        .nav-btn.active { background: rgba(255, 255, 255, 0.6); color: #2563eb; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        #toast { transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1); transform: translateY(100px); opacity: 0; }
        #toast.show { transform: translateY(0); opacity: 1; }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .modal-scroll { max-height: 85vh; overflow-y: auto; scrollbar-width: none; }
        .modal-scroll::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="h-screen p-4 md:p-8">
    <div id="app" class="w-full mx-auto h-full flex apple-glass rounded-[40px] shadow-2xl overflow-hidden relative border border-white/40" data-list-url="{{ $listUrl }}" data-publish-url="{{ $publishUrl }}" data-trash-url="{{ $trashUrl }}">
        <aside class="w-20 md:w-64 border-r border-white/20 flex flex-col p-6 space-y-4">
            <div class="flex items-center gap-3 px-2 mb-8 text-blue-600">
                <div class="w-10 h-10 bg-blue-600 rounded-2xl flex items-center justify-center text-white shadow-lg"><i data-lucide="layers"></i></div>
                <span class="hidden md:block font-bold text-xl tracking-tighter text-gray-900 uppercase">File Server</span>
            </div>
            <nav class="space-y-2 flex-1">
                <button id="btn-recent" onclick="showTab('recent')" class="nav-btn w-full flex items-center gap-3 p-3 text-gray-500 hover:bg-white/30 rounded-2xl font-bold transition">
                    <i data-lucide="clock"></i> <span class="hidden md:block text-sm">Gần đây</span>
                </button>
                <button id="btn-projects" onclick="showTab('projects')" class="nav-btn w-full flex items-center gap-3 p-3 text-gray-500 hover:bg-white/30 rounded-2xl font-bold transition">
                    <i data-lucide="folder"></i> <span class="hidden md:block text-sm">Dự án</span>
                </button>
            </nav>
            <nav class="pt-4 border-t border-white/20">
                <button id="btn-trash" onclick="showTab('trash')" class="nav-btn w-full flex items-center gap-3 p-3 text-rose-500 hover:bg-rose-50 rounded-2xl font-bold transition">
                    <i data-lucide="trash-2"></i> <span class="hidden md:block text-sm">Đã xóa</span>
                </button>
            </nav>
        </aside>

        <main class="flex-1 flex flex-col overflow-hidden">
            <header class="h-24 flex items-center justify-between px-10">
                <div id="headerTitle"></div>
                <div class="flex items-center gap-3">
                    <div class="hidden md:flex items-center gap-2 rounded-full bg-white/40 px-4 py-2 text-xs font-bold uppercase tracking-[0.2em] text-slate-500">
                        <span id="releaseCount">0</span>
                        <span>bản phát hành</span>
                    </div>
                    <button id="refreshBtn" type="button" class="rounded-full border border-white/60 bg-white/40 px-4 py-2 text-xs font-bold uppercase tracking-[0.15em] text-slate-500 hover:bg-white/70 transition">
                        Làm mới
                    </button>
                    <button onclick="toggleModal(true)" class="bg-blue-600 text-white w-14 h-14 rounded-full flex items-center justify-center shadow-lg hover:scale-110 active:scale-95 transition">
                        <i data-lucide="plus" class="w-7 h-7"></i>
                    </button>
                </div>
            </header>
            <div id="mainContent" class="flex-1 overflow-y-auto px-10 pb-10"></div>
        </main>
    </div>

    <div id="modalOverlay" class="fixed inset-0 flex items-center justify-center bg-black/20 p-4">
        <div class="absolute inset-0" onclick="toggleModal(false)"></div>
        <div class="apple-glass w-full max-w-2xl rounded-[40px] shadow-2xl relative z-10 border border-white/50 p-8 modal-scroll fade-in">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Tải lên</p>
                    <h3 class="mt-2 text-2xl font-bold text-slate-900">Phát hành phiên bản</h3>
                </div>
                <div class="rounded-2xl bg-blue-50 p-3 text-blue-600 shadow-inner">
                    <i data-lucide="upload-cloud" class="w-6 h-6"></i>
                </div>
            </div>

            <form id="uploadForm" class="space-y-5">
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Security Code</label>
                    <input id="token" type="password" placeholder="..." required class="w-full rounded-2xl border border-white/60 bg-white/40 px-4 py-3 outline-none focus:border-blue-400 transition shadow-sm">
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Mã ứng dụng</label>
                        <input id="appSlug" type="text" value="tiktok-bot" required class="w-full rounded-2xl border border-white/60 bg-white/40 px-4 py-3 outline-none focus:border-blue-400 transition font-bold">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Kênh</label>
                        <input id="channel" type="text" value="app" required class="w-full rounded-2xl border border-white/60 bg-white/40 px-4 py-3 outline-none focus:border-blue-400 transition">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Phiên bản</label>
                        <input id="version" type="text" value="1.0.0" required class="w-full rounded-2xl border border-white/60 bg-white/40 px-4 py-3 outline-none focus:border-blue-400 transition font-bold text-blue-600">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Bắt buộc</label>
                        <input id="mandatory" type="text" value="1" placeholder="1 hoặc 0" class="w-full rounded-2xl border border-white/60 bg-white/40 px-4 py-3 outline-none focus:border-blue-400 transition">
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Ghi chú</label>
                    <textarea id="notes" class="min-h-[100px] w-full rounded-2xl border border-white/60 bg-white/40 px-4 py-3 outline-none focus:border-blue-400 transition">Phiên bản được tải lên từ bảng điều khiển cập nhật.</textarea>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Tệp EXE</label>
                    <input id="file" type="file" accept=".exe" required class="w-full rounded-2xl border border-white/60 bg-white/40 px-4 py-3 file:mr-4 file:rounded-xl file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:font-semibold file:text-white cursor-pointer">
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <button id="uploadBtn" type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-6 py-4 text-sm font-bold text-white shadow-lg hover:bg-black active:scale-95 transition-all uppercase tracking-wide">
                        <i data-lucide="upload" class="w-4 h-4"></i>
                        Tải bản phát hành
                    </button>
                </div>
            </form>
            <pre id="uploadOutput" class="hidden mt-6 overflow-auto rounded-2xl bg-slate-950 p-4 text-xs text-slate-100"></pre>
            <button onclick="toggleModal(false)" class="absolute top-8 right-8 text-slate-400 hover:text-slate-900 transition"><i data-lucide="x"></i></button>
        </div>
    </div>

    <div id="toast" class="fixed bottom-10 left-1/2 -translate-x-1/2 apple-glass px-6 py-3 rounded-full shadow-2xl border border-white/50 z-[200] flex items-center gap-2">
        <div id="toastIcon" class="w-6 h-6 bg-emerald-500 text-white rounded-full flex items-center justify-center"><i data-lucide="check" class="w-4 h-4"></i></div>
        <span id="toastMsg" class="font-bold text-gray-800 text-sm">Xong</span>
    </div>

    <script>
        const root = document.getElementById('app');
        const listUrl = root.dataset.listUrl;
        const publishUrl = root.dataset.publishUrl;
        const trashUrl = root.dataset.trashUrl || '/api/admin/app-updates/trash';
        const stateKey = 'update-dashboard-state';
        let releases = [];
        let trash = [];

        const getEl = (id) => document.getElementById(id);
        const getToken = () => getEl('token').value.trim();
        const toggleModal = (show) => getEl('modalOverlay').classList.toggle('active', show);
        const renderIcons = () => window.lucide?.createIcons?.();

        function showToast(msg, type = 'success') {
            getEl('toastMsg').innerText = msg;
            const iconBox = getEl('toastIcon');
            iconBox.className = 'w-6 h-6 text-white rounded-full flex items-center justify-center ' + (type === 'success' ? 'bg-emerald-500' : 'bg-rose-500');
            iconBox.innerHTML = `<i data-lucide="${type === 'success' ? 'check' : 'x'}" class="w-4 h-4"></i>`;
            renderIcons();
            const toast = getEl('toast');
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2200);
        }

        function persistState() {
            localStorage.setItem(stateKey, JSON.stringify({
                appSlug: getEl('appSlug').value,
                channel: getEl('channel').value,
                version: getEl('version').value,
                mandatory: getEl('mandatory').value,
                notes: getEl('notes').value,
            }));
        }
        function hydrateState() {
            try {
                const state = JSON.parse(localStorage.getItem(stateKey) || '{}');
                ['appSlug', 'channel', 'version', 'mandatory', 'notes'].forEach((key) => {
                    if (state[key] !== undefined && getEl(key)) getEl(key).value = state[key];
                });
            } catch {}
        }
        async function parseResponse(response) {
            const raw = await response.text();
            try { return JSON.parse(raw); } catch { return raw; }
        }
        function showOutput(el, payload) {
            if (!el) return;
            el.classList.remove('hidden');
            el.textContent = typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2);
        }
        function hideOutput(el) { if (el) { el.classList.add('hidden'); el.textContent = ''; } }
        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const parsed = new Date(dateStr);
            return Number.isNaN(parsed.getTime()) ? dateStr : parsed.toLocaleDateString('vi-VN');
        }
        function daysLeft(dateStr) {
            if (!dateStr) return '-';
            const parsed = new Date(dateStr);
            if (Number.isNaN(parsed.getTime())) return '-';
            return Math.max(0, Math.ceil((parsed - Date.now()) / 86400000));
        }
        function updateCounts() {
            const count = releases.length;
            const appCount = new Set(releases.map((item) => item.app_slug)).size;
            getEl('releaseCount').innerText = count;
            const releaseCountAside = getEl('releaseCountAside');
            const appCountAside = getEl('appCountAside');
            if (releaseCountAside) releaseCountAside.innerText = count;
            if (appCountAside) appCountAside.innerText = appCount;
        }
        async function loadReleases(showError = true) {
            if (!getToken()) {
                releases = [];
                updateCounts();
                if (showError) showToast('Nhập mã Bearer để tải dữ liệu', 'error');
                return false;
            }
            const response = await fetch(listUrl, { headers: { Accept: 'application/json', Authorization: 'Bearer ' + getToken() } });
            const payload = await parseResponse(response);
            if (!response.ok) {
                if (showError) showToast('Không tải được dữ liệu. HTTP ' + response.status, 'error');
                return false;
            }
            releases = payload.releases || [];
            updateCounts();
            return true;
        }
        async function loadTrash(showError = false) {
            if (!getToken()) { trash = []; return false; }
            const response = await fetch(trashUrl, { headers: { Accept: 'application/json', Authorization: 'Bearer ' + getToken() } });
            const payload = await parseResponse(response);
            if (!response.ok) {
                if (showError) showToast('Không tải được thùng rác. HTTP ' + response.status, 'error');
                return false;
            }
            trash = (payload.trash || []).map((item) => ({
                label: item.label || `${item.app_slug}/${item.channel}`,
                deletedAt: formatDate(item.deleted_at),
                purgeAfterRaw: item.purge_after,
                purgeAfter: formatDate(item.purge_after),
                restoreUrl: item.restore_url,
            }));
            return true;
        }
        function statusBadge(release) {
            return release.mandatory
                ? `<span class="px-2 py-1 bg-rose-100 text-rose-700 text-[10px] font-bold rounded-full uppercase">Bắt buộc</span>`
                : `<span class="px-2 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-full uppercase">Tùy chọn</span>`;
        }
        function fileIcon(channel) {
            if (channel === 'bot-server') return 'server';
            if (channel === 'app') return 'file-code';
            return 'file';
        }

        function renderRecent() {
            document.getElementById('headerTitle').innerHTML = `<h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Gần đây</h2>`;
            let html = `<div class="apple-glass rounded-[35px] overflow-hidden shadow-xl border border-white/40 fade-in"><table class="w-full text-left"><thead class="bg-white/30 border-b border-white/20 text-[10px] uppercase tracking-widest text-gray-400 font-bold"><tr><th class="px-8 py-5">Tệp</th><th class="px-8 py-5">Ngày tải</th><th class="px-8 py-5 text-center">Kích thước</th><th class="px-8 py-5 text-center">Trạng thái</th><th class="px-8 py-5 text-right">Thao tác</th></tr></thead><tbody class="divide-y divide-white/20">`;
            releases.forEach((f) => {
                html += `<tr class="hover:bg-white/40 group transition"><td class="px-8 py-5 flex items-center gap-4"><div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-sm text-blue-500"><i data-lucide="${fileIcon(f.channel)}"></i></div><div><p class="font-bold text-gray-900 whitespace-nowrap" title="${f.filename}">${f.filename}</p><span class="text-[10px] bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full font-bold uppercase">${f.app_slug} / ${f.channel}</span><p class="text-[10px] text-gray-400 mt-1">v${f.version}</p></div></td><td class="px-8 py-5 text-sm text-gray-500 font-medium whitespace-nowrap">${f.published_at || '-'}</td><td class="px-8 py-5 text-center text-emerald-600 font-bold text-sm">${(f.size / 1048576).toFixed(2)} MB</td><td class="px-8 py-5 text-center">${statusBadge(f)}</td><td class="px-8 py-5 text-right"><div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition"><button onclick="copyLink('${f.download_url}')" class="w-9 h-9 rounded-full bg-white text-gray-400 hover:text-gray-900 flex items-center justify-center shadow-sm border border-gray-100"><i data-lucide="copy" class="w-4 h-4"></i></button><a href="${f.latest_url}" target="_blank" rel="noopener" class="w-9 h-9 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center hover:bg-amber-500 hover:text-white transition shadow-sm"><i data-lucide="file-text" class="w-4 h-4"></i></a><a href="${f.download_url}" class="w-9 h-9 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition"><i data-lucide="download" class="w-4 h-4"></i></a><button onclick="moveToTrash('${f.delete_url}', '${f.app_slug}/${f.channel}')" class="w-9 h-9 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-500 hover:text-white transition shadow-sm"><i data-lucide="trash-2" class="w-4 h-4"></i></button></div></td></tr>`;
            });
            if (!releases.length) html += `<tr><td colspan="5" class="px-8 py-20 text-center text-gray-400 font-bold">Chưa có bản phát hành nào</td></tr>`;
            document.getElementById('mainContent').innerHTML = html + `</tbody></table></div>`;
            renderIcons();
        }

        function renderProjects() {
            document.getElementById('headerTitle').innerHTML = `<h2 class="text-3xl font-extrabold text-gray-900 tracking-tight uppercase">Dự án của tôi</h2>`;
            const grouped = releases.reduce((acc, item) => {
                acc[item.app_slug] = acc[item.app_slug] || [];
                acc[item.app_slug].push(item);
                return acc;
            }, {});
            let html = `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 fade-in">`;
            Object.keys(grouped).forEach((slug) => {
                html += `<div onclick="openFolder('${slug}')" class="p-8 apple-glass rounded-[35px] cursor-pointer hover:bg-white/70 transition-all hover:-translate-y-2 group border border-white/50"><div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-6"><i data-lucide="folder"></i></div><h3 class="text-xl font-bold uppercase tracking-tight">${slug}</h3><p class="text-gray-400 text-sm font-medium mt-1">${grouped[slug].length} phiên bản</p></div>`;
            });
            if (!Object.keys(grouped).length) html = `<div class="text-center py-20 text-gray-400 font-bold w-full">Chưa có dự án nào</div>`;
            document.getElementById('mainContent').innerHTML = html + `</div>`;
            renderIcons();
        }

        function openFolder(slug) {
            document.getElementById('headerTitle').innerHTML = `<p class="text-[10px] font-bold text-blue-600 uppercase tracking-widest mb-1">Dự án</p><h2 class="text-3xl font-extrabold text-gray-900 uppercase tracking-tight">${slug}</h2>`;
            const items = releases.filter((item) => item.app_slug === slug);
            let html = `<button onclick="renderProjects()" class="flex items-center gap-2 text-blue-600 font-bold mb-6 hover:underline transition"><i data-lucide="chevron-left" class="w-5 h-5"></i> Quay lại</button><div class="apple-glass rounded-[35px] overflow-hidden shadow-xl border border-white/40 fade-in"><table class="w-full text-left"><thead class="bg-white/30 border-b border-white/20 text-[10px] uppercase tracking-widest text-gray-400 font-bold"><tr><th class="px-8 py-5">Tên tệp</th><th class="px-8 py-5">Ngày tải</th><th class="px-8 py-5 text-center">JSON</th><th class="px-8 py-5 text-right">Thao tác</th></tr></thead><tbody class="divide-y divide-white/20">`;
            items.forEach((f) => {
                html += `<tr class="hover:bg-white/40 transition group"><td class="px-8 py-5 flex items-center gap-4"><div class="w-10 h-10 bg-white shadow-sm rounded-xl flex items-center justify-center text-blue-500"><i data-lucide="${fileIcon(f.channel)}"></i></div><div><p class="font-bold text-gray-900 whitespace-nowrap" title="${f.filename}">${f.filename}</p><p class="text-[10px] text-blue-600 font-bold uppercase">${f.channel} / phiên bản ${f.version}</p></div></td><td class="px-8 py-5 text-sm text-gray-500 font-semibold whitespace-nowrap">${f.published_at || '-'}</td><td class="px-8 py-5 text-center"><a href="${f.latest_url}" target="_blank" rel="noopener" class="text-blue-600 font-bold text-sm hover:underline">Mở</a></td><td class="px-8 py-5 text-right"><div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition"><button onclick="copyLink('${f.download_url}')" class="w-9 h-9 rounded-full bg-white text-gray-400 hover:text-gray-900 flex items-center justify-center shadow-sm border border-gray-100"><i data-lucide="copy" class="w-4 h-4"></i></button><a href="${f.download_url}" class="w-9 h-9 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition shadow-sm"><i data-lucide="download" class="w-4 h-4"></i></a><button onclick="moveToTrash('${f.delete_url}', '${f.app_slug}/${f.channel}')" class="w-9 h-9 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-500 hover:text-white transition shadow-sm"><i data-lucide="trash-2" class="w-4 h-4"></i></button></div></td></tr>`;
            });
            if (!items.length) html += `<tr><td colspan="4" class="px-8 py-20 text-center text-gray-400 font-bold">Không có tệp nào</td></tr>`;
            document.getElementById('mainContent').innerHTML = html + `</tbody></table></div>`;
            renderIcons();
        }

        function renderTrash() {
            document.getElementById('headerTitle').innerHTML = `<h2 class="text-3xl font-extrabold text-gray-900 tracking-tight text-rose-600 uppercase">Thùng rác</h2>`;
            let html = `<div class="apple-glass rounded-[35px] overflow-hidden shadow-xl border border-rose-100 fade-in"><table class="w-full text-left"><thead class="bg-rose-50/50 border-b border-rose-100 text-[10px] uppercase tracking-widest text-rose-400 font-bold"><tr><th class="px-8 py-5">Tên tệp</th><th class="px-8 py-5 text-center">Ngày xóa</th><th class="px-8 py-5 text-center">Còn lại</th><th class="px-8 py-5 text-right">Khôi phục</th></tr></thead><tbody class="divide-y divide-rose-50">`;
            trash.forEach((f) => {
                const days = daysLeft(f.purgeAfterRaw);
                const daysLabel = days === '-' ? '-' : `${days} ngày`;
                const desc = days === '-' ? 'Không xác định' : `Còn ${days} ngày trước khi xóa vĩnh viễn`;
                html += `<tr class="hover:bg-rose-50/20 group transition"><td class="px-8 py-5 flex items-center gap-4"><div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-sm text-gray-400"><i data-lucide="file-x"></i></div><div><p class="font-bold text-gray-500 line-through">${f.label}</p><p class="text-[10px] text-gray-400 uppercase">${desc}</p></div></td><td class="px-8 py-5 text-center text-rose-400 font-bold text-sm uppercase">${f.deletedAt}</td><td class="px-8 py-5 text-center text-gray-600 font-bold text-sm uppercase">${daysLabel}</td><td class="px-8 py-5 text-right"><button onclick="restoreFromTrash('${f.restoreUrl}')" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700 font-bold text-xs uppercase tracking-wide hover:bg-emerald-600 hover:text-white transition"><i data-lucide="rotate-ccw" class="w-4 h-4"></i>Khôi phục</button></td></tr>`;
            });
            if (!trash.length) html = `<div class="text-center py-20 text-gray-400 font-bold">Thùng rác đang trống</div>`;
            document.getElementById('mainContent').innerHTML = html + `</tbody></table></div>`;
            renderIcons();
        }

        function setActiveTab(tab) {
            document.querySelectorAll('.nav-btn').forEach((b) => b.classList.remove('active', 'text-blue-600', 'bg-white/60'));
            document.getElementById(`btn-${tab}`).classList.add('active');
        }
        function showTab(tab) {
            setActiveTab(tab);
            if (tab === 'projects') renderProjects();
            else if (tab === 'trash') renderTrash();
            else renderRecent();
        }
        async function loadData(forceTab) {
            const activeTab = forceTab || document.querySelector('.nav-btn.active')?.id?.replace('btn-', '') || 'recent';
            await Promise.all([loadReleases(), loadTrash()]);
            showTab(activeTab);
        }
        async function moveToTrash(deleteUrl, label) {
            if (!getToken()) { showToast('Cần có token Bearer để xóa', 'error'); return; }
            if (!confirm('Đưa bản phát hành ' + label + ' vào thùng rác?')) return;
            const response = await fetch(deleteUrl, { method: 'DELETE', headers: { Accept: 'application/json', Authorization: 'Bearer ' + getToken() } });
            const payload = await parseResponse(response);
            if (!response.ok) { showToast((payload && payload.message) || 'Xóa thất bại', 'error'); return; }
            await loadData('trash');
            showToast((payload && payload.message) || 'Đã chuyển vào thùng rác');
        }
        async function restoreFromTrash(restoreUrl) {
            if (!getToken()) { showToast('Cần có token Bearer để khôi phục', 'error'); return; }
            const response = await fetch(restoreUrl, { method: 'POST', headers: { Accept: 'application/json', Authorization: 'Bearer ' + getToken() } });
            const payload = await parseResponse(response);
            if (!response.ok) { showToast((payload && payload.message) || 'Khôi phục thất bại', 'error'); return; }
            await loadData('trash');
            showToast((payload && payload.message) || 'Đã khôi phục');
        }
        function copyLink(link) { navigator.clipboard.writeText(link); showToast('Đã sao chép liên kết'); }

        const uploadEnabled = true;
        async function handleUpload(event) {
            event.preventDefault();
            if (!uploadEnabled) { showToast('Chưa cho phép upload', 'error'); return; }
            getEl('uploadBtn').disabled = true;
            hideOutput(getEl('uploadOutput'));
            const formData = new FormData();
            formData.append('app_slug', getEl('appSlug').value.trim());
            formData.append('channel', getEl('channel').value.trim() || 'app');
            formData.append('version', getEl('version').value.trim());
            formData.append('notes', getEl('notes').value);
            formData.append('mandatory', getEl('mandatory').value);
            formData.append('file', getEl('file').files[0]);
            try {
                const response = await fetch(publishUrl, { method: 'POST', headers: { Accept: 'application/json', Authorization: 'Bearer ' + getToken() }, body: formData });
                const payload = await parseResponse(response);
                showOutput(getEl('uploadOutput'), payload);
                if (!response.ok) { showToast('Upload thất bại. HTTP ' + response.status, 'error'); return; }
                const token = getToken();
                getEl('uploadForm').reset();
                getEl('token').value = token;
                getEl('appSlug').value = payload?.release?.app_slug || '';
                getEl('channel').value = payload?.release?.channel || 'app';
                getEl('version').value = payload?.release?.version || '';
                getEl('notes').value = payload?.release?.notes || '';
                getEl('mandatory').value = payload?.release?.mandatory ? '1' : '0';
                persistState();
                await loadData('recent');
                toggleModal(false);
                showToast(payload.message || 'Upload thành công');
            } catch (error) {
                showOutput(getEl('uploadOutput'), String(error));
                showToast('Không gửi được yêu cầu upload', 'error');
            } finally {
                getEl('uploadBtn').disabled = false;
            }
        }

        document.getElementById('uploadForm').addEventListener('submit', handleUpload);
        document.getElementById('uploadForm').addEventListener('input', persistState);
        const refreshBtn = document.getElementById('refreshBtn');
        if (refreshBtn) refreshBtn.addEventListener('click', () => loadData().catch(() => showToast('Làm mới thất bại', 'error')));
        hydrateState();
        loadData('recent').catch(() => showToast('Không tải được dữ liệu', 'error'));
        renderIcons();
    </script>
</body>
</html>
