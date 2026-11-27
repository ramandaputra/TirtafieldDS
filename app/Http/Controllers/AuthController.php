<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'nik' => ['required', 'string', 'max:150'],
            'pin' => ['required', 'string', 'max:100'],
        ], [
            'nik.required' => 'NIK / NIP atau email wajib diisi.',
            'pin.required' => 'PIN atau kata sandi wajib diisi.',
        ]);

        $identifier = trim($credentials['nik']);
        $secret = trim($credentials['pin']);

        $identifierLower = mb_strtolower($identifier);
        $user = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($identifierLower): void {
                $query->whereRaw('LOWER(nik) = ?', [$identifierLower])
                    ->orWhereRaw('LOWER(email) = ?', [$identifierLower]);
            })
            ->first();

        if (! $user) {
            return back()
                ->withErrors(['nik' => 'Akun dengan NIK/NIP atau email tersebut tidak ditemukan atau tidak aktif.'])
                ->withInput($request->only('nik', 'remember'));
        }

        if (! $user->verifySecret($secret)) {
            return back()
                ->withErrors(['pin' => 'PIN atau kata sandi yang Anda masukkan tidak sesuai.'])
                ->withInput($request->only('nik', 'remember'));
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))
            ->with('status', 'Selamat datang, '.$user->name.'.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'Anda telah keluar dari portal rekap absensi.');
    }
}
