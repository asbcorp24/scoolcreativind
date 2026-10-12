<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AdmissionApplication;
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

        $user=Auth::user();

        if(!$user->isActivated()){
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message=match($user->registration_status){
                'rejected'=>'Регистрация отклонена администратором. Обратитесь в школу для уточнения.',
                'blocked'=>'Учётная запись заблокирована. Обратитесь к администратору.',
                default=>'Учётная запись ещё не активирована. Дождитесь подтверждения администратора и назначения в группу.',
            };

            return back()->withErrors(['email'=>$message])->onlyInput('email');
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
        $data['registration_status']='pending';
        $data['activated_at']=null;
        $data['activated_by']=null;

        $user=User::create($data);

        AdmissionApplication::firstOrCreate(
            ['user_id'=>$user->id],
            [
                'name'=>$user->name,
                'phone'=>$user->phone,
                'email'=>$user->email,
                'status'=>'new',
                'message'=>'Регистрация на сайте',
            ]
        );

        return redirect()->route('login')->with(
            'success',
            'Регистрация завершена. Аккаунт ожидает активации администратором и назначения в учебную группу.'
        );
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
