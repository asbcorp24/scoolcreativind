<?php
namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\HomeworkSubmission;
use App\Models\JournalLesson;
use App\Models\ScheduleLesson;
use App\Models\StudyGroup;
use Illuminate\Support\Facades\DB;

class TeacherDashboardController extends Controller
{
    public function index()
    {
        $user=auth()->user();
        abort_unless($user->is_admin || $user->isTeacherRole(),403);

        if($user->is_admin){
            $groupIds=StudyGroup::pluck('id');
            $scheduleBase=ScheduleLesson::query();
        }else{
            $groupIds=$user->teacherGroups()->pluck('study_groups.id');
            $scheduleBase=ScheduleLesson::where('teacher_id',$user->id);
        }

        $today=(clone $scheduleBase)
            ->with(['group','studio','subject','planLesson','journalLesson'])
            ->whereDate('lesson_date',now()->toDateString())
            ->orderBy('starts_at')
            ->get();

        $upcoming=(clone $scheduleBase)
            ->with(['group','subject','planLesson'])
            ->whereDate('lesson_date','>',now()->toDateString())
            ->where('status','<>','cancelled')
            ->orderBy('lesson_date')
            ->orderBy('starts_at')
            ->take(8)
            ->get();

        $needsJournal=(clone $scheduleBase)
            ->with(['group','subject','planLesson'])
            ->whereDate('lesson_date','<',now()->toDateString())
            ->where('status','planned')
            ->whereNull('journal_lesson_id')
            ->orderByDesc('lesson_date')
            ->take(10)
            ->get();

        $pendingHomework=HomeworkSubmission::with(['student','assignment.group','assignment.subject'])
            ->where('status','submitted')
            ->whereHas('assignment',function($q) use($user,$groupIds){
                $q->whereIn('study_group_id',$groupIds);
                if(!$user->is_admin){
                    $q->where('teacher_id',$user->id);
                }
            })
            ->latest('submitted_at')
            ->take(12)
            ->get();

        $groups=StudyGroup::with(['students','subjects.lessons'])
            ->whereIn('id',$groupIds)
            ->orderBy('name')
            ->get();

        if(!$user->is_admin){
            $teacherId=$user->id;
            $groups->each(function($group) use($teacherId){
                $group->setRelation(
                    'subjects',
                    $group->subjects
                        ->filter(fn($subject)=>(int)($subject->pivot->teacher_id ?? 0)===$teacherId)
                        ->values()
                );
            });
        }

        $progress=$groups->flatMap(function($group) use($user){
            return $group->subjects->map(function($subject) use($group,$user){
                $total=$subject->lessons->count();
                $conducted=JournalLesson::where('study_group_id',$group->id)
                    ->where('subject_id',$subject->id)
                    ->when(!$user->is_admin,fn($q)=>$q->where('teacher_id',$user->id))
                    ->count();

                return [
                    'group'=>$group,
                    'subject'=>$subject,
                    'total'=>$total,
                    'conducted'=>$conducted,
                    'percent'=>$total ? min(100,(int)round($conducted/$total*100)) : 0,
                ];
            });
        })->values();

        $announcements=Announcement::with(['author','group'])
            ->where('is_published',true)
            ->where(function($q) use($groupIds){
                $q->whereNull('study_group_id')
                  ->orWhereIn('study_group_id',$groupIds);
            })
            ->where(function($q){
                $q->whereNull('published_at')->orWhere('published_at','<=',now());
            })
            ->where(function($q){
                $q->whereNull('expires_at')->orWhere('expires_at','>=',now());
            })
            ->latest('published_at')
            ->take(6)
            ->get();

        return view('admin.teacher-dashboard',compact(
            'today','upcoming','needsJournal','pendingHomework','groups','progress','announcements'
        ));
    }
}
