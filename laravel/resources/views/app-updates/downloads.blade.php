<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>File Server</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none; }
        .toast-enter { animation: toastIn .25s ease forwards; }
        .toast-leave { animation: toastOut .25s ease forwards; }
        @keyframes toastIn  { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
        @keyframes toastOut { from { opacity:1; transform:translateY(0); } to { opacity:0; transform:translateY(12px); } }
    </style>
</head>
<body class="bg-zinc-100 min-h-screen text-zinc-900 antialiased">

{{-- Toast container --}}
<div id="toastContainer" class="fixed bottom-5 right-5 z-[100] flex flex-col gap-2 pointer-events-none"></div>

@php
    $selectedAppSlug = request('app_slug');
    $selectedChannel = request('channel');
    $selectedFilename = request('filename');
@endphp

<div class="max-w-5xl mx-auto px-4 py-8 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">File Server</h1>
            <p class="text-sm text-zinc-500 mt-0.5">Quản lý và phân phối file với bảo mật OTP</p>
        </div>
        <button id="openUploadBtn" type="button"
            class="flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-black transition-colors shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
            </svg>
            Upload
        </button>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
            <ul class="list-disc pl-4 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- File List --}}
    <div class="rounded-2xl bg-white border border-zinc-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-zinc-100 flex items-center justify-between">
            <h2 class="font-semibold text-base">Danh sách file</h2>
            <span class="text-xs text-zinc-400 font-medium">{{ $files->total() }} file</span>
        </div>

        @if ($files->isEmpty())
            <div class="py-16 text-center text-zinc-400 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto mb-3 text-zinc-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Chưa có file nào
            </div>
        @else
            {{-- Mobile cards --}}
            <div class="block sm:hidden divide-y divide-zinc-100">
                @foreach ($files as $file)
                    @php
                        $hash = md5(($file['app_slug'] ?? '') . '|' . ($file['channel'] ?? '') . '|' . ($file['filename'] ?? ''));
                        $isSelected = $selectedAppSlug === ($file['app_slug'] ?? null)
                            && $selectedChannel === ($file['channel'] ?? null)
                            && $selectedFilename === ($file['filename'] ?? null);
                    @endphp
                    <div id="m-{{ $hash }}" data-selected="{{ $isSelected ? '1' : '0' }}"
                        class="p-4 {{ $isSelected ? 'bg-blue-50' : '' }}">

                        <div class="flex items-start justify-between gap-2">
                            <span class="font-medium text-sm break-all leading-5">{{ $file['filename'] }}</span>
                            @if ($file['otp_protected'] ?? true)
                                <span class="shrink-0 rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">OTP</span>
                            @else
                                <span class="shrink-0 rounded-md bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-500">Tự do</span>
                            @endif
                        </div>

                        <div class="mt-2 flex gap-4 text-xs text-zinc-500">
                            <span>{{ number_format(($file['size'] ?? 0) / 1048576, 2) }} MB</span>
                            <span>{{ !empty($file['published_at']) ? \Illuminate\Support\Carbon::parse($file['published_at'])->format('d/m/Y H:i') : '-' }}</span>
                            <span class="text-zinc-400">{{ ($file['app_slug'] ?? '') }}/{{ ($file['channel'] ?? '') }}</span>
                        </div>

                        <div class="mt-3 space-y-2">
                            @if ($file['otp_protected'] ?? true)
                                <div class="flex gap-2">
                                    <input type="text" inputmode="numeric" maxlength="6"
                                        placeholder="Nhập OTP 6 số"
                                        class="flex-1 rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        data-otp-input="{{ $isSelected ? '1' : '0' }}"
                                        data-app-slug="{{ $file['app_slug'] }}"
                                        data-channel="{{ $file['channel'] }}"
                                        data-filename="{{ $file['filename'] }}"
                                        data-download-requires-otp="1">
                                    <button type="button"
                                        class="rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-600 transition-colors whitespace-nowrap"
                                        data-req-otp-app="{{ $file['app_slug'] }}"
                                        data-req-otp-channel="{{ $file['channel'] }}"
                                        data-req-otp-filename="{{ $file['filename'] }}"
                                        data-file-id="m-{{ $hash }}"
                                        onclick="requestDownloadOtp(this)">
                                        Lấy OTP
                                    </button>
                                </div>
                                <div id="actions-m-{{ $hash }}" class="{{ $isSelected ? 'flex' : 'hidden' }} gap-2">
                            @else
                                <input type="hidden"
                                    data-otp-input="0"
                                    data-app-slug="{{ $file['app_slug'] }}"
                                    data-channel="{{ $file['channel'] }}"
                                    data-filename="{{ $file['filename'] }}"
                                    data-download-requires-otp="0">
                                <div class="flex gap-2">
                            @endif
                                <button type="button"
                                    class="flex-1 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-colors"
                                    onclick="downloadFile(this)"
                                    data-app-slug="{{ $file['app_slug'] }}"
                                    data-channel="{{ $file['channel'] }}"
                                    data-filename="{{ $file['filename'] }}">
                                    Tải file
                                </button>
                                <button type="button"
                                    class="flex-1 rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-100 transition-colors"
                                    onclick="deleteFile(this)"
                                    data-app-slug="{{ $file['app_slug'] }}"
                                    data-channel="{{ $file['channel'] }}"
                                    data-filename="{{ $file['filename'] }}">
                                    Xóa
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Desktop table --}}
            <div class="hidden sm:block overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-zinc-100 bg-zinc-50/60 text-xs font-semibold uppercase tracking-wide text-zinc-400">
                            <th class="px-5 py-3 text-left">File</th>
                            <th class="px-5 py-3 text-left">App / Channel</th>
                            <th class="px-5 py-3 text-left">Dung lượng</th>
                            <th class="px-5 py-3 text-left">Ngày upload</th>
                            <th class="px-5 py-3 text-left w-56">OTP download</th>
                            <th class="px-5 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($files as $file)
                            @php
                                $hash = md5(($file['app_slug'] ?? '') . '|' . ($file['channel'] ?? '') . '|' . ($file['filename'] ?? ''));
                                $isSelected = $selectedAppSlug === ($file['app_slug'] ?? null)
                                    && $selectedChannel === ($file['channel'] ?? null)
                                    && $selectedFilename === ($file['filename'] ?? null);
                            @endphp
                            <tr id="d-{{ $hash }}" data-selected="{{ $isSelected ? '1' : '0' }}"
                                class="hover:bg-zinc-50/50 transition-colors {{ $isSelected ? 'bg-blue-50' : '' }}">

                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <div class="shrink-0 rounded-lg bg-zinc-100 p-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-zinc-500" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <span class="font-medium text-sm break-all">{{ $file['filename'] }}</span>
                                    </div>
                                </td>

                                <td class="px-5 py-3.5 text-sm text-zinc-500 whitespace-nowrap">
                                    <code class="text-xs bg-zinc-100 rounded px-1.5 py-0.5">{{ $file['app_slug'] }}/{{ $file['channel'] }}</code>
                                </td>

                                <td class="px-5 py-3.5 text-sm text-zinc-600 whitespace-nowrap">
                                    {{ number_format(($file['size'] ?? 0) / 1048576, 2) }} MB
                                </td>

                                <td class="px-5 py-3.5 text-sm text-zinc-600 whitespace-nowrap">
                                    {{ !empty($file['published_at']) ? \Illuminate\Support\Carbon::parse($file['published_at'])->format('d/m/Y H:i') : '-' }}
                                </td>

                                <td class="px-5 py-3.5">
                                    @if ($file['otp_protected'] ?? true)
                                        <div class="flex items-center gap-1.5">
                                            <input type="text" inputmode="numeric" maxlength="6"
                                                placeholder="6 chữ số"
                                                class="w-28 rounded-lg border border-zinc-300 px-2.5 py-1.5 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                                data-otp-input="{{ $isSelected ? '1' : '0' }}"
                                                data-app-slug="{{ $file['app_slug'] }}"
                                                data-channel="{{ $file['channel'] }}"
                                                data-filename="{{ $file['filename'] }}"
                                                data-download-requires-otp="1">
                                            <button type="button"
                                                class="rounded-lg bg-amber-500 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-amber-600 transition-colors whitespace-nowrap"
                                                data-req-otp-app="{{ $file['app_slug'] }}"
                                                data-req-otp-channel="{{ $file['channel'] }}"
                                                data-req-otp-filename="{{ $file['filename'] }}"
                                                data-file-id="d-{{ $hash }}"
                                                onclick="requestDownloadOtp(this)">
                                                Lấy OTP
                                            </button>
                                        </div>
                                    @else
                                        <input type="hidden"
                                            data-otp-input="0"
                                            data-app-slug="{{ $file['app_slug'] }}"
                                            data-channel="{{ $file['channel'] }}"
                                            data-filename="{{ $file['filename'] }}"
                                            data-download-requires-otp="0">
                                        <span class="text-xs text-zinc-300">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5">
                                    <div id="actions-d-{{ $hash }}"
                                        class="{{ ($file['otp_protected'] ?? true) && !$isSelected ? 'hidden' : 'flex' }} items-center justify-end gap-2">
                                        <button type="button"
                                            class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 transition-colors"
                                            onclick="downloadFile(this)"
                                            data-app-slug="{{ $file['app_slug'] }}"
                                            data-channel="{{ $file['channel'] }}"
                                            data-filename="{{ $file['filename'] }}">
                                            Tải file
                                        </button>
                                        <button type="button"
                                            class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-100 transition-colors"
                                            onclick="deleteFile(this)"
                                            data-app-slug="{{ $file['app_slug'] }}"
                                            data-channel="{{ $file['channel'] }}"
                                            data-filename="{{ $file['filename'] }}">
                                            Xóa
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($files->hasPages())
            <div class="border-t border-zinc-100 px-5 py-3">
                {{ $files->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Upload Modal --}}
<div id="uploadModal" class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center bg-black/50 backdrop-blur-sm p-0 sm:p-4">
    <div class="w-full max-w-lg rounded-t-3xl sm:rounded-2xl bg-white shadow-2xl max-h-[96vh] overflow-y-auto">

        <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4">
            <div>
                <h2 class="font-semibold text-base">Upload file mới</h2>
                <p class="text-xs text-zinc-500 mt-0.5">Xác thực bằng Security Code + OTP</p>
            </div>
            <button id="closeUploadBtn" type="button"
                class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>

        <form id="uploadForm" class="p-5 space-y-4">

            <div>
                <label class="block text-xs font-semibold text-zinc-500 uppercase tracking-wide mb-1.5">Security Code</label>
                <input id="token" type="password" autocomplete="off" placeholder="Bearer token"
                    class="w-full rounded-xl border border-zinc-300 px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 uppercase tracking-wide mb-1.5">App Slug</label>
                    <input id="appSlug" type="text" placeholder="vd: my-app"
                        class="w-full rounded-xl border border-zinc-300 px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 uppercase tracking-wide mb-1.5">Channel</label>
                    <input id="channel" type="text" placeholder="vd: app"
                        class="w-full rounded-xl border border-zinc-300 px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-500 uppercase tracking-wide mb-1.5">Version</label>
                <input id="version" type="text" placeholder="vd: 1.0.0"
                    class="w-full rounded-xl border border-zinc-300 px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-500 uppercase tracking-wide mb-1.5">File</label>
                <label class="flex flex-col items-center gap-2 w-full cursor-pointer rounded-xl border-2 border-dashed border-zinc-300 px-4 py-5 text-center hover:border-blue-400 hover:bg-blue-50/30 transition" id="fileDropZone">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <span id="fileLabel" class="text-sm text-zinc-500">Chọn hoặc kéo thả file vào đây</span>
                    <input id="file" type="file" required class="hidden" onchange="updateFileLabel(this)">
                </label>
            </div>

            <div class="flex items-center justify-between rounded-xl bg-zinc-50 border border-zinc-200 px-4 py-3">
                <div>
                    <span class="text-sm font-medium text-zinc-700">Download cần OTP</span>
                    <p class="text-xs text-zinc-500 mt-0.5">Người tải phải nhập OTP qua Telegram</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input id="otpProtected" type="checkbox" checked class="sr-only peer">
                    <div class="w-10 h-5 bg-zinc-200 peer-focus:ring-2 peer-focus:ring-blue-200 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                </label>
            </div>

            <div id="uploadOtpSection" class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 uppercase tracking-wide mb-1.5">OTP Upload</label>
                    <div class="flex gap-2">
                        <input id="uploadOtp" type="text" inputmode="numeric" maxlength="6" placeholder="6 chữ số"
                            class="flex-1 rounded-xl border border-zinc-300 px-3.5 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                        <button id="requestUploadOtpBtn" type="button"
                            class="rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 transition-colors whitespace-nowrap disabled:opacity-60">
                            Lấy OTP
                        </button>
                    </div>
                </div>
            </div>

            <button id="uploadBtn" type="submit"
                class="w-full rounded-xl bg-zinc-900 py-3 text-sm font-semibold text-white hover:bg-black transition-colors shadow-sm disabled:opacity-60 disabled:cursor-not-allowed">
                Upload file
            </button>

            <pre id="uploadOutput" class="hidden overflow-auto rounded-xl bg-zinc-950 p-4 text-xs text-zinc-100 leading-relaxed"></pre>
        </form>
    </div>
</div>

{{-- Hidden form for download --}}
<form id="downloadForm" method="POST" action="{{ route('app-updates.verify-otp', [], false) }}" class="hidden">
    @csrf
    <input type="hidden" name="app_slug">
    <input type="hidden" name="channel">
    <input type="hidden" name="filename">
    <input type="hidden" name="otp">
</form>

<script>
    const publishUrl          = @json($publishUrl);
    const requestUploadOtpUrl = @json($requestUploadOtpUrl);
    const deleteWithOtpUrl    = @json($deleteWithOtpUrl);
    const csrfToken           = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    /* ── helpers ───────────────────────────────────────────────── */
    const $ = (id) => document.getElementById(id);
    const getToken       = () => $('token').value.trim();
    const getUploadOtp   = () => $('uploadOtp').value.trim();
    const isOtpProtected = () => $('otpProtected').checked;

    function findOtpInput(appSlug, channel, filename) {
        return document.querySelector(
            `[data-app-slug="${appSlug}"][data-channel="${channel}"][data-filename="${filename}"][data-otp-input]`
        );
    }

    async function parseResponse(res) {
        const text = await res.text();
        try { return JSON.parse(text); } catch { return text; }
    }

    /* ── toast ─────────────────────────────────────────────────── */
    function toast(msg, type = 'info') {
        const colors = {
            success: 'bg-emerald-600',
            error:   'bg-rose-600',
            info:    'bg-zinc-800',
            warning: 'bg-amber-500',
        };
        const el = document.createElement('div');
        el.className = `pointer-events-auto toast-enter max-w-sm rounded-xl px-4 py-3 text-sm text-white shadow-lg ${colors[type] ?? colors.info}`;
        el.textContent = msg;
        $('toastContainer').appendChild(el);
        setTimeout(() => {
            el.classList.replace('toast-enter', 'toast-leave');
            el.addEventListener('animationend', () => el.remove(), { once: true });
        }, 3500);
    }

    /* ── modal ──────────────────────────────────────────────────── */
    function openModal()  { $('uploadModal').classList.replace('hidden','flex'); }
    function closeModal() { $('uploadModal').classList.replace('flex','hidden'); }

    function syncOtpSection() {
        $('uploadOtpSection').classList.toggle('hidden', !isOtpProtected());
    }

    function updateFileLabel(input) {
        $('fileLabel').textContent = input.files[0]?.name ?? 'Chọn hoặc kéo thả file vào đây';
    }

    /* ── download OTP request ───────────────────────────────────── */
    async function requestDownloadOtp(btn) {
        const appSlug  = btn.dataset.reqOtpApp;
        const channel  = btn.dataset.reqOtpChannel;
        const filename = btn.dataset.reqOtpFilename;
        const fileId   = btn.dataset.fileId;

        btn.disabled = true;
        const orig = btn.textContent;
        btn.textContent = '...';

        try {
            const res = await fetch('{{ route("app-updates.request-otp", [], false) }}', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: new URLSearchParams({ app_slug: appSlug, channel, filename }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                toast(data.message || 'Đã gửi OTP qua Telegram', 'success');
                const actionsEl = document.getElementById('actions-' + fileId);
                if (actionsEl) {
                    actionsEl.classList.remove('hidden');
                    actionsEl.classList.add('flex');
                }
                const input = findOtpInput(appSlug, channel, filename);
                input?.focus();
            } else {
                toast(data.message || 'Không gửi được OTP', 'error');
            }
        } catch {
            toast('Lỗi kết nối', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = orig;
        }
    }

    /* ── upload OTP request ─────────────────────────────────────── */
    async function requestUploadOtp() {
        if (!isOtpProtected()) { toast('Upload không dùng OTP', 'warning'); return; }

        const token   = getToken();
        const appSlug = $('appSlug').value.trim();
        const channel = $('channel').value.trim();
        const version = $('version').value.trim();

        if (!token)   { toast('Nhập Security Code trước', 'warning'); return; }
        if (!appSlug) { toast('Nhập App Slug trước', 'warning'); return; }
        if (!version) { toast('Nhập Version trước', 'warning'); return; }

        const btn = $('requestUploadOtpBtn');
        btn.disabled = true;

        try {
            const res = await fetch(requestUploadOtpUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + token,
                },
                body: new URLSearchParams({
                    app_slug: appSlug,
                    channel: channel || 'app',
                    version,
                }),
            });
            const data = await parseResponse(res);
            if (res.ok) {
                toast(data.message || 'Đã gửi OTP upload qua Telegram', 'success');
                $('uploadOtp').focus();
            } else {
                toast((data?.message) || 'Không gửi được OTP', 'error');
            }
        } catch {
            toast('Lỗi kết nối', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    /* ── upload ─────────────────────────────────────────────────── */
    async function handleUpload(e) {
        e.preventDefault();

        const token   = getToken();
        const appSlug = $('appSlug').value.trim();
        const channel = $('channel').value.trim();
        const version = $('version').value.trim();
        const otp     = getUploadOtp();
        const file    = $('file').files[0];

        if (!token)   { toast('Nhập Security Code', 'warning'); return; }
        if (!appSlug) { toast('Nhập App Slug', 'warning'); return; }
        if (!version) { toast('Nhập Version', 'warning'); return; }
        if (!file)    { toast('Chọn file cần upload', 'warning'); return; }
        if (isOtpProtected() && !otp) { toast('Nhập OTP upload', 'warning'); return; }

        const btn = $('uploadBtn');
        btn.disabled = true;
        btn.textContent = 'Đang upload...';
        $('uploadOutput').classList.add('hidden');

        const fd = new FormData();
        fd.append('app_slug', appSlug);
        fd.append('channel', channel || 'app');
        fd.append('version', version);
        fd.append('otp_protected', isOtpProtected() ? '1' : '0');
        fd.append('otp', otp);
        fd.append('file', file);

        try {
            const res  = await fetch(publishUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token },
                body: fd,
            });
            const data = await parseResponse(res);

            if (!res.ok) {
                $('uploadOutput').classList.remove('hidden');
                $('uploadOutput').textContent = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
                toast((data?.message) || 'Upload thất bại', 'error');
            } else {
                toast(data.message || 'Upload thành công!', 'success');
                closeModal();
                setTimeout(() => window.location.reload(), 800);
            }
        } catch {
            toast('Lỗi kết nối khi upload', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Upload file';
        }
    }

    /* ── download ───────────────────────────────────────────────── */
    function downloadFile(btn) {
        const { appSlug, channel, filename } = btn.dataset;
        const input = findOtpInput(appSlug, channel, filename);
        const otp   = input?.value?.trim() ?? '';

        if (input?.dataset?.downloadRequiresOtp === '1' && !otp) {
            toast('Nhập OTP trước khi tải file', 'warning');
            input.focus();
            return;
        }

        const form = $('downloadForm');
        form.elements.app_slug.value = appSlug;
        form.elements.channel.value  = channel;
        form.elements.filename.value = filename;
        form.elements.otp.value      = otp;
        form.submit();
    }

    /* ── delete ─────────────────────────────────────────────────── */
    async function deleteFile(btn) {
        const { appSlug, channel, filename } = btn.dataset;
        const input = findOtpInput(appSlug, channel, filename);
        const otp   = input?.value?.trim() ?? '';

        if (!otp) {
            toast('Nhập OTP trước khi xóa', 'warning');
            input?.focus();
            return;
        }

        if (!confirm(`Xóa file "${filename}"?`)) return;

        btn.disabled = true;

        try {
            const res  = await fetch(deleteWithOtpUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: new URLSearchParams({ app_slug: appSlug, channel, filename, otp }),
            });
            const data = await parseResponse(res);

            if (res.ok) {
                toast(data.message || 'Đã xóa file', 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                toast((data?.message) || 'Xóa thất bại', 'error');
                btn.disabled = false;
            }
        } catch {
            toast('Lỗi kết nối', 'error');
            btn.disabled = false;
        }
    }

    /* ── event listeners ────────────────────────────────────────── */
    $('openUploadBtn').addEventListener('click', openModal);
    $('closeUploadBtn').addEventListener('click', closeModal);
    $('uploadModal').addEventListener('click', (e) => { if (e.target === $('uploadModal')) closeModal(); });
    $('uploadForm').addEventListener('submit', handleUpload);
    $('requestUploadOtpBtn').addEventListener('click', requestUploadOtp);
    $('otpProtected').addEventListener('change', syncOtpSection);

    document.addEventListener('DOMContentLoaded', () => {
        syncOtpSection();

        const sel = document.querySelector('[data-selected="1"]');
        if (sel) sel.scrollIntoView({ behavior: 'smooth', block: 'center' });

        const selOtp = document.querySelector('[data-otp-input="1"]');
        if (selOtp) selOtp.focus();
    });
</script>
</body>
</html>
