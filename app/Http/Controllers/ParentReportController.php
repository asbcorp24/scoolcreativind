<?php
namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\ParentReportLink;
use Illuminate\Support\Collection;

class ParentReportController extends Controller
{
    public function show(string $token)
    {
        $link=ParentReportLink::with([
            'student.user',
            'student.studio',
            'student.user.studyGroups',
        ])->where('token',$token)->firstOrFail();

        abort_unless($link->is_active,404);

        $profile=$link->student;
        abort_unless($profile && $profile->user,404);

        $entries=JournalEntry::with([
                'lesson.subject',
                'lesson.group',
                'lesson.teacher',
            ])
            ->where('student_id',$profile->user_id)
            ->whereHas('lesson',fn($q)=>$q->whereDate('lesson_date','<=',$link->report_until))
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

        $link->updateQuietly(['last_opened_at'=>now()]);

        return view('parent-report.show',compact(
            'link','profile','entries','attendance','performance','bySubject'
        ));
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
