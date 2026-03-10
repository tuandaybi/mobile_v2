<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevCenter - Full Release Edition</title>
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
    <div id="app" class="max-w-7xl mx-auto h-full flex apple-glass rounded-[40px] shadow-2xl overflow-hidden relative border border-white/40" data-list-url="{{ $listUrl }}" data-publish-url="{{ $publishUrl }}">
        <aside class="w-20 md:w-64 border-r border-white/20 flex flex-col p-6 space-y-4">
            <div class="flex items-center gap-3 px-2 mb-8 text-blue-600">
                <div class="w-10 h-10 bg-blue-600 rounded-2xl flex items-center justify-center text-white shadow-lg"><i data-lucide="layers"></i></div>
                <span class="hidden md:block font-bold text-xl tracking-tighter text-gray-900 uppercase">DevCenter</span>
            </div>
            <nav class="space-y-2 flex-1">
                <button id="btn-recent" onclick="showTab('recent')" class="nav-btn w-full flex items-center gap-3 p-3 text-gray-500 hover:bg-white/30 rounded-2xl font-bold transition">
                    <i data-lucide="clock"></i> <span class="hidden md:block text-sm">Gan day</span>
                </button>
                <button id="btn-projects" onclick="showTab('projects')" class="nav-btn w-full flex items-center gap-3 p-3 text-gray-500 hover:bg-white/30 rounded-2xl font-bold transition">
                    <i data-lucide="folder"></i> <span class="hidden md:block text-sm">Du an</span>
                </button>
            </nav>
            <nav class="pt-4 border-t border-white/20">
                <button id="btn-trash" onclick="showTab('trash')" class="nav-btn w-full flex items-center gap-3 p-3 text-rose-500 hover:bg-rose-50 rounded-2xl font-bold transition">
                    <i data-lucide="trash-2"></i> <span class="hidden md:block text-sm">Da xoa</span>
                </button>
            </nav>
        </aside>

        <main class="flex-1 flex flex-col overflow-hidden">
            <header class="h-24 flex items-center justify-between px-10">
                <div id="headerTitle"></div>
                <div class="flex items-center gap-3">
                    <div class="hidden md:flex items-center gap-2 rounded-full bg-white/40 px-4 py-2 text-xs font-bold uppercase tracking-[0.2em] text-slate-500">
                        <span id="releaseCount">0</span>
                        <span>release</span>
                    </div>
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
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Upload</p>
                    <h3 class="mt-2 text-2xl font-bold text-slate-900">Publish release</h3>
                </div>
                <div class="rounded-2xl bg-blue-50 p-3 text-blue-600 shadow-inner">
                    <i data-lucide="upload-cloud" class="w-6 h-6"></i>
                </div>
            </div>

            <form id="uploadForm" class="space-y-5">
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Bearer Token</label>
                    <input id="token" type="password" placeholder="49|..." required class="w-full rounded-2xl border border-white/60 bg-white/40 px-4 py-3 outline-none focus:border-blue-400 transition shadow-sm">
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">App Slug</label>
                        <input id="appSlug" type="text" value="tiktok-bot" required class="w-full rounded-2xl border border-white/60 bg-white/40 px-4 py-3 outline-none focus:border-blue-400 transition font-bold">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Channel</label>
                        <input id="channel" type="text" value="app" required class="w-full rounded-2xl border border-white/60 bg-white/40 px-4 py-3 outline-none focus:border-blue-400 transition">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Version</label>
                        <input id="version" type="text" value="1.0.0" required class="w-full rounded-2xl border border-white/60 bg-white/40 px-4 py-3 outline-none focus:border-blue-400 transition font-bold text-blue-600">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Mandatory</label>
                        <input id="mandatory" type="text" value="1" placeholder="1 or 0" class="w-full rounded-2xl border border-white/60 bg-white/40 px-4 py-3 outline-none focus:border-blue-400 transition">
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Notes</label>
                    <textarea id="notes" class="min-h-[100px] w-full rounded-2xl border border-white/60 bg-white/40 px-4 py-3 outline-none focus:border-blue-400 transition">Release uploaded from update dashboard.</textarea>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">EXE File</label>
                    <input id="file" type="file" accept=".exe" required class="w-full rounded-2xl border border-white/60 bg-white/40 px-4 py-3 file:mr-4 file:rounded-xl file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:font-semibold file:text-white cursor-pointer">
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <button id="uploadBtn" type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-6 py-4 text-sm font-bold text-white shadow-lg hover:bg-black active:scale-95 transition-all uppercase tracking-wide">
                        <i data-lucide="upload" class="w-4 h-4"></i>
                        Upload release
                    </button>
                    <button type="button" onclick="document.getElementById('channel').value='app'" class="rounded-2xl border border-white/60 bg-white/30 px-5 py-4 text-sm font-semibold text-slate-700 hover:bg-white/60 transition">Use app</button>
                    <button type="button" onclick="document.getElementById('channel').value='bot-server'" class="rounded-2xl border border-white/60 bg-white/30 px-5 py-4 text-sm font-semibold text-slate-700 hover:bg-white/60 transition">Use bot-server</button>
                </div>
            </form>
            <pre id="uploadOutput" class="hidden mt-6 overflow-auto rounded-2xl bg-slate-950 p-4 text-xs text-slate-100"></pre>
            <button onclick="toggleModal(false)" class="absolute top-8 right-8 text-slate-400 hover:text-slate-900 transition"><i data-lucide="x"></i></button>
        </div>
    </div>

    <div id="toast" class="fixed bottom-10 left-1/2 -translate-x-1/2 apple-glass px-6 py-3 rounded-full shadow-2xl border border-white/50 z-[200] flex items-center gap-2">
        <div id="toastIcon" class="w-6 h-6 bg-emerald-500 text-white rounded-full flex items-center justify-center"><i data-lucide="check" class="w-4 h-4"></i></div>
        <span id="toastMsg" class="font-bold text-gray-800 text-sm">Done</span>
    </div>

    <script>
        const root = document.getElementById('app');
        const listUrl = root.dataset.listUrl;
        const publishUrl = root.dataset.publishUrl;
        const stateKey = 'update-dashboard-state';
        let releases = [];
        let trash = [];

        function getEl(id) { return document.getElementById(id); }
        function getToken() { return getEl('token').value.trim(); }
        function toggleModal(show) { document.getElementById('modalOverlay').classList.toggle('active', show); }
        function showToast(msg, type = 'success') {
            document.getElementById('toastMsg').innerText = msg;
            const iconBox = document.getElementById('toastIcon');
            iconBox.className = 'w-6 h-6 text-white rounded-full flex items-center justify-center ' + (type === 'success' ? 'bg-emerald-500' : 'bg-rose-500');
            iconBox.innerHTML = `<i data-lucide="${type === 'success' ? 'check' : 'x'}" class="w-4 h-4"></i>`;
            lucide.createIcons();
            const toast = document.getElementById('toast');
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2200);
        }
        function persistState() {
            localStorage.setItem(stateKey, JSON.stringify({
                token: getEl('token').value,
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
                Object.entries(state).forEach(([key, value]) => {
                    const el = getEl(key);
                    if (el && value !== undefined) el.value = value;
                });
            } catch {}
        }
        async function parseResponse(response) {
            const raw = await response.text();
            try { return JSON.parse(raw); } catch { return raw; }
        }
        function updateCounts() {
            const count = releases.length;
            const appCount = new Set(releases.map((item) => item.app_slug)).size;
            getEl('releaseCount').innerText = count;
            getEl('releaseCountAside').innerText = count;
            getEl('appCountAside').innerText = appCount;
        }
        async function loadReleases() {
            if (!getToken()) {
                releases = [];
                trash = [];
                renderCurrentTab();
                showToast('Nhap Bearer Token de tai du lieu', 'error');
                return;
            }
            const response = await fetch(listUrl, { headers: { Accept: 'application/json', Authorization: 'Bearer ' + getToken() } });
            const payload = await parseResponse(response);
            if (!response.ok) {
                showToast('Khong tai duoc du lieu. HTTP ' + response.status, 'error');
                return;
            }
            releases = payload.releases || [];
            updateCounts();
            renderCurrentTab();
        }
        function statusBadge(release) {
            return release.mandatory
                ? `<span class="px-2 py-1 bg-rose-100 text-rose-700 text-[10px] font-bold rounded-full uppercase">Mandatory</span>`
                : `<span class="px-2 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-full uppercase">Optional</span>`;
        }
        function fileIcon(channel) {
            return channel === 'bot-server' ? 'bot' : 'file-code';
        }
        function renderRecent() {
            document.getElementById('headerTitle').innerHTML = `<h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Gan day</h2>`;
            let html = `<div class="apple-glass rounded-[35px] overflow-hidden shadow-xl border border-white/40 fade-in"><table class="w-full text-left"><thead class="bg-white/30 border-b border-white/20 text-[10px] uppercase tracking-widest text-gray-400 font-bold"><tr><th class="px-8 py-5">File</th><th class="px-8 py-5">Ngay tai</th><th class="px-8 py-5 text-center">Kich thuoc</th><th class="px-8 py-5 text-center">Trang thai</th><th class="px-8 py-5 text-right">Action</th></tr></thead><tbody class="divide-y divide-white/20">`;
            releases.forEach((f) => {
                html += `<tr class="hover:bg-white/40 group transition"><td class="px-8 py-5 flex items-center gap-4"><div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-sm text-blue-500"><i data-lucide="${fileIcon(f.channel)}"></i></div><div><p class="font-bold text-gray-900">${f.filename}</p><span class="text-[10px] bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full font-bold uppercase">${f.app_slug} / ${f.channel}</span><p class="text-[10px] text-gray-400 mt-1">v${f.version}</p></div></td><td class="px-8 py-5 text-sm text-gray-500 font-medium">${f.published_at || '-'}</td><td class="px-8 py-5 text-center text-emerald-600 font-bold text-sm">${(f.size / 1048576).toFixed(2)} MB</td><td class="px-8 py-5 text-center">${statusBadge(f)}</td><td class="px-8 py-5 text-right"><div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition"><button onclick="copyLink('${f.download_url}')" class="w-9 h-9 rounded-full bg-white text-gray-400 hover:text-gray-900 flex items-center justify-center shadow-sm border border-gray-100"><i data-lucide="copy" class="w-4 h-4"></i></button><a href="${f.download_url}" class="w-9 h-9 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition"><i data-lucide="download" class="w-4 h-4"></i></a><button onclick="moveToTrash('${f.delete_url}', '${f.app_slug}/${f.channel}')" class="w-9 h-9 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-500 hover:text-white transition shadow-sm"><i data-lucide="trash-2" class="w-4 h-4"></i></button></div></td></tr>`;
            });
            if (!releases.length) html += `<tr><td colspan="5" class="px-8 py-20 text-center text-gray-400 font-bold">Chua co release nao</td></tr>`;
            document.getElementById('mainContent').innerHTML = html + `</tbody></table></div>`;
            lucide.createIcons();
        }
        function renderProjects() {
            document.getElementById('headerTitle').innerHTML = `<h2 class="text-3xl font-extrabold text-gray-900 tracking-tight uppercase">Du an cua toi</h2>`;
            const grouped = releases.reduce((acc, item) => {
                acc[item.app_slug] = acc[item.app_slug] || [];
                acc[item.app_slug].push(item);
                return acc;
            }, {});
            let html = `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 fade-in">`;
            Object.keys(grouped).forEach((slug) => {
                html += `<div onclick="openFolder('${slug}')" class="p-8 apple-glass rounded-[35px] cursor-pointer hover:bg-white/70 transition-all hover:-translate-y-2 group border border-white/50"><div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-6"><i data-lucide="folder"></i></div><h3 class="text-xl font-bold uppercase tracking-tight">${slug}</h3><p class="text-gray-400 text-sm font-medium mt-1">${grouped[slug].length} phien ban</p></div>`;
            });
            if (!Object.keys(grouped).length) html = `<div class="text-center py-20 text-gray-400 font-bold w-full">Chua co du an nao</div>`;
            document.getElementById('mainContent').innerHTML = html + `</div>`;
            lucide.createIcons();
        }
        function openFolder(slug) {
            document.getElementById('headerTitle').innerHTML = `<p class="text-[10px] font-bold text-blue-600 uppercase tracking-widest mb-1">Du an</p><h2 class="text-3xl font-extrabold text-gray-900 uppercase tracking-tight">${slug}</h2>`;
            const items = releases.filter((item) => item.app_slug === slug);
            let html = `<button onclick="renderProjects()" class="flex items-center gap-2 text-blue-600 font-bold mb-6 hover:underline transition"><i data-lucide="chevron-left" class="w-5 h-5"></i> Quay lai</button><div class="apple-glass rounded-[35px] overflow-hidden shadow-xl border border-white/40 fade-in"><table class="w-full text-left"><thead class="bg-white/30 border-b border-white/20 text-[10px] uppercase tracking-widest text-gray-400 font-bold"><tr><th class="px-8 py-5">Ten File</th><th class="px-8 py-5">Ngay tai</th><th class="px-8 py-5 text-center">JSON</th><th class="px-8 py-5 text-right">Action</th></tr></thead><tbody class="divide-y divide-white/20">`;
            items.forEach((f) => {
                html += `<tr class="hover:bg-white/40 transition group"><td class="px-8 py-5 flex items-center gap-4"><div class="w-10 h-10 bg-white shadow-sm rounded-xl flex items-center justify-center text-blue-500"><i data-lucide="${fileIcon(f.channel)}"></i></div><div><p class="font-bold text-gray-900">${f.filename}</p><p class="text-[10px] text-blue-600 font-bold uppercase">${f.channel} / version ${f.version}</p></div></td><td class="px-8 py-5 text-sm text-gray-500 font-semibold">${f.published_at || '-'}</td><td class="px-8 py-5 text-center"><a href="${f.latest_url}" class="text-blue-600 font-bold text-sm hover:underline">Open</a></td><td class="px-8 py-5 text-right"><div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition"><button onclick="copyLink('${f.download_url}')" class="w-9 h-9 rounded-full bg-white text-gray-400 hover:text-gray-900 flex items-center justify-center shadow-sm border border-gray-100"><i data-lucide="copy" class="w-4 h-4"></i></button><a href="${f.download_url}" class="w-9 h-9 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition shadow-sm"><i data-lucide="download" class="w-4 h-4"></i></a><button onclick="moveToTrash('${f.delete_url}', '${f.app_slug}/${f.channel}')" class="w-9 h-9 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-500 hover:text-white transition shadow-sm"><i data-lucide="trash-2" class="w-4 h-4"></i></button></div></td></tr>`;
            });
            if (!items.length) html += `<tr><td colspan="4" class="px-8 py-20 text-center text-gray-400 font-bold">Khong co file nao</td></tr>`;
            document.getElementById('mainContent').innerHTML = html + `</tbody></table></div>`;
            lucide.createIcons();
        }
        function renderTrash() {
            document.getElementById('headerTitle').innerHTML = `<h2 class="text-3xl font-extrabold text-gray-900 tracking-tight text-rose-600 uppercase">Thung rac</h2>`;
            let html = `<div class="apple-glass rounded-[35px] overflow-hidden shadow-xl border border-rose-100 fade-in"><table class="w-full text-left"><thead class="bg-rose-50/50 border-b border-rose-100 text-[10px] uppercase tracking-widest text-rose-400 font-bold"><tr><th class="px-8 py-5">Ten File</th><th class="px-8 py-5 text-center">Ngay xoa</th><th class="px-8 py-5 text-center">Purge</th></tr></thead><tbody class="divide-y divide-rose-50">`;
            trash.forEach((f) => {
                html += `<tr class="hover:bg-rose-50/20 group transition"><td class="px-8 py-5 flex items-center gap-4"><div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-sm text-gray-400"><i data-lucide="file-x"></i></div><div><p class="font-bold text-gray-500 line-through">${f.label}</p><p class="text-[10px] text-gray-400 uppercase">Cho xoa vinh vien sau 30 ngay</p></div></td><td class="px-8 py-5 text-center text-rose-400 font-bold text-sm uppercase">${f.deletedAt}</td><td class="px-8 py-5 text-center text-gray-400 font-bold text-sm uppercase">${f.purgeAfter}</td></tr>`;
            });
            if (!trash.length) html = `<div class="text-center py-20 text-gray-400 font-bold">Thung rac dang trong</div>`;
            document.getElementById('mainContent').innerHTML = html + `</tbody></table></div>`;
            lucide.createIcons();
        }
        function setActiveTab(tab) {
            document.querySelectorAll('.nav-btn').forEach((b) => b.classList.remove('active', 'text-blue-600', 'bg-white/60'));
            document.getElementById(`btn-${tab}`).classList.add('active');
        }
        function renderCurrentTab() {
            const active = document.querySelector('.nav-btn.active')?.id?.replace('btn-', '') || 'recent';
            showTab(active);
        }
        function showTab(tab) {
            setActiveTab(tab);
            if (tab === 'projects') renderProjects();
            else if (tab === 'trash') renderTrash();
            else renderRecent();
        }
        async function moveToTrash(deleteUrl, label) {
            if (!getToken()) { showToast('Khong the xoa khi chua co token', 'error'); return; }
            if (!confirm('Dua release ' + label + ' vao thung rac?')) return;
            const response = await fetch(deleteUrl, { method: 'DELETE', headers: { Accept: 'application/json', Authorization: 'Bearer ' + getToken() } });
            const payload = await parseResponse(response);
            if (!response.ok) { showToast('Xoa that bai', 'error'); return; }
            trash.unshift({
                label,
                deletedAt: new Date().toLocaleDateString('vi-VN'),
                purgeAfter: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toLocaleDateString('vi-VN'),
            });
            await loadReleases();
            showToast(payload.message || 'Da chuyen vao thung rac');
        }
        function copyLink(link) {
            navigator.clipboard.writeText(link);
            showToast('Da sao chep link!');
        }
        async function handleUpload(event) {
            event.preventDefault();
            if (!getToken()) { showToast('Chi upload duoc khi co token', 'error'); return; }
            if (!getEl('file').files[0]) { showToast('Chua chon file .exe', 'error'); return; }
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
                if (!response.ok) { showToast('Upload that bai. HTTP ' + response.status, 'error'); return; }
                const token = getToken();
                getEl('uploadForm').reset();
                getEl('token').value = token;
                getEl('appSlug').value = payload?.release?.app_slug || '';
                getEl('channel').value = payload?.release?.channel || 'app';
                getEl('version').value = payload?.release?.version || '';
                getEl('notes').value = payload?.release?.notes || '';
                getEl('mandatory').value = payload?.release?.mandatory ? '1' : '0';
                persistState();
                await loadReleases();
                toggleModal(false);
                showToast(payload.message || 'Tai len thanh cong!');
            } catch (error) {
                showOutput(getEl('uploadOutput'), String(error));
                showToast('Khong gui duoc request upload', 'error');
            } finally {
                getEl('uploadBtn').disabled = false;
            }
        }
        document.getElementById('uploadForm').addEventListener('submit', handleUpload);
        document.getElementById('uploadForm').addEventListener('input', persistState);
        document.getElementById('refreshBtn').addEventListener('click', () => loadReleases().catch(() => showToast('Refresh that bai', 'error')));
        hydrateState();
        loadReleases().finally(() => showTab('recent'));
        lucide.createIcons();
    </script>
</body>
</html>
