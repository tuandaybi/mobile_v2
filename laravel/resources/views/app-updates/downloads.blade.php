<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>File Server</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900">
    @php
        $selectedAppSlug = request('app_slug');
        $selectedChannel = request('channel');
        $selectedFilename = request('filename');
    @endphp

    <div class="max-w-6xl mx-auto px-4 py-8 space-y-8">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold">File Server</h1>
                <p class="text-sm text-slate-500 mt-2">Trang upload và download file đơn giản.</p>
            </div>
            <button id="openUploadModalBtn" type="button" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-black transition">
                Upload file
            </button>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                <ul class="list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section id="downloads" class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-xl font-semibold">Danh sách file</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">File</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Dung lượng</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Ngày upload</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">OTP</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($files as $file)
                            @php
                                $isSelected = $selectedAppSlug === ($file['app_slug'] ?? null)
                                    && $selectedChannel === ($file['channel'] ?? null)
                                    && $selectedFilename === ($file['filename'] ?? null);
                            @endphp
                            <tr id="file-{{ md5(($file['app_slug'] ?? '') . '|' . ($file['channel'] ?? '') . '|' . ($file['filename'] ?? '')) }}" data-selected="{{ $isSelected ? '1' : '0' }}" class="{{ $isSelected ? 'bg-blue-50' : '' }}">
                                <td class="px-6 py-4 align-top">
                                    <div class="font-semibold text-slate-900 break-all">{{ $file['filename'] }}</div>
                                </td>
                                <td class="px-6 py-4 align-top text-sm text-slate-700 whitespace-nowrap">{{ number_format(($file['size'] ?? 0) / 1048576, 2) }} MB</td>
                                <td class="px-6 py-4 align-top text-sm text-slate-700 whitespace-nowrap">{{ !empty($file['published_at']) ? \Illuminate\Support\Carbon::parse($file['published_at'])->format('d/m/Y H:i') : '-' }}</td>
                                <td class="px-6 py-4 align-top">
                                    <div class="space-y-2 min-w-[220px]">
                                        <form method="POST" action="{{ route('app-updates.request-otp', [], false) }}">
                                            @csrf
                                            <input type="hidden" name="app_slug" value="{{ $file['app_slug'] }}">
                                            <input type="hidden" name="channel" value="{{ $file['channel'] }}">
                                            <input type="hidden" name="filename" value="{{ $file['filename'] }}">
                                            <button type="button" class="w-full rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-600 transition" onclick="requestOtp(this.form)">
                                                Lấy OTP
                                            </button>
                                        </form>
                                        <input
                                            type="text"
                                            inputmode="numeric"
                                            maxlength="6"
                                            placeholder="Nhập OTP 6 số"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500"
                                            data-otp-input="{{ $isSelected ? '1' : '0' }}"
                                            data-app-slug="{{ $file['app_slug'] }}"
                                            data-channel="{{ $file['channel'] }}"
                                            data-filename="{{ $file['filename'] }}"
                                        >
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition"
                                            onclick="downloadFile(this)"
                                            data-app-slug="{{ $file['app_slug'] }}"
                                            data-channel="{{ $file['channel'] }}"
                                            data-filename="{{ $file['filename'] }}"
                                        >
                                            Tải file
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700 transition"
                                            onclick="deleteFile(this)"
                                            data-app-slug="{{ $file['app_slug'] }}"
                                            data-channel="{{ $file['channel'] }}"
                                            data-filename="{{ $file['filename'] }}"
                                        >
                                            Xóa file
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-slate-500">Chưa có file nào.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div id="uploadModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-xl rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h2 class="text-xl font-semibold">Upload file</h2>
                    <p class="text-sm text-slate-500 mt-1">Nhập Security Code, lấy OTP rồi upload file.</p>
                </div>
                <button id="closeUploadModalBtn" type="button" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900">X</button>
            </div>
            <div class="p-6">
                <form id="uploadForm" class="space-y-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Security Code</label>
                        <input id="token" type="password" placeholder="Bearer token" class="w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Tệp</label>
                        <input id="file" type="file" required class="w-full rounded-xl border border-slate-300 px-4 py-3">
                    </div>

                    <div class="grid gap-4 md:grid-cols-[1fr_auto] items-end">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">OTP upload</label>
                            <input id="uploadOtp" type="text" inputmode="numeric" maxlength="6" placeholder="Nhập OTP upload" class="w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500">
                        </div>
                        <button id="requestUploadOtpBtn" type="button" class="rounded-xl bg-amber-500 px-5 py-3 font-semibold text-white hover:bg-amber-600 transition">
                            Lấy OTP
                        </button>
                    </div>

                    <div>
                        <button id="uploadBtn" type="submit" class="rounded-xl bg-slate-900 px-6 py-3 font-semibold text-white hover:bg-black transition">
                            Upload
                        </button>
                    </div>
                </form>

                <pre id="uploadOutput" class="hidden mt-6 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100"></pre>
            </div>
        </div>
    </div>

    <form id="downloadForm" method="POST" action="{{ route('app-updates.verify-otp', [], false) }}" class="hidden">
        @csrf
        <input type="hidden" name="app_slug">
        <input type="hidden" name="channel">
        <input type="hidden" name="filename">
        <input type="hidden" name="otp">
    </form>

    <script>
        const publishUrl = @json($publishUrl);
        const requestUploadOtpUrl = @json($requestUploadOtpUrl);
        const deleteWithOtpUrl = @json($deleteWithOtpUrl);
        const defaultAppSlug = 'tiktok-bot';
        const defaultChannel = 'app';
        const defaultVersion = '1.0.0';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const getEl = (id) => document.getElementById(id);
        const getToken = () => getEl('token').value.trim();
        const getUploadOtp = () => getEl('uploadOtp').value.trim();

        function showOutput(el, payload) {
            if (!el) return;
            el.classList.remove('hidden');
            el.textContent = typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2);
        }

        function hideOutput(el) {
            if (!el) return;
            el.classList.add('hidden');
            el.textContent = '';
        }

        function openUploadModal() {
            getEl('uploadModal').classList.remove('hidden');
            getEl('uploadModal').classList.add('flex');
        }

        function closeUploadModal() {
            getEl('uploadModal').classList.add('hidden');
            getEl('uploadModal').classList.remove('flex');
        }

        function findOtpInput(appSlug, channel, filename) {
            return document.querySelector(`[data-app-slug="${appSlug}"][data-channel="${channel}"][data-filename="${filename}"]`);
        }

        async function parseResponse(response) {
            const raw = await response.text();
            try { return JSON.parse(raw); } catch { return raw; }
        }

        async function requestOtp(form) {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                alert(payload.message || 'Không gửi được OTP.');
                return;
            }

            alert(payload.message || 'Đã gửi OTP qua Telegram.');
        }

        async function requestUploadOtp() {
            const token = getToken();
            if (!token) {
                alert('Nhập Security Code trước khi gửi OTP upload');
                return;
            }

            const btn = getEl('requestUploadOtpBtn');
            btn.disabled = true;

            try {
                const response = await fetch(requestUploadOtpUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        Authorization: 'Bearer ' + token,
                    },
                    body: new URLSearchParams({
                        app_slug: defaultAppSlug,
                        channel: defaultChannel,
                        version: defaultVersion,
                    }),
                });

                const payload = await parseResponse(response);
                if (!response.ok) {
                    alert((payload && payload.message) ? payload.message : 'Không gửi được OTP upload');
                    return;
                }

                alert(payload.message || 'Đã gửi OTP upload qua Telegram');
                getEl('uploadOtp').focus();
            } catch (error) {
                alert('Không gửi được OTP upload (network error)');
            } finally {
                btn.disabled = false;
            }
        }

        async function handleUpload(event) {
            event.preventDefault();
            getEl('uploadBtn').disabled = true;
            hideOutput(getEl('uploadOutput'));

            const token = getToken();
            if (!token) {
                alert('Nhập Security Code trước khi upload');
                getEl('uploadBtn').disabled = false;
                return;
            }

            if (!getUploadOtp()) {
                alert('Nhập OTP upload trước khi upload');
                getEl('uploadBtn').disabled = false;
                return;
            }

            const file = getEl('file').files[0];
            if (!file) {
                alert('Chọn file trước khi upload');
                getEl('uploadBtn').disabled = false;
                return;
            }

            const formData = new FormData();
            formData.append('app_slug', defaultAppSlug);
            formData.append('channel', defaultChannel);
            formData.append('version', defaultVersion);
            formData.append('otp_protected', '1');
            formData.append('otp', getUploadOtp());
            formData.append('file', file);

            try {
                const response = await fetch(publishUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        Authorization: 'Bearer ' + token,
                    },
                    body: formData,
                });

                const payload = await parseResponse(response);
                showOutput(getEl('uploadOutput'), payload);

                if (!response.ok) {
                    alert((payload && payload.message) ? payload.message : 'Upload thất bại');
                    return;
                }

                alert(payload.message || 'Upload thành công');
                window.location.reload();
            } catch (error) {
                showOutput(getEl('uploadOutput'), String(error));
                alert('Không gửi được yêu cầu upload (network error)');
            } finally {
                getEl('uploadBtn').disabled = false;
            }
        }

        function downloadFile(button) {
            const appSlug = button.dataset.appSlug;
            const channel = button.dataset.channel;
            const filename = button.dataset.filename;
            const otpInput = findOtpInput(appSlug, channel, filename);
            const otp = otpInput?.value?.trim() || '';

            if (!otp) {
                alert('Nhập OTP trước khi tải file');
                otpInput?.focus();
                return;
            }

            const form = getEl('downloadForm');
            form.elements.app_slug.value = appSlug;
            form.elements.channel.value = channel;
            form.elements.filename.value = filename;
            form.elements.otp.value = otp;
            form.submit();
        }

        async function deleteFile(button) {
            const appSlug = button.dataset.appSlug;
            const channel = button.dataset.channel;
            const filename = button.dataset.filename;
            const otpInput = findOtpInput(appSlug, channel, filename);
            const otp = otpInput?.value?.trim() || '';

            if (!otp) {
                alert('Nhập OTP trước khi xóa file');
                otpInput?.focus();
                return;
            }

            if (!confirm(`Xóa file ${filename}?`)) {
                return;
            }

            const response = await fetch(deleteWithOtpUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: new URLSearchParams({
                    app_slug: appSlug,
                    channel,
                    filename,
                    otp,
                }),
            });

            const payload = await parseResponse(response);

            if (!response.ok) {
                alert((payload && payload.message) ? payload.message : 'Xóa file thất bại');
                return;
            }

            alert(payload.message || 'Đã xóa file');
            window.location.reload();
        }

        document.getElementById('uploadForm').addEventListener('submit', handleUpload);
        document.getElementById('requestUploadOtpBtn').addEventListener('click', requestUploadOtp);
        document.getElementById('openUploadModalBtn').addEventListener('click', openUploadModal);
        document.getElementById('closeUploadModalBtn').addEventListener('click', closeUploadModal);

        document.addEventListener('DOMContentLoaded', () => {
            const selectedRow = document.querySelector('[data-selected="1"]');
            const selectedOtpInput = document.querySelector('[data-otp-input="1"]');

            if (selectedRow) {
                selectedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            if (selectedOtpInput) {
                selectedOtpInput.focus();
            }
        });
    </script>
</body>
</html>
