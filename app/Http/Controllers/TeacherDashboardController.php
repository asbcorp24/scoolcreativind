<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\HomeworkSubmission;
use App\Models\JournalLesson;
use App\Models\User;
use App\Models\StudentProfile;
use App\Models\JournalEntry;
use App\Models\ScheduleLesson;
use App\Models\StudyGroup;
use Illuminate\Http\Request;
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
            $directGroups=$user->teacherGroups()->pluck('study_groups.id');
            $substituteGroups=ScheduleLesson::where('substitute_teacher_id',$user->id)
                ->whereNotNull('study_group_id')
                ->pluck('study_group_id');

            $groupIds=$directGroups->merge($substituteGroups)->filter()->unique()->values();

            $scheduleBase=ScheduleLesson::where(function($q) use($user){
                $q->where('teacher_id',$user->id)
                  ->orWhere('substitute_teacher_id',$user->id);
            });
        }

        $today=(clone $scheduleBase)
            ->with(['group','studio','subject','planLesson','journalLesson','teacher','substituteTeacher'])
            ->whereDate('lesson_date',now()->toDateString())
            ->orderBy('starts_at')
            ->get();

        $upcoming=(clone $scheduleBase)
            ->with(['group','subject','planLesson','teacher','substituteTeacher'])
            ->whereDate('lesson_date','>',now()->toDateString())
            ->where('status','<>','cancelled')
            ->orderBy('lesson_date')
            ->orderBy('starts_at')
            ->take(8)
            ->get();

        $needsJournal=(clone $scheduleBase)
            ->with(['group','subject','planLesson','teacher','substituteTeacher'])
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

    public function saveAnnouncement(Request $request)
    {
        $user=auth()->user();
        abort_unless($user->is_admin || $user->isTeacherRole(),403);

        $data=$request->validate([
            'study_group_id'=>'nullable|exists:study_groups,id',
            'audience'=>'required|in:all,students,teachers',
            'title'=>'required|string|max:255',
            'body'=>'required|string|max:10000',
            'published_at'=>'nullable|date',
            'expires_at'=>'nullable|date|after:published_at',
        ]);

        if(!$user->is_admin){
            abort_unless(
                !empty($data['study_group_id']) &&
                $user->teacherGroups()->where('study_groups.id',$data['study_group_id'])->exists(),
                403
            );
        }

        $announcement=Announcement::create([
            'author_id'=>$user->id,
            'study_group_id'=>$data['study_group_id'] ?? null,
            'audience'=>$data['audience'],
            'title'=>$data['title'],
            'body'=>$data['body'],
            'published_at'=>$data['published_at'] ?? now(),
            'expires_at'=>$data['expires_at'] ?? null,
            'is_published'=>true,
        ]);

        ActivityLog::record(
            'announcement.created',
            $announcement,
            'Создано объявление: '.$announcement->title,
            null,
            $announcement->toArray()
        );

        return back()->with('success','Объявление опубликовано.');
    }

    public function deleteAnnouncement(Announcement $announcement)
    {
        $user=auth()->user();
        abort_unless(
            $user->is_admin || (int)$announcement->author_id===$user->id,
            403
        );

        ActivityLog::record(
            'announcement.deleted',
            $announcement,
            'Удалено объявление: '.$announcement->title,
            $announcement->toArray(),
            null
        );

        $announcement->delete();

        return back()->with('success','Объявление удалено.');
    }


    public function groups()
    {
        $user=auth()->user();
        abort_unless($user->is_admin || $user->isTeacherRole(),403);

        $groupIds=$user->is_admin
            ? StudyGroup::pluck('id')
            : $user->teacherGroups()->pluck('study_groups.id');

        $groups=StudyGroup::with(['academicYear','studio','students','subjects'])
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

        $stats=$groups->mapWithKeys(function($group) use($user){
            $subjectIds=$group->subjects->pluck('id');

            $entries=JournalEntry::whereHas('lesson',function($q) use($group,$subjectIds,$user){
                $q->where('study_group_id',$group->id)
                    ->whereIn('subject_id',$subjectIds);

                if(!$user->is_admin){
                    $q->where('teacher_id',$user->id);
                }
            })->get();

            $total=$entries->count();
            $present=$entries->whereIn('attendance',['present','late'])->count();

            return [$group->id=>[
                'students'=>$group->students->count(),
                'lessons'=>JournalLesson::where('study_group_id',$group->id)
                    ->whereIn('subject_id',$subjectIds)
                    ->when(!$user->is_admin,fn($q)=>$q->where('teacher_id',$user->id))
                    ->count(),
                'attendance'=>$total ? round($present/$total*100,1) : null,
            ]];
        });

        return view('admin.teacher-groups',compact('groups','stats'));
    }

    public function student(User $student)
    {
        $user=auth()->user();
        abort_unless($user->is_admin || $user->isTeacherRole(),403);

        $teacherGroupIds=$user->is_admin
            ? StudyGroup::pluck('id')
            : $user->teacherGroups()->pluck('study_groups.id');

        $studentGroups=$student->studentGroups()
            ->whereIn('study_groups.id',$teacherGroupIds)
            ->get();

        abort_if($studentGroups->isEmpty(),403);

        $groupIds=$studentGroups->pluck('id');

        if($user->is_admin){
            $subjectIds=\App\Models\Subject::whereHas('groups',fn($q)=>$q->whereIn('study_groups.id',$groupIds))
                ->pluck('id');
        }else{
            $subjectIds=DB::table('group_subjects')
                ->whereIn('study_group_id',$groupIds)
                ->where('teacher_id',$user->id)
                ->pluck('subject_id');
        }

        $entries=JournalEntry::with(['lesson.subject','lesson.group'])
            ->where('student_id',$student->id)
            ->whereHas('lesson',function($q) use($groupIds,$subjectIds,$user){
                $q->whereIn('study_group_id',$groupIds)
                    ->whereIn('subject_id',$subjectIds);

                if(!$user->is_admin){
                    $q->where('teacher_id',$user->id);
                }
            })
            ->latest()
            ->get();

        $attendanceTotal=$entries->count();
        $present=$entries->whereIn('attendance',['present','late'])->count();
        $graded=$entries->whereNotNull('grade');

        $summary=[
            'lessons'=>$attendanceTotal,
            'attendance'=>$attendanceTotal ? round($present/$attendanceTotal*100,1) : null,
            'absent'=>$entries->where('attendance','absent')->count(),
            'late'=>$entries->where('attendance','late')->count(),
            'avg_grade'=>$graded->count() ? round((float)$graded->avg('grade'),2) : null,
        ];

        $homework=HomeworkSubmission::with(['assignment.subject','assignment.group'])
            ->where('student_id',$student->id)
            ->whereHas('assignment',function($q) use($groupIds,$subjectIds,$user){
                $q->whereIn('study_group_id',$groupIds)
                    ->whereIn('subject_id',$subjectIds);

                if(!$user->is_admin){
                    $q->where('teacher_id',$user->id);
                }
            })
            ->latest('submitted_at')
            ->get();

        $profile=StudentProfile::where('user_id',$student->id)->first();
        $portfolio=$profile
            ? $profile->portfolio()->with(['lesson.subject','media'])->take(20)->get()
            : collect();

        return view('admin.teacher-student',compact(
            'student','studentGroups','entries','summary','homework','profile','portfolio'
        ));
    }

}
