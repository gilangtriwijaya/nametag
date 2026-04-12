<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonateController extends Controller
{

    public function __construct()
    {
        // harus login untuk semua
        $this->middleware('auth');

        // start hanya untuk superadmin
        $this->middleware('role:superadmin')->only('start');
        // stop tidak pakai 'role', supaya user non-superadmin bisa keluar
    }
    
    public function start(Request $request, User $user)
    {
        $me = $request->user();

        if ($user->id === $me->id) {
            return back()->with('ok', 'Anda sudah memakai akun tersebut.');
        }
        if ($user->hasRole('superadmin')) {
            return back()->with('ok', 'Tidak diperkenankan menyamar sebagai superadmin.');
        }

        if (!session()->has('impersonate.by')) {
            session([
                'impersonate.by' => $me->id,
                'impersonate.as' => $user->id,
            ]);
        } else {
            session(['impersonate.as' => $user->id]);
        }

        Auth::login($user);
        $request->session()->regenerate(); // <— important

        return redirect()->route('dashboard')
            ->with('ok', 'Anda sekarang masuk sebagai: '.$user->name);
    }

    public function stop(Request $request)
    {
        $originalId = session('impersonate.by');

        if (!$originalId) {
            return redirect()->route('dashboard')->with('ok', 'Sesi impersonasi tidak ditemukan.');
        }

        session()->forget(['impersonate.by', 'impersonate.as']);

        Auth::loginUsingId($originalId);
        $request->session()->regenerate(); // <— important

        return redirect()->route('dashboard')->with('ok', 'Kembali ke akun semula.');
    }
}
