<?php
namespace App\Http\Controllers;

use App\Models\AdmissionApplication;
use App\Models\JournalEntry;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminRegistrationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->is_admin,403);

        $query=User::query()
            ->where('is_admin',false)
            ->whereHas('admissionApplications',function($q){
                $q->where('message','Регистрация на сайте');
            })
            ->with([
                'admissionApplications'=>fn($q)=>$q->where('message','Регистрация на сайте')->latest(),
                'studyGroups',
            ])
            ->withCount([
                'studyGroups',
                'homeworkSubmissions',
                'competitionRegistrations',
                'quizAttempts',
            ])
            ->latest();

        if($request->filled('q')){
            $q=trim((string)$request->input('q'));
            $query->where(function($builder) use($q){
                $builder->where('name','like','%'.$q.'%')
                    ->orWhere('email','like','%'.$q.'%')
                    ->orWhere('phone','like','%'.$q.'%');
            });
        }

        return view('admin.registrations',[
            'users'=>$query->paginate(50)->withQueryString(),
        ]);
    }

    public function destroy(User $user)
    {
        abort_unless(auth()->user()->is_admin,403);
        abort_if($user->is_admin || $user->isSectionAdmin(),403,'Нельзя удалить администратора.');

        $isSelfRegistration=$user->admissionApplications()
            ->where('message','Регистрация на сайте')
            ->exists();

        abort_unless($isSelfRegistration,404);

        $profile=StudentProfile::where('user_id',$user->id)
            ->withCount(['portfolio','achievements','parentReportLinks'])
            ->first();

        $hasAcademicData=
            $user->studyGroups()->exists()
            || JournalEntry::where('student_id',$user->id)->exists()
            || $user->homeworkSubmissions()->exists()
            || $user->competitionRegistrations()->exists()
            || $user->quizAttempts()->exists()
            || ($profile && (
                $profile->portfolio_count>0
                || $profile->achievements_count>0
                || $profile->parent_report_links_count>0
            ));

        if($hasAcademicData){
            return back()->withErrors([
                'registration'=>'Пользователь «'.$user->name.'» уже связан с учебными данными. Сначала удалите/отвяжите его из групп, журнала, работ или других учебных разделов.'
            ]);
        }

        DB::transaction(function() use($user,$profile){
            AdmissionApplication::where('user_id',$user->id)->delete();

            if($profile){
                if($profile->avatar && !preg_match('~^(https?:)?//~i',$profile->avatar)){
                    Storage::disk('public')->delete($profile->avatar);
                }
                $profile->delete();
            }

            $user->delete();
        });

        return back()->with('success','Регистрация и пользователь удалены.');
    }

    public function destroyBulk(Request $request)
    {
        abort_unless(auth()->user()->is_admin,403);

        $data=$request->validate([
            'user_ids'=>'required|array|min:1|max:200',
            'user_ids.*'=>'integer|exists:users,id',
        ]);

        $deleted=0;
        $skipped=0;

        foreach(User::whereIn('id',$data['user_ids'])->get() as $user){
            if($user->is_admin || $user->isSectionAdmin()){
                $skipped++;
                continue;
            }

            if(!$user->admissionApplications()->where('message','Регистрация на сайте')->exists()){
                $skipped++;
                continue;
            }

            $profile=StudentProfile::where('user_id',$user->id)
                ->withCount(['portfolio','achievements','parentReportLinks'])
                ->first();

            $hasAcademicData=
                $user->studyGroups()->exists()
                || JournalEntry::where('student_id',$user->id)->exists()
                || $user->homeworkSubmissions()->exists()
                || $user->competitionRegistrations()->exists()
                || $user->quizAttempts()->exists()
                || ($profile && (
                    $profile->portfolio_count>0
                    || $profile->achievements_count>0
                    || $profile->parent_report_links_count>0
                ));

            if($hasAcademicData){
                $skipped++;
                continue;
            }

            DB::transaction(function() use($user,$profile){
                AdmissionApplication::where('user_id',$user->id)->delete();

                if($profile){
                    if($profile->avatar && !preg_match('~^(https?:)?//~i',$profile->avatar)){
                        Storage::disk('public')->delete($profile->avatar);
                    }
                    $profile->delete();
                }

                $user->delete();
            });

            $deleted++;
        }

        return back()->with(
            'success',
            'Удалено регистраций: '.$deleted.'.'.($skipped ? ' Пропущено защищённых/используемых: '.$skipped.'.' : '')
        );
    }
}
