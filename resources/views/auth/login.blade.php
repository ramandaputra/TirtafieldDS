@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
<div class="min-h-screen flex flex-col justify-center py-10 px-4 sm:px-6">
    <div class="w-full max-w-[400px] mx-auto">
        <div class="pusimpel-card p-6 sm:p-8">
            <div class="text-center mb-6">
                <div class="profile-container mx-auto mb-4" aria-hidden="true">
                    <div class="w-full h-full icon-tile cat-pelanggan">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
                            <path d="M9 14l2 2 4-4" />
                        </svg>
                    </div>
                </div>

                <h1 class="text-2xl font-extrabold user-name-gradient">
                    Masuk ke Tirtafield DS
                </h1>
            </div>

            @if ($errors->any())
                <div role="alert" class="mb-5 rounded-2xl border p-3 text-xs font-semibold badge-rose">
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
                        autocomplete="username"
                        placeholder="NIK atau email"
                        @if ($errors->has('nik')) aria-invalid="true" @endif
                        class="w-full min-h-[44px] px-4 py-2.5 rounded-full text-sm pusimpel-input {{ $errors->has('nik') ? 'border-rose-400' : '' }}"
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
                        <p class="mt-1 text-xs font-semibold text-[#fda4af]">{{ $message }}</p>
                    @enderror
                </div>

                <label for="remember" class="inline-flex items-center gap-2 text-xs text-[#cbd5e1] min-h-[40px]">
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
                    class="w-full min-h-[46px] px-5 py-2.5 rounded-full text-sm pusimpel-btn-primary flex items-center justify-center"
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
                btnLabel.textContent = 'Memproses...';
            });
        }
    })();
</script>
@endpush
