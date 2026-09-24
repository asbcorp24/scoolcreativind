<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function loginForm(){ return view('auth.login'); }
    public function registerForm(){ return view('auth.register'); }

    public function login(Request $request)
    {
        $credentials=$request->validate(['email'=>'required|email','password'=>'required|string']);
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email'=>'Неверный email или пароль.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        return redirect()->intended('/cabinet');
    }

    public function register(Request $request)
    {
        $data=$request->validate([
            'name'=>'required|string|max:120',
            'email'=>'required|email|max:160|unique:users,email',
            'phone'=>'nullable|string|max:40',
            'password'=>'required|string|min:8|confirmed'
        ]);
        $data['password']=Hash::make($data['password']);
        $user=User::create($data);
        Auth::login($user);
        return redirect('/cabinet');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
