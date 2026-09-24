<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function loginForm()
    {
        if (Auth::check() && Auth::user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials=$request->validate([
            'email'=>'required|email',
            'password'=>'required|string'
        ]);

        if (!Auth::attempt($credentials,$request->boolean('remember'))) {
            return back()->withErrors(['email'=>'Неверный email или пароль администратора.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        if (!Auth::user()->is_admin) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email'=>'У этой учётной записи нет прав администратора.'])->onlyInput('email');
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
