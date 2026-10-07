<?php
namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\ParentReportLink;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

class ParentReportController extends Controller
{
    public function show(string $token)
    {
        $link=ParentReportLink::with([
            'student.user',
            'student.studio',
            'student.user.studentGroups',
            'group.students',
            'group.studio',
        ])->where('token',$token)->firstOrFail();

        abort_unless($link->is_active,404);

        if($link->target_type==='group'){
            return $this->showGroup($link);
        }

        $profile=$link->student;
        abort_unless($profile && $profile->user,404);

        $data=$this->buildStudentReport($profile,$link);
        $link->updateQuietly(['last_opened_at'=>now()]);

        return view('parent-report.show',$data);
    }

    private function showGroup(ParentReportLink $link)
    {
        $group=$link->group;
        abort_unless($group,404);

        $profiles=StudentProfile::with(['user','studio'])
            ->whereIn('user_id',$group->students->pluck('id'))
            ->get()
            ->sortBy(fn($profile)=>mb_strtolower($profile->user->name ?? ''))
            ->values();

        $selectedId=(int)request('student_profile_id');
        if(!$selectedId){
            $link->updateQuietly(['last_opened_at'=>now()]);
            return view('parent-report.group',compact('link','group','profiles'));
        }

        $profile=$profiles->firstWhere('id',$selectedId);
        abort_unless($profile && $profile->user,404);

        $data=$this->buildStudentReport($profile,$link);
        $data['group']=$group;
        $data['profiles']=$profiles;
        $data['isGroupReport']=true;

        $link->updateQuietly(['last_opened_at'=>now()]);

        return view('parent-report.show',$data);
    }

    private function buildStudentReport(StudentProfile $profile, ParentReportLink $link): array
    {
        $entries=JournalEntry::with([
                'lesson.subject',
                'lesson.group',
                'lesson.teacher',
            ])
            ->where('student_id',$profile->user_id)
            ->whereHas('lesson',function($q) use($link){
                $q->whereDate('lesson_date','<=',$link->report_until);

                if($link->target_type==='group' && $link->study_group_id){
                    $q->where('study_group_id',$link->study_group_id);
                }
            })
            ->get()
            ->sortByDesc(fn($entry)=>optional($entry->lesson->lesson_date)->format('Y-m-d'))
            ->values();

        $attendance=$this->attendanceSummary($entries);
        $performance=$this->performanceSummary($entries);

        $bySubject=$entries
            ->groupBy(fn($entry)=>$entry->lesson?->subject_id ?: 0)
            ->map(function(Collection $items){
                $grades=$items->pluck('grade')->filter(fn($v)=>$v!==null)->map(fn($v)=>(float)$v);
                $present=$items->where('attendance','present')->count();
                $late=$items->where('attendance','late')->count();
                $total=$items->count();

                return [
                    'subject'=>$items->first()?->lesson?->subject?->title ?: 'Без предмета',
                    'total'=>$total,
                    'present'=>$present,
                    'late'=>$late,
                    'absent'=>$items->where('attendance','absent')->count(),
                    'excused'=>$items->where('attendance','excused')->count(),
                    'attendance_rate'=>$total ? round((($present+$late)/$total)*100,1) : null,
                    'grades_count'=>$grades->count(),
                    'average_grade'=>$grades->count() ? round($grades->avg(),1) : null,
                ];
            })
            ->sortBy('subject')
            ->values();

        return compact(
            'link','profile','entries','attendance','performance','bySubject'
        );
    }

    private function attendanceSummary(Collection $entries): array
    {
        $total=$entries->count();
        $present=$entries->where('attendance','present')->count();
        $late=$entries->where('attendance','late')->count();

        return [
            'total'=>$total,
            'present'=>$present,
            'late'=>$late,
            'absent'=>$entries->where('attendance','absent')->count(),
            'excused'=>$entries->where('attendance','excused')->count(),
            'rate'=>$total ? round((($present+$late)/$total)*100,1) : null,
        ];
    }

    private function performanceSummary(Collection $entries): array
    {
        $grades=$entries
            ->pluck('grade')
            ->filter(fn($v)=>$v!==null)
            ->map(fn($v)=>(float)$v);

        return [
            'count'=>$grades->count(),
            'average'=>$grades->count() ? round($grades->avg(),1) : null,
            'min'=>$grades->count() ? $grades->min() : null,
            'max'=>$grades->count() ? $grades->max() : null,
        ];
    }
}
