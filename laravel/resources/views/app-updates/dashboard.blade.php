<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional File Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">
    <div id="app" class="flex h-screen overflow-hidden" data-list-url="{{ $listUrl }}" data-publish-url="{{ $publishUrl }}">
        <aside class="w-72 bg-white border-r border-gray-200 flex-shrink-0 hidden xl:flex flex-col">
            <div class="p-6">
                <div class="flex items-center gap-3 text-blue-600">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-600 text-white">
                        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gray-400">Server</p>
                        <span class="font-bold text-xl">UpdateManager</span>
                    </div>
                </div>
            </div>

            <div class="px-4 space-y-3">
                <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-500">Domain</p>
                    <p class="mt-2 font-semibold text-gray-900">update.0977769666.click</p>
                    <p class="mt-1 text-sm text-gray-500">Trang goc quan ly release.</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-400">Token</p>
                    <p class="mt-2 text-sm text-gray-600">Khong co token thi khong upload, refresh, xoa hay download JSON duoc.</p>
                </div>
            </div>

            <div class="mt-auto p-4 border-t border-gray-100">
                <div class="bg-blue-600 text-white p-4 rounded-xl shadow-lg shadow-blue-200">
                    <p class="text-xs opacity-80 uppercase font-bold">Release stats</p>
                    <p class="text-sm font-semibold mt-1"><span id="releaseCountAside">0</span> channels / <span id="appCountAside">0</span> apps</p>
                    <div class="w-full bg-blue-400 h-1.5 rounded-full mt-2">
                        <div class="bg-white h-1.5 rounded-full" id="statsBar" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </aside>

        <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <header class="h-20 bg-white border-b border-gray-200 flex items-center justify-between px-8">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">Release dashboard</p>
                    <h1 class="text-2xl font-bold text-gray-900">Professional File Manager</h1>
                </div>
                <div class="flex items-center gap-4">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 text-sm text-gray-600">
                        <span id="releaseCount">0</span> files / <span id="appCount">0</span> apps
                    </div>
                    <button id="refreshBtn" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i> Refresh
                    </button>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-8">
                <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gray-400">Upload file</p>
                                <h2 class="mt-2 text-2xl font-bold text-gray-900">Publish update</h2>
                            </div>
                            <div class="p-3 rounded-xl bg-blue-50 text-blue-600">
                                <i data-lucide="upload-cloud" class="w-5 h-5"></i>
                            </div>
                        </div>

                        <form id="uploadForm" class="mt-5 space-y-4">
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Bearer Token</label>
                                <input id="token" type="password" placeholder="49|..." required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">App Slug</label>
                                    <input id="appSlug" type="text" value="tiktok-bot" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Channel</label>
                                    <input id="channel" type="text" value="app" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Version</label>
                                    <input id="version" type="text" value="1.0.0" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Mandatory</label>
                                    <input id="mandatory" type="text" value="1" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Notes</label>
                                <textarea id="notes" class="min-h-[120px] w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500">Release uploaded from dashboard.</textarea>
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">EXE File</label>
                                <input id="file" type="file" accept=".exe" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:font-semibold file:text-white">
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <button id="uploadBtn" type="submit" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg text-sm font-medium transition shadow-sm">
                                    <i data-lucide="upload" class="w-4 h-4"></i> Upload File
                                </button>
                                <button type="button" data-channel="app" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700">Use app</button>
                                <button type="button" data-channel="bot-server" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700">Use bot-server</button>
                            </div>
                        </form>

                        <div id="uploadStatus" class="hidden mt-4 rounded-xl border px-4 py-3 text-sm font-medium"></div>
                        <pre id="uploadOutput" class="hidden mt-4 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100"></pre>
                    </section>

                    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gray-400">Server files</p>
                                <h2 class="mt-2 text-2xl font-bold text-gray-900">Quan ly release</h2>
                                <p class="mt-1 text-sm text-gray-500">Delete se dua file vao trash. Sau 30 ngay he thong moi xoa vinh vien tren server.</p>
                            </div>
                            <div id="listStatus" class="hidden rounded-xl border px-4 py-3 text-sm font-medium"></div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-400 text-xs uppercase font-semibold">
                                        <th class="px-6 py-4">Ten File</th>
                                        <th class="px-6 py-4">Version</th>
                                        <th class="px-6 py-4">Kich thuoc</th>
                                        <th class="px-6 py-4">Ngay cap nhat</th>
                                        <th class="px-6 py-4">Trang thai</th>
                                        <th class="px-6 py-4 text-right">Hanh dong</th>
                                    </tr>
                                </thead>
                                <tbody id="releaseList" class="divide-y divide-gray-100"></tbody>
                            </table>
                        </div>

                        <div id="emptyState" class="hidden px-6 py-10 text-center text-sm text-gray-500">
                            Chua co release nao tren server.
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <script>
        const root = document.getElementById('app');
        const listUrl = root.dataset.listUrl;
        const publishUrl = root.dataset.publishUrl;
        const stateKey = 'update-dashboard-state';
        const $ = (id) => document.getElementById(id);

        function setStatus(el, type, message) {
            el.className = 'mt-4 block rounded-xl border px-4 py-3 text-sm font-medium';
            if (type === 'ok') el.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
            else if (type === 'error') el.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-700');
            else el.classList.add('border-gray-200', 'bg-gray-50', 'text-gray-600');
            el.textContent = message;
        }
        function clearStatus(el) { el.className = 'hidden rounded-xl border px-4 py-3 text-sm font-medium'; el.textContent = ''; }
        function showOutput(el, payload) { el.classList.remove('hidden'); el.textContent = typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2); }
        function hideOutput(el) { el.classList.add('hidden'); el.textContent = ''; }
        function getToken() { return $('token').value.trim(); }
        function persistState() {
            localStorage.setItem(stateKey, JSON.stringify({
                token: $('token').value, appSlug: $('appSlug').value, channel: $('channel').value,
                version: $('version').value, mandatory: $('mandatory').value, notes: $('notes').value,
            }));
        }
        function hydrateState() {
            try {
                const state = JSON.parse(localStorage.getItem(stateKey) || '{}');
                Object.entries(state).forEach(([key, value]) => { if ($(key) && value !== undefined) $(key).value = value; });
            } catch {}
        }
        async function parseResponse(response) {
            const raw = await response.text();
            try { return JSON.parse(raw); } catch { return raw; }
        }
        function renderCounts(releases) {
            const count = String(releases.length);
            const apps = String(new Set(releases.map((item) => item.app_slug)).size);
            $('releaseCount').textContent = count; $('releaseCountAside').textContent = count;
            $('appCount').textContent = apps; $('appCountAside').textContent = apps;
            $('statsBar').style.width = Math.min(releases.length * 12, 100) + '%';
        }
        async function loadReleases() {
            if (!getToken()) { setStatus($('listStatus'), 'error', 'Nhap Bearer Token de tai danh sach release.'); return; }
            setStatus($('listStatus'), 'muted', 'Dang tai danh sach release...');
            const response = await fetch(listUrl, { headers: { Accept: 'application/json', Authorization: 'Bearer ' + getToken() } });
            const payload = await parseResponse(response);
            if (!response.ok) { setStatus($('listStatus'), 'error', 'Khong tai duoc release. HTTP ' + response.status + '.'); showOutput($('uploadOutput'), payload); return; }
            renderReleases(payload.releases || []);
            setStatus($('listStatus'), 'ok', 'Da tai xong danh sach release.');
        }
        function renderReleases(releases) {
            const tbody = $('releaseList');
            tbody.innerHTML = '';
            renderCounts(releases);
            $('emptyState').classList.toggle('hidden', releases.length > 0);

            releases.forEach((release) => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-blue-50/30 transition group';
                tr.innerHTML = `
                    <td class="px-6 py-4">
                        <div class="flex items-start gap-3">
                            <div class="p-2 bg-blue-100 text-blue-600 rounded-lg"><i data-lucide="${release.channel === 'bot-server' ? 'bot' : 'file-code-2'}" class="w-5 h-5"></i></div>
                            <div>
                                <p class="font-medium text-gray-900">${release.filename}</p>
                                <p class="text-xs text-gray-400">${release.app_slug} / ${release.channel}</p>
                                <p class="mt-1 text-xs text-gray-400">${release.notes || 'Khong co release note.'}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">v${release.version}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">${(release.size / 1048576).toFixed(2)} MB</td>
                    <td class="px-6 py-4 text-sm text-gray-500">${release.published_at || '-'}</td>
                    <td class="px-6 py-4"><span class="px-2 py-1 ${release.mandatory ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'} text-[10px] font-bold rounded-full uppercase">${release.mandatory ? 'Mandatory' : 'Optional'}</span></td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition">
                            <a title="Download" href="${release.download_url}" class="p-1.5 text-gray-500 hover:text-blue-600"><i data-lucide="download" class="w-4 h-4"></i></a>
                            <a title="JSON" href="${release.latest_url}" class="p-1.5 text-gray-500 hover:text-slate-700"><i data-lucide="file-json" class="w-4 h-4"></i></a>
                            <button title="Delete" class="p-1.5 text-gray-500 hover:text-red-600" type="button" data-delete-url="${release.delete_url}" data-label="${release.app_slug}/${release.channel}"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </div>
                    </td>`;
                tbody.appendChild(tr);
            });

            lucide.createIcons();
            tbody.querySelectorAll('[data-delete-url]').forEach((button) => {
                button.addEventListener('click', async () => {
                    if (!getToken()) { setStatus($('listStatus'), 'error', 'Khong the xoa khi chua co token.'); return; }
                    if (!confirm('Dua release ' + button.dataset.label + ' vao trash?')) return;
                    button.disabled = true;
                    try {
                        const response = await fetch(button.dataset.deleteUrl, { method: 'DELETE', headers: { Accept: 'application/json', Authorization: 'Bearer ' + getToken() } });
                        const payload = await parseResponse(response);
                        if (!response.ok) { setStatus($('listStatus'), 'error', 'Xoa that bai. HTTP ' + response.status + '.'); showOutput($('uploadOutput'), payload); return; }
                        setStatus($('listStatus'), 'ok', payload.message || 'Da dua release vao trash.');
                        await loadReleases();
                    } catch (error) {
                        setStatus($('listStatus'), 'error', 'Khong gui duoc request xoa.');
                        showOutput($('uploadOutput'), String(error));
                    } finally { button.disabled = false; }
                });
            });
        }

        $('uploadForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!getToken()) { setStatus($('uploadStatus'), 'error', 'Chi upload duoc khi co token.'); return; }
            if (!$('file').files[0]) { setStatus($('uploadStatus'), 'error', 'Chua chon file .exe.'); return; }

            $('uploadBtn').disabled = true; clearStatus($('uploadStatus')); hideOutput($('uploadOutput'));
            const formData = new FormData();
            formData.append('app_slug', $('appSlug').value.trim());
            formData.append('channel', $('channel').value.trim() || 'app');
            formData.append('version', $('version').value.trim());
            formData.append('notes', $('notes').value);
            formData.append('mandatory', $('mandatory').value);
            formData.append('file', $('file').files[0]);

            try {
                const response = await fetch(publishUrl, { method: 'POST', headers: { Accept: 'application/json', Authorization: 'Bearer ' + getToken() }, body: formData });
                const payload = await parseResponse(response);
                showOutput($('uploadOutput'), payload);
                if (!response.ok) { setStatus($('uploadStatus'), 'error', 'Upload that bai. HTTP ' + response.status + '.'); return; }
                setStatus($('uploadStatus'), 'ok', payload.message || 'Upload thanh cong.');
                const token = getToken();
                $('uploadForm').reset();
                $('token').value = token;
                $('appSlug').value = payload?.release?.app_slug || '';
                $('channel').value = payload?.release?.channel || 'app';
                $('version').value = payload?.release?.version || '';
                $('notes').value = payload?.release?.notes || '';
                $('mandatory').value = payload?.release?.mandatory ? '1' : '0';
                persistState();
                await loadReleases();
            } catch (error) {
                setStatus($('uploadStatus'), 'error', 'Khong gui duoc request upload.');
                showOutput($('uploadOutput'), String(error));
            } finally { $('uploadBtn').disabled = false; }
        });

        document.querySelectorAll('[data-channel]').forEach((button) => button.addEventListener('click', () => { $('channel').value = button.dataset.channel; persistState(); }));
        $('refreshBtn').addEventListener('click', () => loadReleases().catch(() => setStatus($('listStatus'), 'error', 'Refresh that bai.')));
        $('uploadForm').addEventListener('input', persistState);
        hydrateState();
        if (getToken()) loadReleases().catch(() => setStatus($('listStatus'), 'error', 'Khong tai duoc release luc khoi dong.'));
        lucide.createIcons();
    </script>
</body>
</html>
