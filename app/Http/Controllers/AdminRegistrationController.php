<?php
namespace App\Http\Controllers;

use App\Models\AdmissionApplication;
use App\Models\JournalEntry;
use App\Models\StudentProfile;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
                'activatedBy',
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

        if($request->filled('status') && in_array($request->input('status'),['pending','active','rejected','blocked'],true)){
            $query->where('registration_status',$request->input('status'));
        }

        return view('admin.registrations',[
            'users'=>$query->paginate(50)->withQueryString(),
            'groups'=>StudyGroup::where('is_active',true)->orderBy('study_year')->orderBy('name')->get(),
            'pendingCount'=>User::where('registration_status','pending')
                ->whereHas('admissionApplications',fn($q)=>$q->where('message','Регистрация на сайте'))
                ->count(),
        ]);
    }

    public function activate(Request $request, User $user)
    {
        abort_unless(auth()->user()->is_admin,403);
        $data=$request->validate([
            'group_id'=>'required|exists:study_groups,id',
            'role'=>'required|in:student,teacher',
        ]);

        $group=StudyGroup::where('is_active',true)->findOrFail($data['group_id']);
        $this->activateUser($user,$group,$data['role']);

        return back()->with('success','Пользователь «'.$user->name.'» активирован и добавлен в группу «'.$group->name.'».');
    }

    public function activateBulk(Request $request)
    {
        abort_unless(auth()->user()->is_admin,403);
        $data=$request->validate([
            'user_ids'=>'required|array|min:1|max:200',
            'user_ids.*'=>'integer|exists:users,id',
            'group_id'=>'required|exists:study_groups,id',
            'role'=>'required|in:student,teacher',
        ]);

        $group=StudyGroup::where('is_active',true)->findOrFail($data['group_id']);
        $activated=0;
        $skipped=0;

        foreach(User::whereIn('id',$data['user_ids'])->get() as $user){
            if($user->is_admin || $user->isSectionAdmin() || !$this->isSelfRegistration($user)){
                $skipped++;
                continue;
            }
            $this->activateUser($user,$group,$data['role']);
            $activated++;
        }

        return back()->with(
            'success',
            'Активировано пользователей: '.$activated.'.'.($skipped ? ' Пропущено: '.$skipped.'.' : '')
        );
    }

    public function reject(User $user)
    {
        abort_unless(auth()->user()->is_admin,403);
        abort_if($user->is_admin || $user->isSectionAdmin(),403);
        abort_unless($this->isSelfRegistration($user),404);

        DB::transaction(function() use($user){
            $user->update([
                'registration_status'=>'rejected',
                'activated_at'=>null,
                'activated_by'=>null,
            ]);
            $user->admissionApplications()
                ->where('message','Регистрация на сайте')
                ->update(['status'=>'rejected']);
        });

        return back()->with('success','Регистрация «'.$user->name.'» отклонена.');
    }

    private function activateUser(User $user, StudyGroup $group, string $role): void
    {
        abort_if($user->is_admin || $user->isSectionAdmin(),403);
        abort_unless($this->isSelfRegistration($user),404);

        DB::transaction(function() use($user,$group,$role){
            $group->users()->syncWithoutDetaching([
                $user->id=>['role'=>$role],
            ]);

            if($role==='student'){
                StudentProfile::firstOrCreate(
                    ['user_id'=>$user->id],
                    [
                        'studio_id'=>$group->studio_id,
                        'class_name'=>$group->name,
                        'portfolio_slug'=>Str::slug($user->name).'-'.$user->id,
                        'is_public'=>false,
                    ]
                );
            }

            $user->update([
                'registration_status'=>'active',
                'activated_at'=>now(),
                'activated_by'=>auth()->id(),
            ]);

            $user->admissionApplications()
                ->where('message','Регистрация на сайте')
                ->update(['status'=>'accepted']);
        });
    }

    private function isSelfRegistration(User $user): bool
    {
        return $user->admissionApplications()
            ->where('message','Регистрация на сайте')
            ->exists();
    }

    public function destroy(User $user)
    {
        abort_unless(auth()->user()->is_admin,403);
        abort_if($user->is_admin || $user->isSectionAdmin(),403,'Нельзя удалить администратора.');
        abort_unless($this->isSelfRegistration($user),404);

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
            if($user->is_admin || $user->isSectionAdmin() || !$this->isSelfRegistration($user)){
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
