<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tải file</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="max-w-5xl mx-auto px-4 py-10">
        <div class="mb-8">
            <h1 class="text-3xl font-bold">Danh sách file tải</h1>
            <p class="text-sm text-slate-500 mt-2">Bấm lấy OTP qua Telegram, nhập OTP để tải file. Mỗi mã chỉ dùng 1 lần.</p>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                <ul class="list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @php
            $selectedAppSlug = request('app_slug');
            $selectedChannel = request('channel');
            $selectedFilename = request('filename');
        @endphp

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
                            <h2 class="text-lg font-semibold break-all">{{ $file['filename'] }}</h2>
                            <p class="text-sm text-slate-500 mt-1">Dung lượng: {{ number_format(($file['size'] ?? 0) / 1048576, 2) }} MB</p>
                            <p class="text-sm text-slate-500">Ngày phát hành: {{ $file['published_at'] ?? '-' }}</p>
                            @if (!empty($file['notes']))
                                <p class="text-sm text-slate-600 mt-2 whitespace-pre-line">{{ $file['notes'] }}</p>
                            @endif
                        </div>

                        <div class="w-full lg:w-[360px] space-y-3">
                            <form method="POST" action="{{ route('app-updates.request-otp') }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="app_slug" value="{{ $file['app_slug'] }}">
                                <input type="hidden" name="channel" value="{{ $file['channel'] }}">
                                <input type="hidden" name="filename" value="{{ $file['filename'] }}">
                                <button
                                    type="button"
                                    class="w-full rounded-xl bg-amber-500 px-4 py-3 text-sm font-semibold text-white hover:bg-amber-600 transition"
                                    onclick="requestOtp(this.form)"
                                >
                                    Gửi OTP qua Telegram
                                </button>
                            </form>

                            <form method="POST" action="{{ route('app-updates.verify-otp') }}" class="space-y-3">
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
    </div>

    <script>
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
