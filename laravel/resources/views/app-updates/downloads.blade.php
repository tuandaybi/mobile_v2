<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <div>
            <h1 class="text-3xl font-bold">File Server</h1>
            <p class="text-sm text-slate-500 mt-2">Upload và download file đều dùng OTP Telegram.</p>
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

        <section class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
            <div class="mb-6">
                <h2 class="text-xl font-semibold">Upload file</h2>
                <p class="text-sm text-slate-500 mt-1">Nhập Security Code, lấy OTP rồi upload file.</p>
            </div>

            <form id="uploadForm" class="space-y-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Security Code</label>
                    <input id="token" type="password" placeholder="Bearer token" class="w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Tệp</label>
                    <input id="file" type="file" accept=".exe,.zip" required class="w-full rounded-xl border border-slate-300 px-4 py-3">
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
                        Tải bản phát hành
                    </button>
                </div>
            </form>

            <pre id="uploadOutput" class="hidden mt-6 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100"></pre>
        </section>

        <section>
            <div class="mb-4">
                <h2 class="text-xl font-semibold">Danh sách file tải</h2>
                <p class="text-sm text-slate-500 mt-1">Bấm lấy OTP qua Telegram, nhập OTP để tải file. Mỗi mã chỉ dùng 1 lần.</p>
            </div>

            <div class="space-y-4">
                @forelse ($files as $file)
                    @php
                        $isSelected = $selectedAppSlug === ($file['app_slug'] ?? null)
                            && $selectedChannel === ($file['channel'] ?? null)
                            && $selectedFilename === ($file['filename'] ?? null);
                    @endphp
                    <div
                        id="file-{{ md5(($file['app_slug'] ?? '') . '|' . ($file['channel'] ?? '') . '|' . ($file['filename'] ?? '')) }}"
                        data-selected="{{ $isSelected ? '1' : '0' }}"
                        class="rounded-2xl bg-white shadow-sm border p-5 {{ $isSelected ? 'border-blue-500 ring-2 ring-blue-100' : 'border-slate-200' }}"
                    >
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                                        {{ $file['app_slug'] }} / {{ $file['channel'] }}
                                    </span>
                                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                        v{{ $file['version'] }}
                                    </span>
                                </div>
                                <h3 class="text-lg font-semibold break-all">{{ $file['filename'] }}</h3>
                                <p class="text-sm text-slate-500 mt-1">Dung lượng: {{ number_format(($file['size'] ?? 0) / 1048576, 2) }} MB</p>
                                <p class="text-sm text-slate-500">Ngày phát hành: {{ $file['published_at'] ?? '-' }}</p>
                                @if (!empty($file['notes']))
                                    <p class="text-sm text-slate-600 mt-2 whitespace-pre-line">{{ $file['notes'] }}</p>
                                @endif
                            </div>

                            <div class="w-full lg:w-[360px] space-y-3">
                                <form method="POST" action="{{ route('app-updates.request-otp', [], false) }}" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="app_slug" value="{{ $file['app_slug'] }}">
                                    <input type="hidden" name="channel" value="{{ $file['channel'] }}">
                                    <input type="hidden" name="filename" value="{{ $file['filename'] }}">
                                    <button
                                        type="button"
                                        class="w-full rounded-xl bg-amber-500 px-4 py-3 text-sm font-semibold text-white hover:bg-amber-600 transition"
                                        onclick="requestOtp(this.form)"
                                    >
                                        Lấy OTP
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('app-updates.verify-otp', [], false) }}" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="app_slug" value="{{ $file['app_slug'] }}">
                                    <input type="hidden" name="channel" value="{{ $file['channel'] }}">
                                    <input type="hidden" name="filename" value="{{ $file['filename'] }}">
                                    <input
                                        type="text"
                                        name="otp"
                                        inputmode="numeric"
                                        maxlength="6"
                                        placeholder="Nhập OTP 6 số"
                                        class="w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
                                        data-otp-input="{{ $isSelected ? '1' : '0' }}"
                                        required
                                    >
                                    <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 transition">
                                        Xác nhận và tải file
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-10 text-center text-slate-500">
                        Chưa có file nào.
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <script>
        const publishUrl = @json($publishUrl);
        const requestUploadOtpUrl = @json($requestUploadOtpUrl);
        const defaultAppSlug = 'tiktok-bot';
        const defaultChannel = 'app';
        const defaultVersion = '1.0.0';
        const defaultMandatory = '1';
        const defaultNotes = 'Phiên bản được tải lên từ bảng điều khiển cập nhật.';

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

            const appSlug = defaultAppSlug;
            const channel = defaultChannel;
            const version = defaultVersion;

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
                        app_slug: appSlug,
                        channel,
                        version,
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
                alert('Nhập OTP upload trước khi tải bản phát hành');
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
            formData.append('notes', defaultNotes);
            formData.append('mandatory', defaultMandatory);
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
                window.location.href = payload.download_url || window.location.href;
            } catch (error) {
                showOutput(getEl('uploadOutput'), String(error));
                alert('Không gửi được yêu cầu upload (network error)');
            } finally {
                getEl('uploadBtn').disabled = false;
            }
        }

        document.getElementById('uploadForm').addEventListener('submit', handleUpload);
        document.getElementById('requestUploadOtpBtn').addEventListener('click', requestUploadOtp);

        document.addEventListener('DOMContentLoaded', () => {
            const selectedCard = document.querySelector('[data-selected="1"]');
            const selectedOtpInput = document.querySelector('[data-otp-input="1"]');

            if (selectedCard) {
                selectedCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            if (selectedOtpInput) {
                selectedOtpInput.focus();
            }
        });
    </script>
</body>
</html>
