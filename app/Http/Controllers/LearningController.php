<?php
namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\Competition;
use App\Models\CompetitionRegistration;
use App\Models\PortfolioItem;
use App\Models\ScheduleLesson;
use App\Models\StudentProfile;
use App\Models\Studio;
use App\Models\Subject;
use App\Models\StudyGroup;
use Illuminate\Http\Request;

class LearningController extends Controller
{
    public function schedule(Request $request)
    {
        $viewMode=in_array($request->input('view'),['month','week','day','list'],true)
            ? $request->input('view')
            : 'month';

        $month=$request->input('month',now()->format('Y-m'));
        $monthStart=\Carbon\Carbon::createFromFormat('Y-m',$month)->startOfMonth();

        $anchorDate=$request->filled('date')
            ? \Carbon\Carbon::parse($request->input('date'))
            : ($month===now()->format('Y-m') ? now()->copy() : $monthStart->copy());

        if($viewMode==='day'){
            $rangeStart=$anchorDate->copy()->startOfDay();
            $rangeEnd=$anchorDate->copy()->endOfDay();
        }elseif($viewMode==='week'){
            $rangeStart=$anchorDate->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
            $rangeEnd=$anchorDate->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
        }else{
            $rangeStart=$monthStart->copy()->startOfMonth();
            $rangeEnd=$monthStart->copy()->endOfMonth();
        }

        $query=ScheduleLesson::with(['studio','group','teacher','subject','planLesson'])
            ->where('is_published',true);

        $activeStudio=$request->input('studio','');
        $activeTeacher=trim((string)$request->input('teacher',''));
        $activeGroup=$request->input('group','');
        $activeSubject=$request->input('subject','');
        $activeRoom=trim((string)$request->input('room',''));

        if($activeStudio!=='') $query->where('studio_id',$activeStudio);
        if($activeTeacher!=='') $query->where('teacher_name',$activeTeacher);
        if($activeGroup!=='') $query->where('study_group_id',$activeGroup);
        if($activeSubject!=='') $query->where('subject_id',$activeSubject);
        if($activeRoom!=='') $query->where('room',$activeRoom);

        $lessons=$query
            ->whereBetween('lesson_date',[$rangeStart->toDateString(),$rangeEnd->toDateString()])
            ->orderBy('lesson_date')
            ->orderBy('starts_at')
            ->get();

        $published=ScheduleLesson::where('is_published',true);

        $studioIds=(clone $published)->whereNotNull('studio_id')->distinct()->pluck('studio_id');
        $studios=Studio::whereIn('id',$studioIds)->orderBy('sort_order')->get();

        $groupIds=(clone $published)->whereNotNull('study_group_id')->distinct()->pluck('study_group_id');
        $groups=StudyGroup::whereIn('id',$groupIds)->orderBy('name')->get();

        $subjectIds=(clone $published)->whereNotNull('subject_id')->distinct()->pluck('subject_id');
        $subjects=Subject::whereIn('id',$subjectIds)->orderBy('title')->get();

        $teachers=(clone $published)
            ->whereNotNull('teacher_name')
            ->where('teacher_name','<>','')
            ->distinct()
            ->orderBy('teacher_name')
            ->pluck('teacher_name');

        $rooms=(clone $published)
            ->whereNotNull('room')
            ->where('room','<>','')
            ->distinct()
            ->orderBy('room')
            ->pluck('room');

        return view('schedule',[
            'lessons'=>$lessons,
            'start'=>$monthStart,
            'month'=>$month,
            'studios'=>$studios,
            'groups'=>$groups,
            'subjects'=>$subjects,
            'teachers'=>$teachers,
            'rooms'=>$rooms,
            'activeStudio'=>$activeStudio,
            'activeTeacher'=>$activeTeacher,
            'activeGroup'=>$activeGroup,
            'activeSubject'=>$activeSubject,
            'activeRoom'=>$activeRoom,
            'viewMode'=>$viewMode,
            'anchorDate'=>$anchorDate,
            'rangeStart'=>$rangeStart,
            'rangeEnd'=>$rangeEnd,
        ]);
    }

    public function scheduleIcs(Request $request)
    {
        $query=ScheduleLesson::with(['group','subject'])
            ->where('is_published',true)
            ->where('status','<>','cancelled');

        foreach(['studio'=>'studio_id','group'=>'study_group_id','subject'=>'subject_id'] as $param=>$column){
            if($request->filled($param)) $query->where($column,$request->input($param));
        }
        if($request->filled('teacher')) $query->where('teacher_name',$request->input('teacher'));
        if($request->filled('room')) $query->where('room',$request->input('room'));

        $from=$request->filled('from') ? \Carbon\Carbon::parse($request->input('from')) : now()->startOfMonth();
        $to=$request->filled('to') ? \Carbon\Carbon::parse($request->input('to')) : $from->copy()->addMonths(6);

        $lessons=$query->whereBetween('lesson_date',[$from->toDateString(),$to->toDateString()])
            ->orderBy('lesson_date')->orderBy('starts_at')->get();

        $escape=function($value){
            return str_replace(["\\",";",",","\r","\n"],["\\\\","\\;","\\,","","\\n"],(string)$value);
        };

        $lines=[
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//SHKIVR//Schedule//RU',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach($lessons as $lesson){
            $date=$lesson->lesson_date->format('Ymd');
            $start=str_replace(':','',substr($lesson->starts_at,0,5)).'00';
            $end=str_replace(':','',substr($lesson->ends_at,0,5)).'00';

            $lines[]='BEGIN:VEVENT';
            $lines[]='UID:schedule-'.$lesson->id.'@'.parse_url(config('app.url'),PHP_URL_HOST);
            $lines[]='DTSTAMP:'.now()->utc()->format('Ymd\\THis\\Z');
            $lines[]='DTSTART:'.$date.'T'.$start;
            $lines[]='DTEND:'.$date.'T'.$end;
            $lines[]='SUMMARY:'.$escape($lesson->title);
            $lines[]='DESCRIPTION:'.$escape(collect([
                $lesson->group?->name,
                $lesson->subject?->title,
                $lesson->teacher_name ? 'Преподаватель: '.$lesson->teacher_name : null,
                $lesson->status_note,
            ])->filter()->implode(' · '));
            if($lesson->room) $lines[]='LOCATION:'.$escape($lesson->room);
            $lines[]='END:VEVENT';
        }

        $lines[]='END:VCALENDAR';

        return response(implode("\r\n",$lines)."\r\n",200,[
            'Content-Type'=>'text/calendar; charset=utf-8',
            'Content-Disposition'=>'attachment; filename="shki-schedule.ics"',
        ]);
    }

    public function projects(Request $request)
    {
        $query=PortfolioItem::with(['student.user','studio','media'=>fn($q)=>$q->where('is_visible',true)->orderBy('sort_order')])
            ->where('is_public',true);

        if($request->filled('type')){
            $query->where('type',$request->string('type'));
        }

        return view('projects.index',[
            'projects'=>$query->orderByDesc('is_featured')->latest('completed_at')->latest()->paginate(12)->withQueryString(),
            'types'=>PortfolioItem::where('is_public',true)->whereNotNull('type')->distinct()->orderBy('type')->pluck('type'),
            'activeType'=>$request->input('type'),
        ]);
    }

    public function project(PortfolioItem $project)
    {
        abort_unless($project->is_public,404);
        $project->load([
            'student.user',
            'studio',
            'lesson.subject',
            'media'=>fn($q)=>$q->where('is_visible',true)->orderBy('sort_order'),
        ]);
        return view('projects.show',compact('project'));
    }

    public function portfolio(StudentProfile $profile)
    {
        abort_unless($profile->is_public || (auth()->check() && auth()->id()===$profile->user_id),404);
        $profile->load(['user','studio','portfolio.studio','portfolio.media'=>fn($q)=>$q->where('is_visible',true)->orderBy('sort_order'),'achievements.competition']);
        return view('portfolio.show',compact('profile'));
    }

    public function myPortfolio()
    {
        $profile=StudentProfile::firstOrCreate(
            ['user_id'=>auth()->id()],
            ['portfolio_slug'=>'student-'.auth()->id(), 'is_public'=>false]
        );
        $profile->load(['user','studio','portfolio.studio','achievements.competition']);
        return view('portfolio.mine',compact('profile'));
    }

    public function competitions()
    {
        return view('competitions.index',[
            'competitions'=>Competition::where('is_published',true)->withCount('registrations')->latest('starts_on')->get(),
            'registrations'=>auth()->check()
                ? CompetitionRegistration::where('user_id',auth()->id())->get()->keyBy('competition_id')
                : collect(),
            'achievements'=>Achievement::where('is_public',true)->with(['student.user','competition'])->latest('awarded_at')->take(24)->get(),
        ]);
    }
}
