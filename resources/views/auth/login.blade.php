@extends('layouts.app')

@section('title', 'Masuk ke Portal')

@section('content')
<div class="min-h-[calc(100vh-4rem)] flex flex-col justify-center py-8 sm:py-12 px-4 sm:px-6">
    <div class="w-full max-w-md mx-auto">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="h-2 bg-[#00288E] w-full" aria-hidden="true"></div>

            <div class="p-6 sm:p-8">
                <div class="flex items-center gap-3.5 mb-6">
                    <div class="w-12 h-12 rounded-xl bg-[#00288E] text-white flex items-center justify-center shrink-0" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
                            <path d="M9 14l2 2 4-4" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight text-[#0F172A]">
                            Masuk ke Tirtafield DS
                        </h1>
                        <p class="text-sm text-[#475569] mt-0.5">
                            Portal rekapitulasi absensi PERUMDA Air Minum Tirta Kepri
                        </p>
                    </div>
                </div>

                @if ($errors->any())
                    <div role="alert" class="mb-5 rounded-xl border border-rose-300 bg-[#FFF1F2] p-3.5 text-sm text-[#9F1239] flex items-start gap-2.5">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        <div>
                            <p class="font-bold">Gagal masuk</p>
                            <p class="mt-0.5">{{ $errors->first() }}</p>
                        </div>
                    </div>
                @endif

                <form id="loginForm" action="{{ route('login.submit') }}" method="POST" class="space-y-5" novalidate>
                    @csrf

                    <div>
                        <label for="nik" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                            NIK / NIP atau Email
                        </label>
                        <input
                            type="text"
                            id="nik"
                            name="nik"
                            value="{{ old('nik') }}"
                            required
                            autocomplete="username"
                            placeholder="Contoh: P242101 atau nama@tirtakepri.co.id"
                            @if ($errors->has('nik')) aria-invalid="true" @endif
                            class="w-full min-h-[44px] px-3.5 py-2.5 rounded-xl border {{ $errors->has('nik') ? 'border-rose-500' : 'border-slate-300' }} bg-white text-sm text-[#0F172A] placeholder:text-slate-500 focus:border-[#00288E]"
                        >
                        @error('nik')
                            <p class="mt-1.5 text-xs font-semibold text-[#9F1239]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="pin" class="block text-sm font-semibold text-[#0F172A] mb-1.5">
                            PIN atau Kata Sandi
                        </label>
                        <div class="relative">
                            <input
                                type="password"
                                id="pin"
                                name="pin"
                                required
                                autocomplete="current-password"
                                placeholder="Masukkan PIN 6 digit atau kata sandi"
                                @if ($errors->has('pin')) aria-invalid="true" @endif
                                class="w-full min-h-[44px] pl-3.5 pr-12 py-2.5 rounded-xl border {{ $errors->has('pin') ? 'border-rose-500' : 'border-slate-300' }} bg-white text-sm text-[#0F172A] placeholder:text-slate-500 focus:border-[#00288E]"
                            >
                            <button
                                type="button"
                                id="togglePinBtn"
                                aria-label="Tampilkan PIN atau kata sandi"
                                aria-pressed="false"
                                class="absolute inset-y-0 right-0 min-w-[44px] px-3 flex items-center justify-center text-[#475569] hover:text-[#0F172A] rounded-r-xl"
                            >
                                <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                        </div>
                        @error('pin')
                            <p class="mt-1.5 text-xs font-semibold text-[#9F1239]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between">
                        <label for="remember" class="inline-flex items-center gap-2.5 text-sm text-[#475569] min-h-[44px]">
                            <input
                                type="checkbox"
                                id="remember"
                                name="remember"
                                value="1"
                                {{ old('remember') ? 'checked' : '' }}
                                class="w-4 h-4 rounded border-slate-400 text-[#00288E]"
                            >
                            <span>Ingat sesi login saya</span>
                        </label>
                    </div>

                    <button
                        type="submit"
                        id="loginSubmitBtn"
                        class="w-full min-h-[48px] px-5 py-3 rounded-xl bg-[#00288E] hover:bg-[#001F70] active:bg-[#001754] text-white text-sm font-bold tracking-wide transition-colors flex items-center justify-center gap-2"
                    >
                        <span id="loginBtnLabel">Masuk ke Portal</span>
                    </button>
                </form>

                <div class="mt-6 pt-5 border-t border-slate-200 text-xs text-[#475569] leading-relaxed">
                    Gunakan akun dan PIN yang terdaftar pada database aplikasi <span class="font-semibold text-[#0F172A]">Tirtafield</span>.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const pinInput = document.getElementById('pin');
        const toggleBtn = document.getElementById('togglePinBtn');
        const form = document.getElementById('loginForm');
        const submitBtn = document.getElementById('loginSubmitBtn');
        const btnLabel = document.getElementById('loginBtnLabel');

        if (toggleBtn && pinInput) {
            toggleBtn.addEventListener('click', function () {
                const isPassword = pinInput.type === 'password';
                pinInput.type = isPassword ? 'text' : 'password';
                toggleBtn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                toggleBtn.setAttribute('aria-label', isPassword ? 'Sembunyikan PIN atau kata sandi' : 'Tampilkan PIN atau kata sandi');
            });
        }

        if (form && submitBtn && btnLabel) {
            form.addEventListener('submit', function () {
                submitBtn.disabled = true;
                btnLabel.textContent = 'Memverifikasi akun...';
            });
        }
    })();
</script>
@endpush
