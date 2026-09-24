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

        if ($request->user()->is_admin) {
            return $next($request);
        }

        $isTeacher=$request->user()->teacherGroups()->exists();
        $teacherAllowed=$request->is(
            'admin/journal',
            'admin/journal/*',
            'admin/homework',
            'admin/homework/*',
            'admin/schedule',
            'admin/schedule/*'
        );

        if ($isTeacher && $teacherAllowed) {
            return $next($request);
        }

        if ($isTeacher) {
            return redirect()->route('admin.journal')
                ->withErrors(['access'=>'Для преподавателя доступны журнал, домашние задания и расписание.']);
        }

        return redirect()->route('admin.login')->withErrors([
            'email'=>'Для входа в этот раздел требуются права администратора или преподавателя.'
        ]);
    }
}
