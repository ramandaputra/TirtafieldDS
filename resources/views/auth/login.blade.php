@extends('layouts.app')

@section('title', 'Masuk ke Portal')

@section('content')
<div class="min-h-screen flex flex-col justify-center py-10 px-4 sm:px-6">
    <div class="w-full max-w-md mx-auto">
        <div class="pusimpel-card p-6 sm:p-8">
            <div class="text-center mb-6">
                <div class="profile-container mx-auto mb-4" aria-hidden="true">
                    <div class="w-full h-full icon-tile cat-pelanggan">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
                            <path d="M9 14l2 2 4-4" />
                        </svg>
                    </div>
                </div>

                <h1 class="text-2xl sm:text-3xl font-extrabold user-name-gradient">
                    Masuk ke Tirtafield DS
                </h1>
                <p class="text-xs sm:text-sm text-[#cbd5e1] mt-2">
                    Portal rekapitulasi absensi PERUMDA Air Minum Tirta Kepri
                </p>
            </div>

            <hr class="divider-glass mb-6">

            @if ($errors->any())
                <div role="alert" class="mb-5 rounded-2xl border p-3.5 text-sm flex items-start gap-2.5 badge-rose">
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
                    <label for="nik" class="block text-sm font-semibold text-[#e2e8f0] mb-1.5">
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
                        class="w-full min-h-[44px] px-4 py-2.5 rounded-full text-sm pusimpel-input {{ $errors->has('nik') ? 'border-rose-400' : '' }}"
                    >
                    @error('nik')
                        <p class="mt-1.5 text-xs font-semibold text-[#fda4af]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="pin" class="block text-sm font-semibold text-[#e2e8f0] mb-1.5">
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
                            class="w-full min-h-[44px] pl-4 pr-12 py-2.5 rounded-full text-sm pusimpel-input {{ $errors->has('pin') ? 'border-rose-400' : '' }}"
                        >
                        <button
                            type="button"
                            id="togglePinBtn"
                            aria-label="Tampilkan PIN atau kata sandi"
                            aria-pressed="false"
                            class="absolute inset-y-0 right-0 min-w-[44px] px-3 flex items-center justify-center text-[#cbd5e1] hover:text-white rounded-r-full"
                        >
                            <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                    @error('pin')
                        <p class="mt-1.5 text-xs font-semibold text-[#fda4af]">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between">
                    <label for="remember" class="inline-flex items-center gap-2.5 text-sm text-[#cbd5e1] min-h-[44px]">
                        <input
                            type="checkbox"
                            id="remember"
                            name="remember"
                            value="1"
                            {{ old('remember') ? 'checked' : '' }}
                            class="w-4 h-4 rounded accent-[#38bdf8]"
                        >
                        <span>Ingat sesi login saya</span>
                    </label>
                </div>

                <button
                    type="submit"
                    id="loginSubmitBtn"
                    class="w-full min-h-[48px] px-5 py-3 rounded-full text-sm tracking-wide pusimpel-btn-primary flex items-center justify-center gap-2"
                >
                    <span id="loginBtnLabel">Masuk ke Portal</span>
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-white/10 text-center text-xs text-[#94a3b8] leading-relaxed">
                Gunakan akun dan PIN yang terdaftar pada database aplikasi <strong class="text-[#e2e8f0]">Tirtafield</strong>.
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
