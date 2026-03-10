<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800">
    <div id="app" class="min-h-screen" data-login-url="{{ $loginUrl }}" data-list-url="{{ $listUrl }}" data-publish-url="{{ $publishUrl }}">
        <div class="flex min-h-screen">
            <aside class="hidden xl:flex w-72 flex-col border-r border-slate-200 bg-white">
                <div class="p-6 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-600 text-white">
                            <i data-lucide="rocket" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Laravel</p>
                            <h1 class="text-xl font-bold text-slate-900">Update Manager</h1>
                        </div>
                    </div>
                </div>
                <div class="space-y-4 p-4">
                    <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-500">Current user</p>
                        <p id="sessionUserAside" class="mt-2 font-semibold text-slate-900">Chua dang nhap</p>
                        <p id="sessionEmailAside" class="mt-1 text-sm text-slate-500">Login de lay Sanctum token.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p id="releaseCountAside" class="text-3xl font-bold text-slate-900">0</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">Release</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p id="appCountAside" class="text-3xl font-bold text-slate-900">0</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">Apps</p>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="flex-1 min-w-0">
                <header class="border-b border-slate-200 bg-white">
                    <div class="flex items-center justify-between gap-4 px-6 py-5 lg:px-8">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-600">update.0977769666.click</p>
                            <h2 class="mt-2 text-3xl font-bold text-slate-900">Professional Release Center</h2>
                            <p class="mt-2 text-sm text-slate-500">Dang nhap bang API Laravel, upload release va quan ly file tren server trong cung mot trang.</p>
                        </div>
                        <button id="refreshBtn" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">
                            <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                            Refresh
                        </button>
                    </div>
                </header>

                <div class="space-y-6 px-6 py-6 lg:px-8">
                    <section class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Login</p>
                            <h3 class="mt-2 text-2xl font-bold text-slate-900">Dang nhap de lay Bearer Token</h3>
                            <form id="loginForm" class="mt-5 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Email</label>
                                    <input id="email" type="email" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-blue-400">
                                </div>
                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Password</label>
                                    <input id="password" type="password" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-blue-400">
                                </div>
                                <div class="md:col-span-2 flex flex-wrap items-center gap-3">
                                    <button id="loginBtn" type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white">
                                        <i data-lucide="log-in" class="h-4 w-4"></i>
                                        Login and get token
                                    </button>
                                    <div id="loginStatus" class="hidden rounded-2xl border px-4 py-3 text-sm font-medium"></div>
                                </div>
                            </form>
                            <pre id="loginOutput" class="mt-4 hidden overflow-auto rounded-2xl bg-slate-950 p-4 text-xs text-slate-100"></pre>
                        </div>

                        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Session</p>
                            <div class="mt-4 space-y-4">
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <p id="sessionUser" class="font-semibold text-slate-900">Chua dang nhap</p>
                                    <p id="sessionEmail" class="mt-1 text-sm text-slate-500">Token se tu dong nap vao form upload.</p>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <p id="releaseCount" class="text-3xl font-bold text-slate-900">0</p>
                                        <p class="mt-1 text-sm text-slate-500">Release channels</p>
                                    </div>
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <p id="appCount" class="text-3xl font-bold text-slate-900">0</p>
                                        <p class="mt-1 text-sm text-slate-500">Apps</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Upload</p>
                                    <h3 class="mt-2 text-2xl font-bold text-slate-900">Publish release</h3>
                                </div>
                                <div class="rounded-2xl bg-blue-50 p-3 text-blue-600">
                                    <i data-lucide="upload-cloud" class="h-5 w-5"></i>
                                </div>
                            </div>

                            <form id="uploadForm" class="mt-5 space-y-4">
                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Bearer Token</label>
                                    <input id="token" type="password" placeholder="49|..." required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-blue-400">
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">App Slug</label>
                                        <input id="appSlug" type="text" value="tiktok-bot" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-blue-400">
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Channel</label>
                                        <input id="channel" type="text" value="app" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-blue-400">
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Version</label>
                                        <input id="version" type="text" value="1.0.0" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-blue-400">
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Mandatory</label>
                                        <input id="mandatory" type="text" value="1" placeholder="1 or 0" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-blue-400">
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Notes</label>
                                    <textarea id="notes" class="min-h-[120px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-blue-400">Release uploaded from update dashboard.</textarea>
                                </div>
                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">EXE File</label>
                                    <input id="file" type="file" accept=".exe" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 file:mr-4 file:rounded-xl file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:font-semibold file:text-white">
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <button id="uploadBtn" type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white">
                                        <i data-lucide="upload" class="h-4 w-4"></i>
                                        Upload release
                                    </button>
                                    <button type="button" data-channel="app" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700">Use app</button>
                                    <button type="button" data-channel="bot-server" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700">Use bot-server</button>
                                </div>
                            </form>
                            <div id="uploadStatus" class="mt-4 hidden rounded-2xl border px-4 py-3 text-sm font-medium"></div>
                            <pre id="uploadOutput" class="mt-4 hidden overflow-auto rounded-2xl bg-slate-950 p-4 text-xs text-slate-100"></pre>
                        </div>

                        <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-5">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Server files</p>
                                    <h3 class="mt-2 text-2xl font-bold text-slate-900">Quan ly release tren server</h3>
                                </div>
                                <div id="listStatus" class="hidden rounded-2xl border px-4 py-3 text-sm font-medium"></div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-left">
                                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                        <tr>
                                            <th class="px-6 py-4">File</th>
                                            <th class="px-6 py-4">Version</th>
                                            <th class="px-6 py-4">Size</th>
                                            <th class="px-6 py-4">Published</th>
                                            <th class="px-6 py-4">Mode</th>
                                            <th class="px-6 py-4 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="releaseList" class="divide-y divide-slate-100"></tbody>
                                </table>
                            </div>
                            <div id="emptyState" class="hidden px-6 py-10 text-center text-sm text-slate-500">Chua co release nao tren server.</div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <script>
        const root = document.getElementById('app');
        const loginUrl = root.dataset.loginUrl;
        const listUrl = root.dataset.listUrl;
        const publishUrl = root.dataset.publishUrl;
        const stateKey = 'update-dashboard-state';
        const ids = (id) => document.getElementById(id);
        const loginStatus = ids('loginStatus');
        const uploadStatus = ids('uploadStatus');
        const listStatus = ids('listStatus');

        function setStatus(el, type, message) {
            el.className = 'mt-4 block rounded-2xl border px-4 py-3 text-sm font-medium';
            if (type === 'ok') el.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
            else if (type === 'error') el.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-700');
            else el.classList.add('border-slate-200', 'bg-slate-50', 'text-slate-600');
            el.textContent = message;
        }
        function clearStatus(el) { el.className = 'hidden rounded-2xl border px-4 py-3 text-sm font-medium'; el.textContent = ''; }
        function showOutput(el, payload) { el.classList.remove('hidden'); el.textContent = typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2); }
        function hideOutput(el) { el.classList.add('hidden'); el.textContent = ''; }
        function getToken() { return ids('token').value.trim(); }
        function persistState() {
            localStorage.setItem(stateKey, JSON.stringify({
                email: ids('email').value, appSlug: ids('appSlug').value, channel: ids('channel').value,
                version: ids('version').value, mandatory: ids('mandatory').value, notes: ids('notes').value, token: ids('token').value,
            }));
        }
        function hydrateState() {
            try {
                const state = JSON.parse(localStorage.getItem(stateKey) || '{}');
                Object.entries(state).forEach(([key, value]) => { if (ids(key) && value !== undefined) ids(key).value = value; });
            } catch {}
        }
        async function parseResponse(response) {
            const raw = await response.text();
            try { return JSON.parse(raw); } catch { return raw; }
        }
        function syncSession(payload) {
            const name = payload?.user?.name || 'Da dang nhap';
            const email = payload?.user?.email || 'Token da duoc cap.';
            ids('sessionUser').textContent = name; ids('sessionEmail').textContent = email;
            ids('sessionUserAside').textContent = name; ids('sessionEmailAside').textContent = email;
        }
        function renderCounts(releases) {
            const count = String(releases.length);
            const apps = String(new Set(releases.map((item) => item.app_slug)).size);
            ids('releaseCount').textContent = count; ids('releaseCountAside').textContent = count;
            ids('appCount').textContent = apps; ids('appCountAside').textContent = apps;
        }
        async function loadReleases() {
            if (!getToken()) { setStatus(listStatus, 'error', 'Chua co Bearer Token de tai danh sach release.'); return; }
            setStatus(listStatus, 'muted', 'Dang tai danh sach release...');
            const response = await fetch(listUrl, { headers: { Accept: 'application/json', Authorization: 'Bearer ' + getToken() } });
            const payload = await parseResponse(response);
            if (!response.ok) { setStatus(listStatus, 'error', 'Khong tai duoc danh sach release. HTTP ' + response.status + '.'); showOutput(ids('uploadOutput'), payload); return; }
            renderReleases(payload.releases || []);
            setStatus(listStatus, 'ok', 'Da tai xong danh sach release.');
        }
        function renderReleases(releases) {
            const tbody = ids('releaseList');
            tbody.innerHTML = '';
            renderCounts(releases);
            ids('emptyState').classList.toggle('hidden', releases.length > 0);
            releases.forEach((release) => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-blue-50/40';
                tr.innerHTML = `
                    <td class="px-6 py-5">
                        <div class="flex items-start gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-100 text-blue-600">
                                <i data-lucide="${release.channel === 'bot-server' ? 'bot' : 'file-up'}" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-slate-900">${release.filename}</p>
                                <div class="mt-1 flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600">${release.app_slug}</span>
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 font-semibold text-amber-700">${release.channel}</span>
                                </div>
                                <p class="mt-2 text-xs text-slate-400">${release.notes || 'Khong co release note.'}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-5 font-semibold text-slate-900">v${release.version}</td>
                    <td class="px-6 py-5 text-sm text-slate-500">${(release.size / 1048576).toFixed(2)} MB</td>
                    <td class="px-6 py-5 text-sm text-slate-500">${release.published_at || '-'}</td>
                    <td class="px-6 py-5"><span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase ${release.mandatory ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'}">${release.mandatory ? 'Mandatory' : 'Optional'}</span></td>
                    <td class="px-6 py-5"><div class="flex justify-end gap-2">
                        <a class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:text-blue-600" href="${release.download_url}"><i data-lucide="download" class="h-4 w-4"></i></a>
                        <a class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:text-slate-700" href="${release.latest_url}"><i data-lucide="file-json" class="h-4 w-4"></i></a>
                        <button class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-100 text-rose-500 hover:bg-rose-50" type="button" data-delete-url="${release.delete_url}" data-label="${release.app_slug}/${release.channel}"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                    </div></td>`;
                tbody.appendChild(tr);
            });
            lucide.createIcons();
            tbody.querySelectorAll('[data-delete-url]').forEach((button) => {
                button.addEventListener('click', async () => {
                    if (!confirm('Xoa toan bo release cho ' + button.dataset.label + '?')) return;
                    button.disabled = true;
                    try {
                        const response = await fetch(button.dataset.deleteUrl, { method: 'DELETE', headers: { Accept: 'application/json', Authorization: 'Bearer ' + getToken() } });
                        const payload = await parseResponse(response);
                        if (!response.ok) { setStatus(listStatus, 'error', 'Xoa that bai. HTTP ' + response.status + '.'); showOutput(ids('uploadOutput'), payload); return; }
                        setStatus(listStatus, 'ok', 'Da xoa ' + button.dataset.label + '.');
                        await loadReleases();
                    } catch (error) {
                        setStatus(listStatus, 'error', 'Khong gui duoc request xoa.');
                        showOutput(ids('uploadOutput'), String(error));
                    } finally { button.disabled = false; }
                });
            });
        }

        ids('loginForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            ids('loginBtn').disabled = true; clearStatus(loginStatus); hideOutput(ids('loginOutput'));
            try {
                const response = await fetch(loginUrl, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ email: ids('email').value.trim(), password: ids('password').value }) });
                const payload = await parseResponse(response); showOutput(ids('loginOutput'), payload);
                if (!response.ok) { setStatus(loginStatus, 'error', 'Login that bai. HTTP ' + response.status + '.'); return; }
                if (payload?.user?.auth_token) ids('token').value = payload.user.auth_token;
                syncSession(payload); setStatus(loginStatus, 'ok', 'Dang nhap thanh cong. Token da duoc nap vao form upload.'); persistState(); await loadReleases();
            } catch (error) {
                setStatus(loginStatus, 'error', 'Khong ket noi duoc toi API login.'); showOutput(ids('loginOutput'), String(error));
            } finally { ids('loginBtn').disabled = false; }
        });

        ids('uploadForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!getToken()) { setStatus(uploadStatus, 'error', 'Bearer Token la bat buoc.'); return; }
            if (!ids('file').files[0]) { setStatus(uploadStatus, 'error', 'Chua chon file .exe.'); return; }
            ids('uploadBtn').disabled = true; clearStatus(uploadStatus); hideOutput(ids('uploadOutput'));
            const formData = new FormData();
            formData.append('app_slug', ids('appSlug').value.trim());
            formData.append('channel', ids('channel').value.trim() || 'app');
            formData.append('version', ids('version').value.trim());
            formData.append('notes', ids('notes').value);
            formData.append('mandatory', ids('mandatory').value);
            formData.append('file', ids('file').files[0]);
            try {
                const response = await fetch(publishUrl, { method: 'POST', headers: { Accept: 'application/json', Authorization: 'Bearer ' + getToken() }, body: formData });
                const payload = await parseResponse(response); showOutput(ids('uploadOutput'), payload);
                if (!response.ok) { setStatus(uploadStatus, 'error', 'Upload that bai. HTTP ' + response.status + '.'); return; }
                setStatus(uploadStatus, 'ok', 'Upload thanh cong.');
                const token = getToken(); ids('uploadForm').reset(); ids('token').value = token;
                ids('appSlug').value = payload?.release?.app_slug || ''; ids('channel').value = payload?.release?.channel || 'app';
                ids('version').value = payload?.release?.version || ''; ids('notes').value = payload?.release?.notes || '';
                ids('mandatory').value = payload?.release?.mandatory ? '1' : '0'; persistState(); await loadReleases();
            } catch (error) {
                setStatus(uploadStatus, 'error', 'Khong gui duoc request upload.'); showOutput(ids('uploadOutput'), String(error));
            } finally { ids('uploadBtn').disabled = false; }
        });

        document.querySelectorAll('[data-channel]').forEach((button) => button.addEventListener('click', () => { ids('channel').value = button.dataset.channel; persistState(); }));
        ids('refreshBtn').addEventListener('click', () => loadReleases().catch(() => setStatus(listStatus, 'error', 'Refresh that bai.')));
        ids('loginForm').addEventListener('input', persistState);
        ids('uploadForm').addEventListener('input', persistState);
        hydrateState();
        if (getToken()) loadReleases().catch(() => setStatus(listStatus, 'error', 'Khong tai duoc release luc khoi dong.'));
        lucide.createIcons();
    </script>
</body>
</html>
