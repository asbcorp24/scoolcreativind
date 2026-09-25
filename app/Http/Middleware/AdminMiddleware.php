<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user=$request->user();

        if (!$user) {
            return redirect()->route('admin.login');
        }

        if ($user->is_admin) {
            return $next($request);
        }

        if ($user->isSectionAdmin()) {
            $section=$this->resolveSection($request);

            if ($section && $user->canAdminSection($section)) {
                return $next($request);
            }

            if ($request->is('admin')) {
                return redirect()->route($user->adminLandingRoute());
            }

            return redirect()->route($user->adminLandingRoute())
                ->withErrors(['access'=>'У вас нет доступа к этому разделу админки.']);
        }

        $isTeacher=$user->teacherGroups()->exists();
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

    private function resolveSection(Request $request): ?string
    {
        $path=$request->path();

        $map=[
            'admin/studios'=>'studios',
            'admin/media'=>'studios',
            'admin/news'=>'news',
            'admin/projects'=>'projects',
            'admin/events'=>'events',
            'admin/team'=>'team',
            'admin/equipment'=>'equipment',
            'admin/students'=>'students',
            'admin/competitions'=>'competitions',
            'admin/achievements'=>'competitions',
            'admin/quizzes'=>'quizzes',
            'admin/groups'=>'groups',
            'admin/subjects'=>'subjects',
            'admin/schedule'=>'schedule',
            'admin/journal'=>'journal',
            'admin/homework'=>'homework',
            'admin/settings'=>'settings',
            'admin/applications'=>'applications',
        ];

        foreach($map as $prefix=>$section){
            if($path===$prefix || str_starts_with($path,$prefix.'/')){
                return $section;
            }
        }

        return $path==='admin' ? null : null;
    }
}
