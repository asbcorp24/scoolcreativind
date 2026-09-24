<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return redirect()->route('admin.login');
        }

        if (!$request->user()->is_admin) {
            return redirect()->route('admin.login')->withErrors([
                'email'=>'Для входа в админ-панель требуется учётная запись администратора.'
            ]);
        }

        return $next($request);
    }
}
