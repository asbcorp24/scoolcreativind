<?php
namespace App\Http\Controllers;

use App\Models\ParentReportLink;
use App\Models\StudentProfile;
use App\Models\StudyGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminParentReportController extends Controller
{
    private function allowedGroupIds()
    {
        if(auth()->user()->is_admin){
            return StudyGroup::pluck('id');
        }
        return auth()->user()->teacherGroups()->pluck('study_groups.id');
    }

    public function index(Request $request)
    {
        abort_unless(
            auth()->user()->is_admin ||
            auth()->user()->canAdminSection('students') ||
            auth()->user()->teacherGroups()->exists(),
            403
        );

        $groupIds=$this->allowedGroupIds();

        return view('admin.academic.parent-reports',[
            'students'=>StudentProfile::with(['user','studio'])
                ->whereHas('user',fn($q)=>$q->whereHas('studentGroups',fn($g)=>$g->whereIn('study_groups.id',$groupIds)))
                ->get()
                ->sortBy(fn($profile)=>mb_strtolower($profile->user->name ?? ''))
                ->values(),
            'groups'=>StudyGroup::withCount('students')
                ->whereIn('id',$groupIds)
                ->orderBy('study_year')
                ->orderBy('name')
                ->get(),
            'links'=>ParentReportLink::with(['student.user','group'])
                ->where(function($q) use($groupIds){
                    $q->whereIn('study_group_id',$groupIds)
                      ->orWhereHas('student.user.studentGroups',fn($g)=>$g->whereIn('study_groups.id',$groupIds));
                })
                ->latest()
                ->paginate(30),
            'selectedProfileId'=>$request->integer('student_profile_id'),
            'selectedGroupId'=>$request->integer('study_group_id'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(
            auth()->user()->is_admin ||
            auth()->user()->canAdminSection('students') ||
            auth()->user()->teacherGroups()->exists(),
            403
        );

        $data=$request->validate([
            'target_type'=>'required|in:student,group',
            'student_profile_id'=>'nullable|required_if:target_type,student|exists:student_profiles,id',
            'study_group_id'=>'nullable|required_if:target_type,group|exists:study_groups,id',
            'report_until'=>'required|date',
        ]);

        if($data['target_type']==='student'){
            $profile=StudentProfile::with('user.studentGroups')->findOrFail($data['student_profile_id']);
            $allowed=$profile->user?->studentGroups
                ?->pluck('id')
                ->intersect($this->allowedGroupIds())
                ->isNotEmpty();
            abort_unless($allowed,403);
        }else{
            abort_unless($this->allowedGroupIds()->contains((int)$data['study_group_id']),403);

            $group=StudyGroup::with('students')->findOrFail($data['study_group_id']);
            foreach($group->students as $student){
                StudentProfile::firstOrCreate(
                    ['user_id'=>$student->id],
                    [
                        'studio_id'=>$group->studio_id,
                        'class_name'=>$group->name,
                        'portfolio_slug'=>Str::slug($student->name).'-'.$student->id,
                        'is_public'=>false,
                    ]
                );
            }
        }

        $link=ParentReportLink::create([
            'target_type'=>$data['target_type'],
            'student_profile_id'=>$data['target_type']==='student' ? $data['student_profile_id'] : null,
            'study_group_id'=>$data['target_type']==='group' ? $data['study_group_id'] : null,
            'report_until'=>$data['report_until'],
            'token'=>Str::random(64),
            'is_active'=>true,
        ]);

        return redirect()
            ->route('admin.parent-reports')
            ->with('success',$data['target_type']==='group'
                ? 'Групповая ссылка для родителей создана.'
                : 'Персональная ссылка для родителей создана.')
            ->with('parent_report_url',route('parent-report.show',$link->token));
    }

    public function revoke(ParentReportLink $link)
    {
        abort_unless(
            auth()->user()->is_admin ||
            auth()->user()->canAdminSection('students') ||
            auth()->user()->teacherGroups()->exists(),
            403
        );

        $this->ensureLinkAllowed($link);
        $link->update(['is_active'=>false]);

        return back()->with('success','Родительская ссылка отозвана.');
    }

    public function regenerate(ParentReportLink $link)
    {
        abort_unless(
            auth()->user()->is_admin ||
            auth()->user()->canAdminSection('students') ||
            auth()->user()->teacherGroups()->exists(),
            403
        );

        $this->ensureLinkAllowed($link);

        $link->update([
            'token'=>Str::random(64),
            'is_active'=>true,
            'last_opened_at'=>null,
        ]);

        return back()
            ->with('success','Ссылка пересоздана.')
            ->with('parent_report_url',route('parent-report.show',$link->token));
    }

    private function ensureLinkAllowed(ParentReportLink $link): void
    {
        if($link->target_type==='group'){
            abort_unless($this->allowedGroupIds()->contains((int)$link->study_group_id),403);
            return;
        }

        $link->loadMissing('student.user.studentGroups');
        $allowed=$link->student?->user?->studentGroups
            ?->pluck('id')
            ->intersect($this->allowedGroupIds())
            ->isNotEmpty();

        abort_unless($allowed,403);
    }
}
