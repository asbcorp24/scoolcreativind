<?php
namespace App\Http\Controllers;

use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\JournalEntry;
use App\Models\JournalLesson;
use App\Models\StudyGroup;
use App\Models\StudentProfile;
use App\Models\Studio;
use App\Models\Subject;
use App\Models\SubjectLesson;
use App\Models\MediaLibraryItem;
use App\Models\User;
use App\Services\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminAcademicController extends Controller
{
    private function allowedGroupIds()
    {
        if (auth()->user()->is_admin) return StudyGroup::pluck('id');
        return auth()->user()->teacherGroups()->pluck('study_groups.id');
    }

    private function ensureGroupAllowed(int $groupId): void
    {
        abort_unless($this->allowedGroupIds()->contains($groupId),403);
    }

    private function allowedSubjectIdsForGroup(int $groupId)
    {
        $group=StudyGroup::findOrFail($groupId);

        if(auth()->user()->is_admin){
            return $group->subjects()->pluck('subjects.id');
        }

        return $group->subjects()
            ->wherePivot('teacher_id',auth()->id())
            ->pluck('subjects.id');
    }

    private function ensureSubjectAllowed(int $groupId, int $subjectId): void
    {
        $this->ensureGroupAllowed($groupId);
        abort_unless($this->allowedSubjectIdsForGroup($groupId)->contains($subjectId),403);
    }

    private function ensureSubjectPlanAllowed(Subject $subject): void
    {
        if(auth()->user()->is_admin){
            return;
        }

        $allowed=$subject->groups()
            ->wherePivot('teacher_id',auth()->id())
            ->exists();

        abort_unless($allowed,403);
    }

    private function restrictGroupSubjectsForTeacher($groups)
    {
        if(auth()->user()->is_admin){
            return $groups;
        }

        $teacherId=auth()->id();

        $groups->each(function($group) use($teacherId){
            $group->setRelation(
                'subjects',
                $group->subjects
                    ->filter(fn($subject)=>(int)($subject->pivot->teacher_id ?? 0)===$teacherId)
                    ->values()
            );
        });

        return $groups;
    }

    public function groups()
    {
        return view('admin.academic.groups',[
            'groups'=>StudyGroup::with(['studio','students','teachers','subjects'])->whereIn('id',$this->allowedGroupIds())->orderBy('study_year')->orderBy('name')->get(),
            'users'=>User::orderBy('name')->get(),
            'subjects'=>Subject::orderBy('title')->get(),
        ]);
    }

    public function saveGroup(Request $request, ?StudyGroup $group=null)
    {
        $data=$request->validate([
            'name'=>'required|string|max:180',
            'study_year'=>'required|integer|min:1|max:2',
            'code'=>'required|string|max:80|unique:study_groups,code,'.optional($group)->id,
            'studio_id'=>'nullable|exists:studios,id',
            'curator_name'=>'nullable|string|max:180',
            'is_active'=>'nullable|boolean',
        ]);

        $group ??= new StudyGroup();
        $data['is_active']=$request->boolean('is_active');
        $group->fill($data)->save();
        return back()->with('success','Учебная группа сохранена.');
    }


    public function createStudent(Request $request, StudyGroup $group)
    {
        abort_unless(auth()->user()->is_admin,403);

        $data=$request->validate([
            'name'=>'required|string|max:160',
            'email'=>'nullable|email|max:160|unique:users,email',
            'password'=>'nullable|string|min:8|max:100',
        ]);

        $base=Str::slug($data['name']);
        if(!$base) $base='student';
        $email=$data['email'] ?: $base.'.'.random_int(100,999).'@student.local';
        while(User::where('email',$email)->exists()){
            $email=$base.'.'.random_int(1000,9999).'@student.local';
        }

        $password=$data['password'] ?: Str::random(10);

        $user=User::create([
            'name'=>$data['name'],
            'email'=>$email,
            'password'=>Hash::make($password),
            'is_admin'=>false,
        ]);

        $group->users()->attach($user->id,['role'=>'student']);

        StudentProfile::firstOrCreate(
            ['user_id'=>$user->id],
            [
                'studio_id'=>$group->studio_id,
                'class_name'=>$group->name,
                'portfolio_slug'=>Str::slug($user->name).'-'.$user->id,
                'is_public'=>false,
            ]
        );

        return back()->with('success','Ученик создан и добавлен в группу.')
            ->with('created_student_credentials',[
                'name'=>$user->name,
                'email'=>$email,
                'password'=>$password,
            ]);
    }

    public function addMember(Request $request, StudyGroup $group)
    {
        $data=$request->validate([
            'user_id'=>'required|exists:users,id',
            'role'=>'required|in:student,teacher',
        ]);

        abort_unless(auth()->user()->is_admin,403);
        $group->users()->syncWithoutDetaching([
            $data['user_id']=>['role'=>$data['role']]
        ]);

        if($data['role']==='student'){
            $student=User::findOrFail($data['user_id']);

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

        return back()->with('success','Пользователь добавлен в группу.');
    }

    public function removeMember(StudyGroup $group, User $user, string $role)
    {
        abort_unless(auth()->user()->is_admin,403);
        $group->users()->wherePivot('role',$role)->detach($user->id);
        return back()->with('success','Пользователь удалён из группы.');
    }

    public function subjects()
    {
        return view('admin.academic.subjects',[
            'subjects'=>Subject::with('studio')->orderBy('title')->get(),
            'groups'=>StudyGroup::with(['subjects'])->where('is_active',true)->whereIn('id',$this->allowedGroupIds())->orderBy('name')->get(),
            'teachers'=>User::whereHas('studyGroups',fn($q)=>$q->where('role','teacher'))->orderBy('name')->get(),
            'studios'=>Studio::where('is_active',true)->orderBy('sort_order')->get(),
        ]);
    }

    public function saveSubject(Request $request, ?Subject $subject=null)
    {
        $data=$request->validate([
            'title'=>'required|string|max:180',
            'studio_id'=>'nullable|exists:studios,id',
            'description'=>'nullable|string|max:1000',
        ]);
        $subject ??= new Subject();
        $subject->fill($data)->save();
        return back()->with('success','Предмет сохранён.');
    }

    public function subjectLessons(Subject $subject)
    {
        $this->ensureSubjectPlanAllowed($subject);

        $subject->load([
            'studio',
            'lessons'=>fn($q)=>$q->with('media')->orderBy('sort_order')->orderBy('lesson_number'),
        ]);

        return view('admin.academic.subject-lessons',compact('subject'));
    }

    public function saveSubjectLesson(Request $request, Subject $subject, ?SubjectLesson $lesson=null)
    {
        $this->ensureSubjectPlanAllowed($subject);

        if($lesson){
            abort_unless((int)$lesson->subject_id===(int)$subject->id,404);
        }

        $data=$request->validate([
            'lesson_number'=>'required|integer|min:1|max:999',
            'title'=>'required|string|max:255',
            'content'=>'nullable|string|max:50000',
            'homework_description'=>'nullable|string|max:30000',
            'homework_due_days'=>'nullable|integer|min:0|max:365',
            'homework_max_score'=>'required|integer|min:1|max:100',
            'sort_order'=>'nullable|integer|min:0|max:99999',
            'is_published'=>'nullable|boolean',
        ]);

        $duplicate=SubjectLesson::where('subject_id',$subject->id)
            ->where('lesson_number',$data['lesson_number'])
            ->when($lesson,fn($q)=>$q->where('id','<>',$lesson->id))
            ->exists();

        if($duplicate){
            return back()->withErrors([
                'lesson_number'=>'У этого предмета уже есть урок с таким номером.'
            ])->withInput();
        }

        $lesson ??= new SubjectLesson(['subject_id'=>$subject->id]);
        $data['subject_id']=$subject->id;
        $data['sort_order']=$data['sort_order'] ?? $data['lesson_number'];
        $data['is_published']=$request->boolean('is_published');
        $lesson->fill($data)->save();

        return redirect()
            ->route('admin.subjects.lessons',$subject)
            ->with('success','Урок календарно-тематического плана сохранён.');
    }

    public function deleteSubjectLesson(Subject $subject, SubjectLesson $lesson)
    {
        $this->ensureSubjectPlanAllowed($subject);
        abort_unless((int)$lesson->subject_id===(int)$subject->id,404);

        if($lesson->journalLessons()->exists()){
            return back()->withErrors([
                'lesson'=>'Этот урок уже проводился. Его нельзя удалить; снимите публикацию, если нужно скрыть его из плана.'
            ]);
        }

        foreach($lesson->media as $media){
            if($media->url && !preg_match('~^(https?:)?//~i',$media->url)){
                Storage::disk('public')->delete($media->url);
            }
            $media->delete();
        }

        $lesson->delete();
        return back()->with('success','Урок удалён из плана.');
    }

    public function addSubjectLessonMedia(Request $request, Subject $subject, SubjectLesson $lesson)
    {
        $this->ensureSubjectPlanAllowed($subject);
        abort_unless((int)$lesson->subject_id===(int)$subject->id,404);

        $data=$request->validate([
            'type'=>'required|in:photo,panorama,video,model,audio,file,link',
            'title'=>'nullable|string|max:255',
            'url'=>'nullable|string|max:2000',
            'file'=>'nullable|file|max:102400',
            'caption'=>'nullable|string|max:3000',
            'sort_order'=>'nullable|integer|min:0|max:99999',
            'is_visible'=>'nullable|boolean',
        ]);

        if(!$request->filled('url') && !$request->hasFile('file')){
            return back()->withErrors(['file'=>'Укажите ссылку или загрузите файл.'])->withInput();
        }

        if($request->hasFile('file')){
            $file=$request->file('file');
            $ext=strtolower($file->getClientOriginalExtension());
            $allowed=[
                'photo'=>['jpg','jpeg','png','webp','gif'],
                'panorama'=>['jpg','jpeg','png','webp'],
                'video'=>['mp4','webm','mov'],
                'model'=>['glb','gltf','stl'],
                'audio'=>['mp3','wav','ogg','m4a','aac'],
                'file'=>['pdf','doc','docx','xls','xlsx','ppt','pptx','zip','txt'],
                'link'=>[],
            ];

            if(!in_array($ext,$allowed[$data['type']] ?? [],true)){
                return back()->withErrors(['file'=>'Формат файла не подходит выбранному типу материала.'])->withInput();
            }

            if(!StorageQuota::canStore((int)$file->getSize())){
                return back()->withErrors(['file'=>'Недостаточно места в хранилище.'])->withInput();
            }

            $data['url']=$file->store('subject-lessons/'.$lesson->id,'public');
            $data['file_name']=$file->getClientOriginalName();
            $data['mime_type']=$file->getMimeType();
            $data['file_size']=$file->getSize();
        }

        unset($data['file']);
        $data['sort_order']=$data['sort_order'] ?? 0;
        $data['is_visible']=$request->boolean('is_visible');
        $data['is_featured']=false;
        $lesson->media()->create($data);

        return back()->with('success','Материал добавлен к уроку.');
    }

    public function deleteSubjectLessonMedia(Subject $subject, SubjectLesson $lesson, MediaLibraryItem $media)
    {
        $this->ensureSubjectPlanAllowed($subject);
        abort_unless((int)$lesson->subject_id===(int)$subject->id,404);
        abort_unless(
            $media->attachable_type===SubjectLesson::class &&
            (int)$media->attachable_id===(int)$lesson->id,
            404
        );

        if($media->url && !preg_match('~^(https?:)?//~i',$media->url)){
            Storage::disk('public')->delete($media->url);
        }
        $media->delete();

        return back()->with('success','Материал удалён.');
    }

    public function deleteSubject(Subject $subject)
    {
        abort_unless(auth()->user()->is_admin,403);

        if($subject->groups()->exists()){
            return back()->withErrors([
                'subject'=>'Нельзя удалить предмет, пока он закреплён хотя бы за одной группой.'
            ]);
        }

        $subject->delete();
        return back()->with('success','Предмет удалён.');
    }

    public function attachSubject(Request $request, StudyGroup $group)
    {
        $data=$request->validate([
            'subject_id'=>'required|exists:subjects,id',
            'teacher_id'=>'nullable|exists:users,id',
        ]);
        abort_unless(auth()->user()->is_admin,403);
        $teacherId=$data['teacher_id'] ?? null;

        if($group->subjects()->where('subjects.id',$data['subject_id'])->exists()){
            $group->subjects()->updateExistingPivot($data['subject_id'],[
                'teacher_id'=>$teacherId,
                'updated_at'=>now(),
            ]);
        }else{
            $group->subjects()->attach($data['subject_id'],[
                'teacher_id'=>$teacherId,
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);
        }

        return back()->with('success','Предмет и преподаватель закреплены за группой.');
    }

    public function journal(Request $request)
    {
        $groupId=$request->integer('group_id');
        $subjectId=$request->integer('subject_id');

        $groups=StudyGroup::with(['subjects','students'])
            ->whereIn('id',$this->allowedGroupIds())
            ->orderBy('name')
            ->get();

        $groups=$this->restrictGroupSubjectsForTeacher($groups);

        if($groupId) $this->ensureGroupAllowed($groupId);

        $group=$groupId
            ? $groups->firstWhere('id',$groupId)
            : null;

        $subject=null;
        if($group && $subjectId){
            $subject=$group->subjects->firstWhere('id',$subjectId);
        }

        $lessons=collect();
        if($group && $subject){
            $lessons=JournalLesson::with('entries')
                ->where('study_group_id',$group->id)
                ->where('subject_id',$subject->id)
                ->orderBy('lesson_date')
                ->get();
        }

        $subjectOptions=$groups->mapWithKeys(function($item){
            return [
                $item->id=>$item->subjects->map(function($subject){
                    return [
                        'id'=>$subject->id,
                        'title'=>$subject->title,
                        'teacher_id'=>$subject->pivot->teacher_id,
                    ];
                })->values(),
            ];
        });

        return view('admin.academic.journal',compact(
            'groups','group','subject','lessons','subjectOptions'
        ));
    }

    public function createJournalLesson(Request $request)
    {
        $data=$request->validate([
            'study_group_id'=>'required|exists:study_groups,id',
            'subject_id'=>'required|exists:subjects,id',
            'lesson_date'=>'required|date',
            'topic'=>'required|string|max:255',
            'notes'=>'nullable|string|max:5000',
        ]);

        $this->ensureSubjectAllowed((int)$data['study_group_id'],(int)$data['subject_id']);
        $data['teacher_id']=auth()->id();
        $lesson=JournalLesson::create($data);

        $students=StudyGroup::findOrFail($data['study_group_id'])->students;
        foreach($students as $student){
            JournalEntry::firstOrCreate([
                'journal_lesson_id'=>$lesson->id,
                'student_id'=>$student->id,
            ],['attendance'=>'present']);
        }

        return back()->with('success','Урок добавлен в журнал.');
    }

    public function saveJournalEntry(Request $request, JournalEntry $entry)
    {
        $data=$request->validate([
            'attendance'=>'required|in:present,absent,late,excused',
            'grade'=>'nullable|numeric|min:1|max:100',
            'grade_label'=>'nullable|string|max:30',
            'comment'=>'nullable|string|max:1000',
        ]);
        $entry->load('lesson');
        $this->ensureSubjectAllowed(
            (int)$entry->lesson->study_group_id,
            (int)$entry->lesson->subject_id
        );
        $entry->update($data);
        return back()->with('success','Запись журнала сохранена.');
    }

    public function attendance(Request $request)
    {
        $groupId=$request->integer('group_id');
        $subjectId=$request->integer('subject_id');
        $dateFrom=$request->input('date_from') ?: now()->startOfMonth()->toDateString();
        $dateTo=$request->input('date_to') ?: now()->toDateString();

        $groups=StudyGroup::with(['students','subjects'])
            ->whereIn('id',$this->allowedGroupIds())
            ->orderBy('name')
            ->get();

        $groups=$this->restrictGroupSubjectsForTeacher($groups);

        if($groupId){
            $this->ensureGroupAllowed($groupId);
        }

        $group=$groupId ? $groups->firstWhere('id',$groupId) : null;
        $subjects=$group ? $group->subjects : collect();

        if($group && $subjectId && !$subjects->contains('id',$subjectId)){
            $subjectId=0;
        }

        $entries=collect();
        $rows=collect();
        $summary=[
            'total'=>0,
            'present'=>0,
            'late'=>0,
            'absent'=>0,
            'excused'=>0,
            'rate'=>null,
        ];

        if($group){
            $allowedSubjectIds=$group->subjects->pluck('id');

            $entries=JournalEntry::with(['lesson.subject','student'])
                ->whereHas('lesson',function($q) use($group,$subjectId,$dateFrom,$dateTo,$allowedSubjectIds){
                    $q->where('study_group_id',$group->id)
                        ->whereBetween('lesson_date',[$dateFrom,$dateTo]);

                    if($subjectId){
                        $q->where('subject_id',$subjectId);
                    }else{
                        $q->whereIn('subject_id',$allowedSubjectIds);
                    }
                })
                ->get();

            $rows=$group->students
                ->sortBy('name')
                ->values()
                ->map(function($student) use($entries){
                    $items=$entries->where('student_id',$student->id);
                    $total=$items->count();
                    $present=$items->where('attendance','present')->count();
                    $late=$items->where('attendance','late')->count();
                    $absent=$items->where('attendance','absent')->count();
                    $excused=$items->where('attendance','excused')->count();

                    return [
                        'student'=>$student,
                        'total'=>$total,
                        'present'=>$present,
                        'late'=>$late,
                        'absent'=>$absent,
                        'excused'=>$excused,
                        'rate'=>$total ? round((($present+$late)/$total)*100,1) : null,
                    ];
                });

            $total=$entries->count();
            $present=$entries->where('attendance','present')->count();
            $late=$entries->where('attendance','late')->count();

            $summary=[
                'total'=>$total,
                'present'=>$present,
                'late'=>$late,
                'absent'=>$entries->where('attendance','absent')->count(),
                'excused'=>$entries->where('attendance','excused')->count(),
                'rate'=>$total ? round((($present+$late)/$total)*100,1) : null,
            ];
        }

        return view('admin.academic.attendance',compact(
            'groups','group','subjects','subjectId','dateFrom','dateTo','rows','summary'
        ));
    }

    public function homework()
    {
        $groups=StudyGroup::with('subjects')
            ->where('is_active',true)
            ->whereIn('id',$this->allowedGroupIds())
            ->orderBy('name')
            ->get();

        $groups=$this->restrictGroupSubjectsForTeacher($groups);

        $assignments=HomeworkAssignment::with(['group','subject','submissions'])
            ->whereIn('study_group_id',$this->allowedGroupIds());

        if(!auth()->user()->is_admin){
            $assignments->where('teacher_id',auth()->id());
        }

        $subjectOptions=$groups->mapWithKeys(function($group){
            return [
                $group->id=>$group->subjects->map(fn($subject)=>[
                    'id'=>$subject->id,
                    'title'=>$subject->title,
                ])->values(),
            ];
        });

        return view('admin.academic.homework',[
            'assignments'=>$assignments->latest()->get(),
            'groups'=>$groups,
            'subjectOptions'=>$subjectOptions,
        ]);
    }

    public function saveHomework(Request $request, ?HomeworkAssignment $assignment=null)
    {
        $data=$request->validate([
            'study_group_id'=>'required|exists:study_groups,id',
            'subject_id'=>'required|exists:subjects,id',
            'title'=>'required|string|max:255',
            'description'=>'nullable|string|max:20000',
            'due_at'=>'nullable|date',
            'attachment'=>'nullable|file|max:51200',
            'external_url'=>'nullable|string|max:2000',
            'max_score'=>'required|integer|min:1|max:100',
            'is_published'=>'nullable|boolean',
        ]);

        $this->ensureSubjectAllowed((int)$data['study_group_id'],(int)$data['subject_id']);

        if($assignment && !auth()->user()->is_admin){
            abort_unless((int)$assignment->teacher_id===auth()->id(),403);
        }

        $assignment ??= new HomeworkAssignment();
        if($request->hasFile('attachment')){
            if($assignment->attachment) Storage::disk('public')->delete($assignment->attachment);
            $data['attachment']=$request->file('attachment')->store('homework/assignments','public');
        }
        $data['teacher_id']=auth()->id();
        $data['is_published']=$request->boolean('is_published');
        $assignment->fill($data)->save();

        return back()->with('success','Домашнее задание сохранено.');
    }

    public function submissions(HomeworkAssignment $assignment)
    {
        $this->ensureSubjectAllowed((int)$assignment->study_group_id,(int)$assignment->subject_id);
        if(!auth()->user()->is_admin){
            abort_unless((int)$assignment->teacher_id===auth()->id(),403);
        }
        $assignment->load(['group','subject','submissions.student']);
        return view('admin.academic.submissions',compact('assignment'));
    }

    public function reviewSubmission(Request $request, HomeworkSubmission $submission)
    {
        $submission->load('assignment');
        $this->ensureSubjectAllowed(
            (int)$submission->assignment->study_group_id,
            (int)$submission->assignment->subject_id
        );
        if(!auth()->user()->is_admin){
            abort_unless((int)$submission->assignment->teacher_id===auth()->id(),403);
        }
        $data=$request->validate([
            'score'=>'nullable|numeric|min:0',
            'teacher_comment'=>'nullable|string|max:5000',
            'status'=>'required|in:reviewed,returned',
        ]);

        $data['reviewed_at']=now();
        $submission->update($data);

        return back()->with('success','Работа проверена.');
    }
}
