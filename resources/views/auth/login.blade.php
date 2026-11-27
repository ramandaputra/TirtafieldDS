@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
<div class="min-h-screen flex flex-col justify-center py-10 px-4 sm:px-6">
    <div class="w-full max-w-[380px] mx-auto">
        <div class="pusimpel-card p-6 sm:p-8">
            <div class="text-center mb-6 space-y-3">
                <img src="{{ asset('build/favicon.png') }}" alt="" class="w-12 h-12 rounded-xl object-contain mx-auto" aria-hidden="true">

                <h1 class="text-xl font-extrabold user-name-gradient">
                    Masuk ke Tirtafield DS
                </h1>
            </div>

            @if (session('status'))
                <div role="status" class="mb-4 rounded-xl border p-3 text-xs font-semibold badge-cyan">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="mb-4 rounded-xl border p-3 text-xs font-semibold badge-rose">
                    {{ $errors->first() }}
                </div>
            @endif

            <form id="loginForm" action="{{ route('login.submit') }}" method="POST" class="space-y-4" novalidate>
                @csrf

                <div>
                    <label for="nik" class="block text-xs font-semibold text-[#cbd5e1] mb-1.5">
                        NIK / NIP atau Email
                    </label>
                    <input
                        type="text"
                        id="nik"
                        name="nik"
                        value="{{ old('nik') }}"
                        required
                        @if (! old('nik') && ! $errors->has('pin')) autofocus @endif
                        autocomplete="username"
                        placeholder="NIK atau email"
                        @if ($errors->has('nik')) aria-invalid="true" @endif
                        class="w-full min-h-[44px] px-3.5 py-2 rounded-xl text-sm pusimpel-input {{ $errors->has('nik') ? 'border-rose-400' : '' }}"
                    >
                    @error('nik')
                        <p class="mt-1 text-xs font-semibold text-[#fda4af]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="pin" class="block text-xs font-semibold text-[#cbd5e1] mb-1.5">
                        PIN atau Kata Sandi
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="pin"
                            name="pin"
                            required
                            autocomplete="current-password"
                            placeholder="PIN atau kata sandi"
                            @if ($errors->has('pin')) aria-invalid="true" @endif
                            class="w-full min-h-[44px] pl-3.5 pr-11 py-2 rounded-xl text-sm pusimpel-input {{ $errors->has('pin') ? 'border-rose-400' : '' }}"
                        >
                        <button
                            type="button"
                            id="togglePinBtn"
                            aria-label="Tampilkan PIN atau kata sandi"
                            aria-pressed="false"
                            class="absolute inset-y-0 right-0 min-w-[44px] px-3 flex items-center justify-center text-[#94a3b8] hover:text-white rounded-r-xl"
                        >
                            <svg id="eyeIconShow" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg id="eyeIconHide" class="hidden" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94" />
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19" />
                                <line x1="1" y1="1" x2="23" y2="23" />
                            </svg>
                        </button>
                    </div>
                    @error('pin')
                        <p class="mt-1 text-xs font-semibold text-[#fda4af]">{{ $message }}</p>
                    @enderror
                </div>

                <label for="remember" class="inline-flex items-center gap-2 text-xs text-[#cbd5e1] min-h-[36px]">
                    <input
                        type="checkbox"
                        id="remember"
                        name="remember"
                        value="1"
                        {{ old('remember') ? 'checked' : '' }}
                        class="w-4 h-4 rounded accent-[#38bdf8]"
                    >
                    <span>Ingat saya</span>
                </label>

                <button
                    type="submit"
                    id="loginSubmitBtn"
                    class="w-full min-h-[44px] px-5 py-2 rounded-xl text-sm pusimpel-btn-primary flex items-center justify-center"
                >
                    <span id="loginBtnLabel">Masuk</span>
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const pinInput = document.getElementById('pin');
        const toggleBtn = document.getElementById('togglePinBtn');
        const eyeShow = document.getElementById('eyeIconShow');
        const eyeHide = document.getElementById('eyeIconHide');
        const form = document.getElementById('loginForm');
        const submitBtn = document.getElementById('loginSubmitBtn');
        const btnLabel = document.getElementById('loginBtnLabel');

        if (toggleBtn && pinInput) {
            toggleBtn.addEventListener('click', function () {
                const isPassword = pinInput.type === 'password';
                pinInput.type = isPassword ? 'text' : 'password';
                toggleBtn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                toggleBtn.setAttribute('aria-label', isPassword ? 'Sembunyikan PIN atau kata sandi' : 'Tampilkan PIN atau kata sandi');
                if (eyeShow && eyeHide) {
                    eyeShow.classList.toggle('hidden', isPassword);
                    eyeHide.classList.toggle('hidden', !isPassword);
                }
            });
        }

        if (form && submitBtn && btnLabel) {
            form.addEventListener('submit', function () {
                submitBtn.disabled = true;
                btnLabel.textContent = 'Memproses...';
            });

            window.addEventListener('pageshow', function () {
                submitBtn.disabled = false;
                btnLabel.textContent = 'Masuk';
            });
        }
    })();
</script>
@endpush
