<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Control</title>
    <style>
        :root {
            --bg: #eee6d9;
            --panel: rgba(255, 251, 245, 0.88);
            --panel-strong: rgba(255, 251, 245, 0.96);
            --ink: #182033;
            --muted: #5f6877;
            --line: rgba(24, 32, 51, 0.12);
            --accent: #b45309;
            --accent-soft: rgba(180, 83, 9, 0.12);
            --ok: #0f766e;
            --danger: #b91c1c;
            --shadow: 0 18px 48px rgba(24, 32, 51, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Georgia, "Times New Roman", serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(180, 83, 9, 0.18), transparent 26%),
                radial-gradient(circle at bottom right, rgba(15, 118, 110, 0.12), transparent 24%),
                linear-gradient(135deg, #f7f2e8, #ece2d1 58%, #e5d7c4);
        }

        .wrap {
            width: min(1320px, calc(100% - 32px));
            margin: 0 auto;
            padding: 28px 0 48px;
        }

        .hero,
        .panel {
            border: 1px solid var(--line);
            border-radius: 28px;
            background: var(--panel);
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
        }

        .hero {
            display: grid;
            gap: 10px;
            padding: 24px 28px;
            margin-bottom: 18px;
        }

        .eyebrow {
            margin: 0;
            color: var(--accent);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
        }

        h1 {
            margin: 0;
            font-size: clamp(34px, 5vw, 64px);
            line-height: 0.94;
        }

        .sub {
            margin: 0;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.7;
            max-width: 760px;
        }

        .login-shell {
            display: grid;
            grid-template-columns: 1.3fr 0.7fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .panel {
            padding: 22px;
        }

        .panel h2 {
            margin: 0 0 12px;
            font-size: 28px;
        }

        .panel p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .field {
            display: grid;
            gap: 8px;
            margin-top: 14px;
        }

        .field-wide {
            grid-column: 1 / -1;
        }

        label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--muted);
        }

        input,
        textarea,
        button {
            font: inherit;
        }

        input,
        textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 13px 14px;
            background: rgba(255, 255, 255, 0.82);
            color: var(--ink);
        }

        textarea {
            min-height: 126px;
            resize: vertical;
        }

        .actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .btn {
            border: 1px solid var(--line);
            border-radius: 999px;
            min-height: 46px;
            padding: 0 16px;
            background: #fff;
            color: var(--ink);
            font-weight: 700;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--ink);
            color: #fff;
            border-color: var(--ink);
        }

        .btn-danger {
            color: var(--danger);
            border-color: rgba(185, 28, 28, 0.2);
            background: rgba(255, 255, 255, 0.72);
        }

        .btn:disabled {
            opacity: 0.65;
            cursor: progress;
        }

        .summary {
            display: grid;
            gap: 10px;
        }

        .summary-box {
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: var(--panel-strong);
        }

        .summary-box strong {
            display: block;
            font-size: 24px;
            margin-bottom: 4px;
        }

        .dashboard {
            display: grid;
            grid-template-columns: 0.92fr 1.08fr;
            gap: 18px;
        }

        .stack {
            display: grid;
            gap: 18px;
        }

        .status {
            margin-top: 14px;
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.68);
            display: none;
        }

        .status.ok {
            display: block;
            border-color: rgba(15, 118, 110, 0.28);
            color: var(--ok);
        }

        .status.error {
            display: block;
            border-color: rgba(185, 28, 28, 0.28);
            color: var(--danger);
        }

        .token-hint {
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: 16px;
            background: var(--accent-soft);
            color: #9a3412;
        }

        .list {
            display: grid;
            gap: 14px;
            margin-top: 18px;
        }

        .release {
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 18px;
            background: var(--panel-strong);
        }

        .release-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: start;
        }

        .release-title {
            margin: 0;
            font-size: 24px;
        }

        .pill {
            display: inline-block;
            margin-top: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: var(--accent-soft);
            color: var(--accent);
        }

        .release-version {
            font-size: 24px;
            font-weight: 700;
            white-space: nowrap;
        }

        .release-meta {
            display: grid;
            gap: 8px;
            margin: 14px 0;
            color: var(--muted);
            font-size: 14px;
        }

        .release-meta strong {
            color: var(--ink);
        }

        .release-notes {
            margin: 0 0 14px;
            padding: 14px;
            border-radius: 16px;
            border: 1px solid rgba(24, 32, 51, 0.08);
            background: rgba(255, 255, 255, 0.72);
            line-height: 1.6;
            color: var(--ink);
        }

        .release-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .empty {
            margin-top: 18px;
            padding: 20px;
            border: 1px dashed var(--line);
            border-radius: 20px;
            color: var(--muted);
            background: rgba(255, 255, 255, 0.52);
        }

        pre {
            margin: 14px 0 0;
            padding: 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(24, 32, 51, 0.08);
            overflow: auto;
            color: var(--ink);
            font-size: 13px;
            line-height: 1.5;
            font-family: Consolas, "Courier New", monospace;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 980px) {
            .login-shell,
            .dashboard,
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div
        class="wrap"
        data-login-url="{{ $loginUrl }}"
        data-list-url="{{ $listUrl }}"
        data-publish-url="{{ $publishUrl }}"
    >
        <section class="hero">
            <p class="eyebrow">Update control</p>
            <h1>Release center</h1>
            <p class="sub">Dang nhap bang tai khoan Laravel de lay Sanctum token. Sau do dashboard se cho phep upload file `.exe`, xem danh sach release tren server, download va xoa tron bo metadata + binary cua tung channel.</p>
        </section>

        <section class="login-shell">
            <div class="panel">
                <h2>Login</h2>
                <p>Dung luon API login hien tai cua Laravel. Token lay duoc se tu dong do vao form upload, nhung form van bat buoc co Bearer Token moi cho submit.</p>

                <form id="loginForm">
                    <div class="grid">
                        <div class="field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" autocomplete="username" required>
                        </div>
                        <div class="field">
                            <label for="password">Password</label>
                            <input id="password" name="password" type="password" autocomplete="current-password" required>
                        </div>
                    </div>

                    <div class="actions">
                        <button class="btn btn-primary" id="loginBtn" type="submit">Login and get token</button>
                    </div>
                </form>

                <div id="loginStatus" class="status"></div>
                <pre id="loginOutput" class="hidden"></pre>
            </div>

            <div class="panel">
                <h2>Session</h2>
                <div class="summary">
                    <div class="summary-box">
                        <strong id="sessionUser">Chua dang nhap</strong>
                        <span id="sessionEmail">Token se xuat hien sau khi login thanh cong.</span>
                    </div>
                    <div class="summary-box">
                        <strong id="releaseCount">0</strong>
                        <span>Release channel tren server</span>
                    </div>
                    <div class="summary-box">
                        <strong id="appCount">0</strong>
                        <span>App dang co metadata update</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard">
            <div class="stack">
                <div class="panel">
                    <h2>Upload</h2>
                    <p>Boi vi route publish duoc bao ve boi Sanctum, form nay bat buoc phai co Bearer Token hop le moi duoc gui.</p>

                    <form id="uploadForm">
                        <div class="field">
                            <label for="token">Bearer Token</label>
                            <input id="token" name="token" type="password" placeholder="49|..." required>
                        </div>

                        <div class="grid">
                            <div class="field">
                                <label for="appSlug">App Slug</label>
                                <input id="appSlug" name="appSlug" type="text" value="tiktok-bot" required>
                            </div>
                            <div class="field">
                                <label for="channel">Channel</label>
                                <input id="channel" name="channel" type="text" value="app" required>
                            </div>
                            <div class="field">
                                <label for="version">Version</label>
                                <input id="version" name="version" type="text" value="1.0.0" required>
                            </div>
                            <div class="field">
                                <label for="mandatory">Mandatory</label>
                                <input id="mandatory" name="mandatory" type="text" value="1" placeholder="1 or 0">
                            </div>
                        </div>

                        <div class="field">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes">Release uploaded from update dashboard.</textarea>
                        </div>

                        <div class="field">
                            <label for="file">EXE File</label>
                            <input id="file" name="file" type="file" accept=".exe" required>
                        </div>

                        <div class="actions">
                            <button class="btn btn-primary" id="uploadBtn" type="submit">Upload release</button>
                            <button class="btn" type="button" data-channel="app">Use app</button>
                            <button class="btn" type="button" data-channel="bot-server">Use bot-server</button>
                        </div>
                    </form>

                    <div class="token-hint">Token o tren co the duoc do tu dong sau khi login, nhung mày van co the thay the bang token khac de thao tac.</div>
                    <div id="uploadStatus" class="status"></div>
                    <pre id="uploadOutput" class="hidden"></pre>
                </div>
            </div>

            <div class="panel">
                <div class="release-head">
                    <div>
                        <h2>Server files</h2>
                        <p>Moi item tuong ung mot `app_slug/channel`. Xoa se xoa ca metadata `latest.json` va toan bo file trong thu muc channel do.</p>
                    </div>
                    <div class="actions">
                        <button class="btn" id="refreshBtn" type="button">Refresh</button>
                    </div>
                </div>

                <div id="listStatus" class="status"></div>
                <div id="releaseList" class="list"></div>
                <div id="emptyState" class="empty hidden">Chua co release nao tren server.</div>
            </div>
        </section>
    </div>

    <script>
        const root = document.querySelector('.wrap');
        const loginUrl = root.dataset.loginUrl;
        const listUrl = root.dataset.listUrl;
        const publishUrl = root.dataset.publishUrl;
        const stateKey = 'update-dashboard-state';
        const channelInput = document.getElementById('channel');

        const loginForm = document.getElementById('loginForm');
        const uploadForm = document.getElementById('uploadForm');
        const loginBtn = document.getElementById('loginBtn');
        const uploadBtn = document.getElementById('uploadBtn');
        const refreshBtn = document.getElementById('refreshBtn');

        const loginStatus = document.getElementById('loginStatus');
        const loginOutput = document.getElementById('loginOutput');
        const uploadStatus = document.getElementById('uploadStatus');
        const uploadOutput = document.getElementById('uploadOutput');
        const listStatus = document.getElementById('listStatus');
        const releaseList = document.getElementById('releaseList');
        const emptyState = document.getElementById('emptyState');

        const sessionUser = document.getElementById('sessionUser');
        const sessionEmail = document.getElementById('sessionEmail');
        const releaseCount = document.getElementById('releaseCount');
        const appCount = document.getElementById('appCount');

        function setStatus(el, type, message) {
            el.className = 'status ' + type;
            el.textContent = message;
        }

        function clearStatus(el) {
            el.className = 'status';
            el.textContent = '';
        }

        function showOutput(el, payload) {
            el.classList.remove('hidden');
            el.textContent = typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2);
        }

        function hideOutput(el) {
            el.classList.add('hidden');
            el.textContent = '';
        }

        function getToken() {
            return document.getElementById('token').value.trim();
        }

        function persistState() {
            const state = {
                email: document.getElementById('email').value,
                appSlug: document.getElementById('appSlug').value,
                channel: document.getElementById('channel').value,
                version: document.getElementById('version').value,
                mandatory: document.getElementById('mandatory').value,
                notes: document.getElementById('notes').value,
                token: document.getElementById('token').value,
            };
            localStorage.setItem(stateKey, JSON.stringify(state));
        }

        function hydrateState() {
            try {
                const state = JSON.parse(localStorage.getItem(stateKey) || '{}');
                Object.entries(state).forEach(([key, value]) => {
                    const el = document.getElementById(key);
                    if (el && value !== undefined) {
                        el.value = value;
                    }
                });
            } catch (error) {
                console.error(error);
            }
        }

        async function parseResponse(response) {
            const rawText = await response.text();
            try {
                return JSON.parse(rawText);
            } catch (error) {
                return rawText;
            }
        }

        async function loadReleases() {
            const token = getToken();
            if (!token) {
                setStatus(listStatus, 'error', 'Chua co Bearer Token de tai danh sach release.');
                return;
            }

            setStatus(listStatus, 'ok', 'Dang tai danh sach release...');

            const response = await fetch(listUrl, {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + token,
                },
            });

            const payload = await parseResponse(response);

            if (!response.ok) {
                setStatus(listStatus, 'error', 'Khong tai duoc danh sach release. HTTP ' + response.status + '.');
                showOutput(loginOutput, payload);
                return;
            }

            renderReleases(payload.releases || []);
            setStatus(listStatus, 'ok', 'Da tai xong danh sach release.');
        }

        function renderReleases(releases) {
            releaseList.innerHTML = '';
            releaseCount.textContent = String(releases.length);
            appCount.textContent = String(new Set(releases.map((item) => item.app_slug)).size);

            if (!releases.length) {
                emptyState.classList.remove('hidden');
                return;
            }

            emptyState.classList.add('hidden');

            releases.forEach((release) => {
                const article = document.createElement('article');
                article.className = 'release';
                article.innerHTML = `
                    <div class="release-head">
                        <div>
                            <h3 class="release-title">${release.app_slug}</h3>
                            <span class="pill">${release.channel}</span>
                        </div>
                        <div class="release-version">v${release.version}</div>
                    </div>
                    <div class="release-meta">
                        <div><strong>Published:</strong> ${release.published_at || '-'}</div>
                        <div><strong>Size:</strong> ${(release.size / 1048576).toFixed(2)} MB</div>
                        <div><strong>File:</strong> ${release.filename}</div>
                        <div><strong>SHA256:</strong> ${release.sha256 || '-'}</div>
                    </div>
                    <p class="release-notes">${release.notes || 'Khong co release note.'}</p>
                    <div class="release-actions">
                        <a class="btn btn-primary" href="${release.download_url}">Download</a>
                        <a class="btn" href="${release.latest_url}">Latest JSON</a>
                        <button class="btn btn-danger" type="button" data-delete-url="${release.delete_url}" data-label="${release.app_slug}/${release.channel}">Delete</button>
                    </div>
                `;

                releaseList.appendChild(article);
            });

            releaseList.querySelectorAll('[data-delete-url]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const label = button.dataset.label;
                    if (!confirm('Xoa toan bo release cho ' + label + '?')) {
                        return;
                    }

                    button.disabled = true;

                    try {
                        const response = await fetch(button.dataset.deleteUrl, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'Authorization': 'Bearer ' + getToken(),
                            },
                        });

                        const payload = await parseResponse(response);

                        if (!response.ok) {
                            setStatus(listStatus, 'error', 'Xoa that bai. HTTP ' + response.status + '.');
                            showOutput(uploadOutput, payload);
                            return;
                        }

                        setStatus(listStatus, 'ok', 'Da xoa ' + label + '.');
                        await loadReleases();
                    } catch (error) {
                        setStatus(listStatus, 'error', 'Khong gui duoc request xoa.');
                    } finally {
                        button.disabled = false;
                    }
                });
            });
        }

        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            loginBtn.disabled = true;
            clearStatus(loginStatus);
            hideOutput(loginOutput);

            try {
                const response = await fetch(loginUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: document.getElementById('email').value.trim(),
                        password: document.getElementById('password').value,
                    }),
                });

                const payload = await parseResponse(response);
                showOutput(loginOutput, payload);

                if (!response.ok) {
                    setStatus(loginStatus, 'error', 'Login that bai. HTTP ' + response.status + '.');
                    return;
                }

                const token = payload?.user?.auth_token;
                if (token) {
                    document.getElementById('token').value = token;
                }

                sessionUser.textContent = payload?.user?.name || 'Da dang nhap';
                sessionEmail.textContent = payload?.user?.email || 'Token da duoc cap.';
                setStatus(loginStatus, 'ok', 'Dang nhap thanh cong. Token da duoc nap vao form upload.');
                persistState();
                await loadReleases();
            } catch (error) {
                setStatus(loginStatus, 'error', 'Khong ket noi duoc toi API login.');
                showOutput(loginOutput, String(error));
            } finally {
                loginBtn.disabled = false;
            }
        });

        uploadForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const token = getToken();
            const file = document.getElementById('file').files[0];

            if (!token) {
                setStatus(uploadStatus, 'error', 'Bearer Token la bat buoc.');
                return;
            }

            if (!file) {
                setStatus(uploadStatus, 'error', 'Chua chon file .exe.');
                return;
            }

            uploadBtn.disabled = true;
            clearStatus(uploadStatus);
            hideOutput(uploadOutput);

            const formData = new FormData();
            formData.append('app_slug', document.getElementById('appSlug').value.trim());
            formData.append('channel', document.getElementById('channel').value.trim() || 'app');
            formData.append('version', document.getElementById('version').value.trim());
            formData.append('notes', document.getElementById('notes').value);
            formData.append('mandatory', document.getElementById('mandatory').value);
            formData.append('file', file);

            try {
                const response = await fetch(publishUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + token,
                    },
                    body: formData,
                });

                const payload = await parseResponse(response);
                showOutput(uploadOutput, payload);

                if (!response.ok) {
                    setStatus(uploadStatus, 'error', 'Upload that bai. HTTP ' + response.status + '.');
                    return;
                }

                setStatus(uploadStatus, 'ok', 'Upload thanh cong.');
                persistState();
                uploadForm.reset();
                document.getElementById('token').value = token;
                document.getElementById('appSlug').value = payload?.release?.app_slug || '';
                document.getElementById('channel').value = payload?.release?.channel || 'app';
                document.getElementById('version').value = payload?.release?.version || '';
                document.getElementById('notes').value = payload?.release?.notes || '';
                document.getElementById('mandatory').value = payload?.release?.mandatory ? '1' : '0';
                await loadReleases();
            } catch (error) {
                setStatus(uploadStatus, 'error', 'Khong gui duoc request upload.');
                showOutput(uploadOutput, String(error));
            } finally {
                uploadBtn.disabled = false;
            }
        });

        document.querySelectorAll('[data-channel]').forEach((button) => {
            button.addEventListener('click', () => {
                channelInput.value = button.dataset.channel;
                persistState();
            });
        });

        refreshBtn.addEventListener('click', async () => {
            try {
                await loadReleases();
            } catch (error) {
                setStatus(listStatus, 'error', 'Refresh that bai.');
            }
        });

        hydrateState();
        if (getToken()) {
            loadReleases().catch(() => {
                setStatus(listStatus, 'error', 'Khong tai duoc release luc khoi dong.');
            });
        }
        uploadForm.addEventListener('input', persistState);
        loginForm.addEventListener('input', persistState);
    </script>
</body>
</html>
