<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>File Server</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .toast-enter { animation: toastIn .25s ease forwards; }
        .toast-leave { animation: toastOut .25s ease forwards; }
        @keyframes toastIn  { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
        @keyframes toastOut { from{opacity:1;transform:translateY(0)} to{opacity:0;transform:translateY(12px)} }
    </style>
</head>
<body class="bg-zinc-100 min-h-screen text-zinc-900 antialiased">

<div id="toastContainer" class="fixed bottom-5 right-5 z-[100] flex flex-col gap-2 pointer-events-none"></div>

<div class="max-w-4xl mx-auto px-4 py-8 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">File Server</h1>
            <p class="text-sm text-zinc-500 mt-0.5">Quản lý file với bảo mật OTP</p>
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
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
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
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-medium text-sm break-all leading-5">{{ $file['filename'] }}</span>
                            @if ($file['otp_protected'] ?? true)
                                <span class="shrink-0 rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">OTP</span>
                            @else
                                <span class="shrink-0 rounded-md bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-500">Tự do</span>
                            @endif
                        </div>
                        <div class="mt-1.5 flex gap-4 text-xs text-zinc-500">
                            <span>{{ number_format(($file['size'] ?? 0) / 1048576, 2) }} MB</span>
                            <span>{{ !empty($file['published_at']) ? \Illuminate\Support\Carbon::parse($file['published_at'])->format('d/m/Y H:i') : '-' }}</span>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <button type="button"
                                class="flex-1 rounded-lg bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition-colors"
                                data-filename="{{ $file['filename'] }}"
                                data-requires-otp="{{ ($file['otp_protected'] ?? true) ? '1' : '0' }}"
                                onclick="handleDownload(this)">
                                Tải file
                            </button>
                            <button type="button"
                                class="flex-1 rounded-lg bg-rose-50 border border-rose-200 px-3 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-100 transition-colors"
                                data-filename="{{ $file['filename'] }}"
                                onclick="handleDelete(this)">
                                Xóa
                            </button>
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
                            <th class="px-5 py-3 text-left">Dung lượng</th>
                            <th class="px-5 py-3 text-left">Ngày upload</th>
                            <th class="px-5 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($files as $file)
                            <tr class="hover:bg-zinc-50/50 transition-colors">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <div class="shrink-0 rounded-lg bg-zinc-100 p-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-zinc-500" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="font-medium text-sm break-all">{{ $file['filename'] }}</div>
                                            @if ($file['otp_protected'] ?? true)
                                                <span class="text-xs text-amber-600 font-medium">OTP</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-sm text-zinc-600 whitespace-nowrap">
                                    {{ number_format(($file['size'] ?? 0) / 1048576, 2) }} MB
                                </td>
                                <td class="px-5 py-3.5 text-sm text-zinc-600 whitespace-nowrap">
                                    {{ !empty($file['published_at']) ? \Illuminate\Support\Carbon::parse($file['published_at'])->format('d/m/Y H:i') : '-' }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button"
                                            class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 transition-colors"
                                            data-filename="{{ $file['filename'] }}"
                                            data-requires-otp="{{ ($file['otp_protected'] ?? true) ? '1' : '0' }}"
                                            onclick="handleDownload(this)">
                                            Tải file
                                        </button>
                                        <button type="button"
                                            class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-100 transition-colors"
                                            data-filename="{{ $file['filename'] }}"
                                            onclick="handleDelete(this)">
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
            <div class="border-t border-zinc-100 px-5 py-3">{{ $files->links() }}</div>
        @endif
    </div>
</div>

{{-- ═══════════════════════════════════════════
     UPLOAD MODAL (multi-step)
═══════════════════════════════════════════ --}}
<div id="uploadModal" class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center bg-black/50 backdrop-blur-sm p-0 sm:p-4">
    <div class="w-full max-w-md rounded-t-3xl sm:rounded-2xl bg-white shadow-2xl max-h-[96vh] overflow-y-auto">

        <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4">
            <div>
                <h2 id="uploadModalTitle" class="font-semibold text-base">Upload file</h2>
                <div class="flex items-center gap-1 mt-1.5">
                    <span id="dot1" class="h-2 w-2 rounded-full bg-zinc-900 transition-colors"></span>
                    <span id="dot2" class="h-2 w-2 rounded-full bg-zinc-200 transition-colors"></span>
                    <span id="dot3" class="h-2 w-2 rounded-full bg-zinc-200 transition-colors"></span>
                </div>
            </div>
            <button id="closeUploadBtn" type="button"
                class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>

        {{-- Step 1: chọn file --}}
        <div id="uploadStep1" class="p-5 space-y-4">
            <div>
                <label class="block text-xs font-semibold text-zinc-500 uppercase tracking-wide mb-1.5">File</label>
                <label class="flex flex-col items-center gap-2 w-full cursor-pointer rounded-xl border-2 border-dashed border-zinc-300 px-4 py-6 text-center hover:border-blue-400 hover:bg-blue-50/30 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <span id="fileLabel" class="text-sm text-zinc-500">Chọn hoặc kéo thả file vào đây</span>
                    <input id="file" type="file" required class="hidden" onchange="updateFileLabel(this)">
                </label>
            </div>
            <button id="uploadStep1Btn" type="button"
                class="w-full rounded-xl bg-zinc-900 py-3 text-sm font-semibold text-white hover:bg-black transition-colors disabled:opacity-60">
                Tiếp tục
            </button>
        </div>

        {{-- Step 2: nhập OTP --}}
        <div id="uploadStep2" class="hidden p-5 space-y-4">
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                OTP đã được gửi qua Telegram. Mã có hiệu lực 5 phút.
            </div>
            <div>
                <label class="block text-xs font-semibold text-zinc-500 uppercase tracking-wide mb-1.5">Nhập OTP</label>
                <input id="uploadOtp" type="text" inputmode="numeric" maxlength="6" placeholder="6 chữ số"
                    class="w-full rounded-xl border border-zinc-300 px-3.5 py-2.5 text-sm text-center tracking-widest text-lg outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
            </div>
            <button id="uploadStep2Btn" type="button"
                class="w-full rounded-xl bg-zinc-900 py-3 text-sm font-semibold text-white hover:bg-black transition-colors disabled:opacity-60">
                Xác nhận &amp; Upload
            </button>
            <div class="flex items-center gap-3">
                <button id="resendUploadOtpBtn" type="button" data-cooldown-key="upload"
                    class="text-xs text-amber-600 hover:text-amber-700 disabled:text-zinc-400 disabled:cursor-not-allowed transition-colors">
                    Gửi lại OTP
                </button>
                <span class="text-zinc-200">|</span>
                <button id="uploadBackBtn" type="button" class="text-xs text-zinc-400 hover:text-zinc-600 transition-colors">
                    Quay lại
                </button>
            </div>
            <pre id="uploadOutput" class="hidden overflow-auto rounded-xl bg-zinc-950 p-4 text-xs text-zinc-100 leading-relaxed"></pre>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════
     OTP CONFIRM MODAL (download / delete)
═══════════════════════════════════════════ --}}
<div id="otpModal" class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center bg-black/50 backdrop-blur-sm p-0 sm:p-4">
    <div class="w-full max-w-sm rounded-t-3xl sm:rounded-2xl bg-white shadow-2xl">
        <div class="px-5 pt-5 pb-4">
            <p id="otpModalLabel" class="text-sm font-semibold text-zinc-800 mb-1"></p>
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-3 py-2.5 text-xs text-amber-800 mb-4">
                OTP đã được gửi qua Telegram. Mã có hiệu lực 5 phút.
            </div>
            <input id="otpModalInput" type="text" inputmode="numeric" maxlength="6" placeholder="6 chữ số"
                class="w-full rounded-xl border border-zinc-300 px-3.5 py-2.5 text-sm text-center tracking-widest text-lg outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition mb-3">
            <button id="otpModalConfirmBtn" type="button"
                class="w-full rounded-xl bg-zinc-900 py-2.5 text-sm font-semibold text-white hover:bg-black transition-colors disabled:opacity-60 mb-2">
                Xác nhận
            </button>
            <div class="flex items-center justify-between">
                <button id="resendDownloadOtpBtn" type="button"
                    class="text-xs text-amber-600 hover:text-amber-700 disabled:text-zinc-400 disabled:cursor-not-allowed transition-colors">
                    Gửi lại OTP
                </button>
                <button id="otpModalCancelBtn" type="button" class="text-xs text-zinc-400 hover:text-zinc-600 transition-colors">
                    Hủy
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Hidden download form --}}
<form id="downloadForm" method="POST" action="{{ route('app-updates.verify-otp', [], false) }}" class="hidden">
    @csrf
    <input type="hidden" name="filename">
    <input type="hidden" name="otp">
</form>

<script>
    const uploadUrl           = @json($uploadUrl);
    const requestUploadOtpUrl = @json($requestUploadOtpUrl);
    const deleteWithOtpUrl    = @json($deleteWithOtpUrl);
    const csrfToken           = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const $ = (id) => document.getElementById(id);
    const getUploadOtp = () => $('uploadOtp').value.trim();

    async function parseResponse(res) {
        const t = await res.text();
        try { return JSON.parse(t); } catch { return t; }
    }

    /* ── toast ───────────────────────────────────── */
    function toast(msg, type = 'info') {
        const colors = { success:'bg-emerald-600', error:'bg-rose-600', info:'bg-zinc-800', warning:'bg-amber-500' };
        const el = document.createElement('div');
        el.className = `pointer-events-auto toast-enter max-w-sm rounded-xl px-4 py-3 text-sm text-white shadow-lg ${colors[type] ?? colors.info}`;
        el.textContent = msg;
        $('toastContainer').appendChild(el);
        setTimeout(() => {
            el.classList.replace('toast-enter', 'toast-leave');
            el.addEventListener('animationend', () => el.remove(), { once: true });
        }, 3500);
    }

    /* ── cooldown ────────────────────────────────── */
    const cooldowns = {};
    function startCooldown(key, btn, seconds = 60) {
        cooldowns[key] = Date.now() + seconds * 1000;
        tickCooldown(key, btn, seconds);
    }
    function tickCooldown(key, btn, seconds) {
        const rem = Math.ceil((cooldowns[key] - Date.now()) / 1000);
        if (rem <= 0) { btn.disabled = false; btn.textContent = 'Gửi lại OTP'; return; }
        btn.disabled = true;
        btn.textContent = `Gửi lại (${rem}s)`;
        setTimeout(() => tickCooldown(key, btn, seconds), 1000);
    }

    /* ── upload modal ─────────────────────────────── */
    function openUploadModal() {
        $('uploadModal').classList.replace('hidden', 'flex');
        showUploadStep(1);
    }
    function closeUploadModal() {
        $('uploadModal').classList.replace('flex', 'hidden');
        showUploadStep(1);
        $('uploadOtp').value = '';
        $('uploadOutput').classList.add('hidden');
    }
    function showUploadStep(step) {
        $('uploadStep1').classList.toggle('hidden', step !== 1);
        $('uploadStep2').classList.toggle('hidden', step !== 2);
        const dots = [$('dot1'), $('dot2'), $('dot3')];
        dots.forEach((d, i) => {
            d.classList.toggle('bg-zinc-900', i < step);
            d.classList.toggle('bg-zinc-200', i >= step);
        });
        const titles = ['', 'Upload file', 'Xác nhận OTP', 'Đang xử lý…'];
        $('uploadModalTitle').textContent = titles[step] ?? 'Upload file';
    }
    function updateFileLabel(input) {
        $('fileLabel').textContent = input.files[0]?.name ?? 'Chọn hoặc kéo thả file vào đây';
    }

    /* Step 1 → gửi OTP */
    async function uploadStep1() {
        const file = $('file').files[0];
        if (!file) { toast('Chọn file cần upload', 'warning'); return; }

        const btn = $('uploadStep1Btn');
        btn.disabled = true; btn.textContent = 'Đang gửi OTP…';

        try {
            const res  = await fetch(requestUploadOtpUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            });
            const data = await parseResponse(res);
            if (res.ok) {
                showUploadStep(2);
                $('uploadOtp').focus();
                startCooldown('upload', $('resendUploadOtpBtn'));
            } else {
                toast(data?.message || 'Không gửi được OTP', data?.message?.includes('phút') ? 'warning' : 'error');
            }
        } catch { toast('Lỗi kết nối', 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Tiếp tục'; }
    }

    /* Step 2 → upload file */
    async function uploadStep2() {
        const otp  = getUploadOtp();
        const file = $('file').files[0];
        if (!otp) { toast('Nhập OTP', 'warning'); $('uploadOtp').focus(); return; }

        const btn = $('uploadStep2Btn');
        btn.disabled = true; btn.textContent = 'Đang upload…';
        showUploadStep(3);
        $('uploadOutput').classList.add('hidden');

        const fd = new FormData();
        fd.append('otp_protected', '1');
        fd.append('otp', otp);
        fd.append('file', file);

        try {
            const res  = await fetch(uploadUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: fd,
            });
            const data = await parseResponse(res);
            if (!res.ok) {
                showUploadStep(2);
                $('uploadOutput').classList.remove('hidden');
                $('uploadOutput').textContent = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
                toast(data?.message || 'Upload thất bại', 'error');
            } else {
                toast(data.message || 'Upload thành công!', 'success');
                closeUploadModal();
                setTimeout(() => window.location.reload(), 800);
            }
        } catch { showUploadStep(2); toast('Lỗi kết nối khi upload', 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Xác nhận & Upload'; }
    }

    /* Gửi lại OTP upload */
    async function resendUploadOtp() {
        const btn = $('resendUploadOtpBtn');
        btn.disabled = true;
        try {
            const res  = await fetch(requestUploadOtpUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            });
            const data = await parseResponse(res);
            if (res.ok) { toast('Đã gửi lại OTP', 'success'); startCooldown('upload', btn); }
            else { toast(data?.message || 'Không gửi được OTP', 'warning'); btn.disabled = false; }
        } catch { toast('Lỗi kết nối', 'error'); btn.disabled = false; }
    }

    /* ── OTP confirm modal (download / delete) ─────── */
    let _otpAction = null; // { type:'download'|'delete', filename }

    function openOtpModal(filename, actionLabel) {
        $('otpModalLabel').textContent = actionLabel + ': ' + filename;
        $('otpModalInput').value = '';
        $('otpModal').classList.replace('hidden', 'flex');
        $('otpModalInput').focus();
    }
    function closeOtpModal() {
        $('otpModal').classList.replace('flex', 'hidden');
        _otpAction = null;
    }

    async function autoSendOtp(filename, forDelete = false) {
        const body = new URLSearchParams({ filename });
        if (forDelete) body.append('for_delete', '1');
        const res  = await fetch('{{ route("app-updates.request-otp", [], false) }}', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) { throw new Error(data?.message || 'Không gửi được OTP'); }
        return data;
    }

    async function handleDownload(btn) {
        const { filename, requiresOtp } = btn.dataset;

        if (requiresOtp === '0') {
            submitDownloadForm(filename, '');
            return;
        }

        btn.disabled = true;
        try {
            await autoSendOtp(filename, false);
            _otpAction = { type: 'download', filename };
            openOtpModal(filename, 'Tải file');
            startCooldown('dl:' + filename, $('resendDownloadOtpBtn'));
        } catch (e) {
            toast(e.message, e.message.includes('phút') ? 'warning' : 'error');
        } finally { btn.disabled = false; }
    }

    async function handleDelete(btn) {
        const { filename } = btn.dataset;
        if (!confirm(`Xóa file "${filename}"?`)) return;

        btn.disabled = true;
        try {
            await autoSendOtp(filename, true);
            _otpAction = { type: 'delete', filename };
            openOtpModal(filename, 'Xóa file');
            startCooldown('dl:' + filename, $('resendDownloadOtpBtn'));
        } catch (e) {
            toast(e.message, e.message.includes('phút') ? 'warning' : 'error');
        } finally { btn.disabled = false; }
    }

    async function confirmOtpAction() {
        if (!_otpAction) return;
        const otp = $('otpModalInput').value.trim();
        if (!otp) { toast('Nhập OTP', 'warning'); $('otpModalInput').focus(); return; }

        const btn = $('otpModalConfirmBtn');
        btn.disabled = true; btn.textContent = 'Đang xử lý…';

        try {
            if (_otpAction.type === 'download') {
                closeOtpModal();
                submitDownloadForm(_otpAction.filename, otp);
            } else {
                await doDelete(_otpAction.filename, otp);
                closeOtpModal();
            }
        } finally { btn.disabled = false; btn.textContent = 'Xác nhận'; }
    }

    async function resendDownloadOtp() {
        if (!_otpAction) return;
        const forDelete = _otpAction.type === 'delete';
        const btn = $('resendDownloadOtpBtn');
        btn.disabled = true;
        try {
            await autoSendOtp(_otpAction.filename, forDelete);
            toast('Đã gửi lại OTP', 'success');
            startCooldown('dl:' + _otpAction.filename, btn);
        } catch (e) {
            toast(e.message, 'warning');
            btn.disabled = false;
        }
    }

    /* ── helpers ─────────────────────────────────── */
    function submitDownloadForm(filename, otp) {
        const form = $('downloadForm');
        form.elements.filename.value = filename;
        form.elements.otp.value      = otp;
        form.submit();
    }

    async function doDelete(filename, otp) {
        const res  = await fetch(deleteWithOtpUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: new URLSearchParams({ filename, otp }),
        });
        const data = await parseResponse(res);
        if (res.ok) { toast(data.message || 'Đã xóa file', 'success'); setTimeout(() => window.location.reload(), 800); }
        else { toast(data?.message || 'Xóa thất bại', 'error'); }
    }

    /* ── listeners ───────────────────────────────── */
    $('openUploadBtn').addEventListener('click', openUploadModal);
    $('closeUploadBtn').addEventListener('click', closeUploadModal);
    $('uploadModal').addEventListener('click', (e) => { if (e.target === $('uploadModal')) closeUploadModal(); });
    $('uploadStep1Btn').addEventListener('click', uploadStep1);
    $('uploadStep2Btn').addEventListener('click', uploadStep2);
    $('uploadBackBtn').addEventListener('click', () => showUploadStep(1));
    $('resendUploadOtpBtn').addEventListener('click', resendUploadOtp);
    $('otpModalConfirmBtn').addEventListener('click', confirmOtpAction);
    $('otpModalCancelBtn').addEventListener('click', closeOtpModal);
    $('otpModal').addEventListener('click', (e) => { if (e.target === $('otpModal')) closeOtpModal(); });
    $('resendDownloadOtpBtn').addEventListener('click', resendDownloadOtp);
    $('otpModalInput').addEventListener('keydown', (e) => { if (e.key === 'Enter') confirmOtpAction(); });
    $('uploadOtp').addEventListener('keydown', (e) => { if (e.key === 'Enter') uploadStep2(); });
</script>
</body>
</html>
