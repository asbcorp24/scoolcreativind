<?php
namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\Competition;
use App\Models\PortfolioItem;
use App\Models\ScheduleLesson;
use App\Models\StudentProfile;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AdminLearningController extends Controller
{
    public function schedule()
    {
        $groupsQuery=\App\Models\StudyGroup::where('is_active',true);
        $lessonsQuery=ScheduleLesson::with(['studio','group']);

        if (!auth()->user()->is_admin) {
            $groupIds=auth()->user()->teacherGroups()->pluck('study_groups.id');
            $groupsQuery->whereIn('id',$groupIds);
            $lessonsQuery->whereIn('study_group_id',$groupIds);
        }

        return view('admin.schedule', [
            'lessons'=>$lessonsQuery->orderByDesc('lesson_date')->orderBy('starts_at')->get(),
            'studios'=>Studio::orderBy('sort_order')->get(),
            'groups'=>$groupsQuery->orderBy('study_year')->orderBy('name')->get(),
        ]);
    }

    public function saveLesson(Request $request, ?ScheduleLesson $lesson=null)
    {
        $data=$request->validate([
            'studio_id'=>'nullable|exists:studios,id',
            'study_group_id'=>'nullable|exists:study_groups,id',
            'title'=>'required|string|max:220',
            'teacher_name'=>'nullable|string|max:180',
            'lesson_date'=>'required|date',
            'starts_at'=>'required',
            'ends_at'=>'required',
            'room'=>'nullable|string|max:120',
            'description'=>'nullable|string|max:5000',
            'color'=>'nullable|string|max:32',
            'is_published'=>'nullable|boolean',
        ]);

        if (!auth()->user()->is_admin) {
            if($lesson && $lesson->study_group_id){
                abort_unless(
                    auth()->user()->teacherGroups()->where('study_groups.id',$lesson->study_group_id)->exists(),
                    403
                );
            }

            abort_unless(
                !empty($data['study_group_id']) &&
                auth()->user()->teacherGroups()->where('study_groups.id',$data['study_group_id'])->exists(),
                403
            );
        }

        $lesson ??= new ScheduleLesson();
        $data['is_published']=$request->boolean('is_published');
        $lesson->fill($data)->save();
        return back()->with('success','Занятие сохранено.');
    }

    public function generateSchedule(Request $request)
    {
        $data=$request->validate([
            'studio_id'=>'nullable|exists:studios,id',
            'study_group_id'=>'nullable|exists:study_groups,id',
            'title'=>'required|string|max:220',
            'teacher_name'=>'nullable|string|max:180',
            'weekday'=>'required|integer|min:1|max:7',
            'date_from'=>'required|date',
            'date_to'=>'required|date|after_or_equal:date_from',
            'starts_at'=>'required|date_format:H:i',
            'ends_at'=>'required|date_format:H:i|after:starts_at',
            'room'=>'nullable|string|max:120',
            'description'=>'nullable|string|max:5000',
            'color'=>'nullable|string|max:32',
            'is_published'=>'nullable|boolean',
        ]);

        if (!auth()->user()->is_admin && !empty($data['study_group_id'])) {
            abort_unless(
                auth()->user()->teacherGroups()->where('study_groups.id',$data['study_group_id'])->exists(),
                403
            );
        }

        $from=Carbon::parse($data['date_from'])->startOfDay();
        $to=Carbon::parse($data['date_to'])->startOfDay();

        if($from->diffInDays($to)>730){
            return back()->withErrors([
                'date_to'=>'Диапазон мастера расписания не может превышать 2 года.'
            ])->withInput();
        }

        $created=0;
        $skipped=0;
        $date=$from->copy();

        while($date->lte($to)){
            if($date->isoWeekday()===(int)$data['weekday']){
                $lessonDate=$date->toDateString();

                $exists=ScheduleLesson::query()
                    ->whereDate('lesson_date',$lessonDate)
                    ->where('starts_at',$data['starts_at'])
                    ->where('ends_at',$data['ends_at'])
                    ->where('title',$data['title'])
                    ->when(
                        !empty($data['study_group_id']),
                        fn($q)=>$q->where('study_group_id',$data['study_group_id']),
                        fn($q)=>$q->whereNull('study_group_id')
                    )
                    ->exists();

                if($exists){
                    $skipped++;
                }else{
                    ScheduleLesson::create([
                        'studio_id'=>$data['studio_id'] ?? null,
                        'study_group_id'=>$data['study_group_id'] ?? null,
                        'title'=>$data['title'],
                        'teacher_name'=>$data['teacher_name'] ?? null,
                        'lesson_date'=>$lessonDate,
                        'starts_at'=>$data['starts_at'],
                        'ends_at'=>$data['ends_at'],
                        'room'=>$data['room'] ?? null,
                        'description'=>$data['description'] ?? null,
                        'color'=>$data['color'] ?? '#8a5cff',
                        'is_published'=>$request->boolean('is_published'),
                    ]);
                    $created++;
                }
            }
            $date->addDay();
        }

        if($created===0 && $skipped===0){
            return back()->withErrors([
                'date_from'=>'В выбранном диапазоне нет выбранного дня недели.'
            ])->withInput();
        }

        $message='Мастер расписания: создано занятий — '.$created.'.';
        if($skipped)$message.=' Уже существовало и пропущено — '.$skipped.'.';

        return back()->with('success',$message);
    }

    public function deleteLesson(ScheduleLesson $lesson)
    {
        if(!auth()->user()->is_admin){
            abort_unless(
                $lesson->study_group_id &&
                auth()->user()->teacherGroups()->where('study_groups.id',$lesson->study_group_id)->exists(),
                403
            );
        }

        $lesson->delete();
        return back()->with('success','Занятие удалено.');
    }

    public function students()
    {
        return view('admin.students', [
            'students'=>User::whereHas('studyGroups',fn($q)=>$q->where('role','student'))
                ->with(['studyGroups'=>fn($q)=>$q->wherePivot('role','student')])
                ->orderBy('name')->get(),
            'profiles'=>StudentProfile::with(['user','studio'])->get()->keyBy('user_id'),
        ]);
    }

    public function saveStudent(Request $request, ?StudentProfile $profile=null)
    {
        $data=$request->validate([
            'user_id'=>'required|exists:users,id',
            'studio_id'=>'nullable|exists:studios,id',
            'class_name'=>'nullable|string|max:120',
            'avatar'=>'nullable|image|max:10240',
            'bio'=>'nullable|string|max:5000',
            'portfolio_slug'=>'nullable|string|max:180',
            'is_public'=>'nullable|boolean',
        ]);

        $profile ??= StudentProfile::firstOrNew(['user_id'=>$data['user_id']]);

        if ($request->hasFile('avatar')) {
            if ($profile->avatar && !preg_match('~^(https?:)?//~i',$profile->avatar)) {
                Storage::disk('public')->delete($profile->avatar);
            }
            $data['avatar']=$request->file('avatar')->store('students','public');
        }

        $user=User::findOrFail($data['user_id']);
        $data['portfolio_slug']=$data['portfolio_slug'] ?: Str::slug($user->name).'-'.$user->id;
        $data['is_public']=$request->boolean('is_public');
        $profile->fill($data)->save();

        return back()->with('success','Профиль ученика сохранён.');
    }

    public function portfolio(StudentProfile $profile)
    {
        return view('admin.portfolio', [
            'profile'=>$profile->load(['user','portfolio.studio','portfolio.media','achievements.competition']),
            'studios'=>Studio::orderBy('sort_order')->get(),
        ]);
    }

    public function savePortfolio(Request $request, StudentProfile $profile, ?PortfolioItem $item=null)
    {
        $data=$request->validate([
            'studio_id'=>'nullable|exists:studios,id',
            'title'=>'required|string|max:220',
            'type'=>'required|string|max:80',
            'description'=>'nullable|string|max:5000',
            'cover'=>'nullable|image|max:10240',
            'project_file'=>'nullable|file|max:102400',
            'project_url'=>'nullable|string|max:2000',
            'video_url'=>'nullable|string|max:2000',
            'completed_at'=>'nullable|date',
            'is_featured'=>'nullable|boolean',
            'is_public'=>'nullable|boolean',
        ]);

        $isNew=$item===null;
        $item ??= new PortfolioItem(['student_profile_id'=>$profile->id]);
        $item->student_profile_id=$profile->id;

        if ($request->hasFile('cover')) {
            if ($item->cover && !preg_match('~^(https?:)?//~i',$item->cover)) {
                Storage::disk('public')->delete($item->cover);
            }
            $data['cover']=$request->file('cover')->store('projects/covers','public');
        }

        if ($request->hasFile('project_file')) {
            if ($item->file_path) Storage::disk('public')->delete($item->file_path);
            $file=$request->file('project_file');
            $data['file_path']=$file->store('projects/files','public');
            $data['file_name']=$file->getClientOriginalName();
            $data['mime_type']=$file->getMimeType();
            $data['file_size']=$file->getSize();
        }

        unset($data['project_file']);

        $data['is_featured']=$request->boolean('is_featured');
        $data['is_public']=$request->boolean('is_public');
        $item->fill($data)->save();

        if($isNew){
            return redirect()->route('admin.projects.media',$item)->with('success','Работа создана. Добавьте медиа в галерею.');
        }
        return back()->with('success','Работа добавлена в портфолио.');
    }

    public function deletePortfolio(PortfolioItem $item)
    {
        foreach($item->media as $media){
            if($media->url && !preg_match('~^(https?:)?//~i',$media->url)){
                Storage::disk('public')->delete($media->url);
            }
            $media->delete();
        }
        if ($item->cover && !preg_match('~^(https?:)?//~i',$item->cover)) {
            Storage::disk('public')->delete($item->cover);
        }
        if ($item->file_path) Storage::disk('public')->delete($item->file_path);
        $item->delete();
        return back()->with('success','Работа удалена.');
    }

    public function competitions()
    {
        return view('admin.competitions', [
            'competitions'=>Competition::with(['registrations.user','registrations.documents'])->withCount('registrations')->orderByDesc('starts_on')->get(),
            'profiles'=>StudentProfile::with('user')->get(),
            'achievements'=>Achievement::with(['student.user','competition'])->latest('awarded_at')->get(),
        ]);
    }

    public function editCompetition(Competition $competition)
    {
        return view('admin.competitions', [
            'competitions'=>Competition::with(['registrations.user','registrations.documents'])->withCount('registrations')->orderByDesc('starts_on')->get(),
            'profiles'=>StudentProfile::with('user')->get(),
            'achievements'=>Achievement::with(['student.user','competition'])->latest('awarded_at')->get(),
            'editCompetition'=>$competition,
        ]);
    }

    public function saveCompetition(Request $request, ?Competition $competition=null)
    {
        $data=$request->validate([
            'title'=>'required|string|max:220',
            'organizer'=>'nullable|string|max:220',
            'starts_on'=>'nullable|date',
            'ends_on'=>'nullable|date',
            'location'=>'nullable|string|max:255',
            'description'=>'nullable|string|max:5000',
            'required_documents'=>'nullable|string|max:10000',
            'url'=>'nullable|string|max:2000',
            'cover'=>'nullable|string|max:2000',
            'is_published'=>'nullable|boolean',
        ]);

        $competition ??= new Competition();

        $requirements=collect(preg_split('/\r\n|\r|\n/', (string)($data['required_documents'] ?? '')))
            ->map(fn($v)=>trim($v))
            ->filter()
            ->values()
            ->map(fn($label,$i)=>[
                'key'=>'doc_'.($i+1),
                'label'=>$label,
            ])
            ->all();

        unset($data['required_documents']);
        $data['required_documents_json']=$requirements ?: null;
        $data['is_published']=$request->boolean('is_published');
        $competition->fill($data)->save();

        return back()->with('success','Конкурс сохранён.');
    }

    public function deleteCompetition(Competition $competition)
    {
        $competition->delete();
        return back()->with('success','Конкурс удалён.');
    }

    public function saveAchievement(Request $request, ?Achievement $achievement=null)
    {
        $data=$request->validate([
            'student_profile_id'=>'nullable|exists:student_profiles,id',
            'competition_id'=>'nullable|exists:competitions,id',
            'title'=>'required|string|max:220',
            'result'=>'nullable|string|max:220',
            'level'=>'nullable|string|max:120',
            'awarded_at'=>'nullable|date',
            'description'=>'nullable|string|max:5000',
            'image'=>'nullable|image|max:10240',
            'is_public'=>'nullable|boolean',
        ]);

        $achievement ??= new Achievement();

        if ($request->hasFile('image')) {
            if ($achievement->image && !preg_match('~^(https?:)?//~i',$achievement->image)) {
                Storage::disk('public')->delete($achievement->image);
            }
            $data['image']=$request->file('image')->store('achievements','public');
        }

        $data['is_public']=$request->boolean('is_public');
        $achievement->fill($data)->save();

        return back()->with('success','Достижение сохранено.');
    }

    public function deleteAchievement(Achievement $achievement)
    {
        if ($achievement->image && !preg_match('~^(https?:)?//~i',$achievement->image)) {
            Storage::disk('public')->delete($achievement->image);
        }
        $achievement->delete();
        return back()->with('success','Достижение удалено.');
    }
}
