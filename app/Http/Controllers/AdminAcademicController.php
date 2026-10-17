<?php
namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminAcademicController extends Controller
{
    private function allowedGroupIds()
    {
        if (auth()->user()->is_admin) return StudyGroup::pluck('id');

        $direct=auth()->user()->teacherGroups()->pluck('study_groups.id');
        $substitute=ScheduleLesson::where('substitute_teacher_id',auth()->id())
            ->whereNotNull('study_group_id')
            ->pluck('study_group_id');

        return $direct->merge($substitute)->filter()->unique()->values();
    }

    private function ensureGroupAllowed(int $groupId): void
    {
        abort_unless($this->allowedGroupIds()->contains($groupId),403);
    }

    private function ensureGroupWritable(int $groupId): void
    {
        $this->ensureGroupAllowed($groupId);

        $group=StudyGroup::with('academicYear')->findOrFail($groupId);
        abort_if(
            $group->academicYear?->is_closed,
            423,
            'Учебный год '.$group->academicYear->name.' закрыт. Изменение учебных данных запрещено.'
        );
    }

    private function allowedSubjectIdsForGroup(int $groupId)
    {
        $group=StudyGroup::findOrFail($groupId);

        if(auth()->user()->is_admin){
            return $group->subjects()->pluck('subjects.id');
        }

        $direct=$group->subjects()
            ->wherePivot('teacher_id',auth()->id())
            ->pluck('subjects.id');

        $substitute=ScheduleLesson::where('study_group_id',$groupId)
            ->where('substitute_teacher_id',auth()->id())
            ->whereNotNull('subject_id')
            ->pluck('subject_id');

        $conducted=JournalLesson::where('study_group_id',$groupId)
            ->where('teacher_id',auth()->id())
            ->pluck('subject_id');

        return $direct
            ->merge($substitute)
            ->merge($conducted)
            ->filter()
            ->unique()
            ->values();
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
            ->exists()
            || ScheduleLesson::where('subject_id',$subject->id)
                ->where('substitute_teacher_id',auth()->id())
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
            'groups'=>StudyGroup::with(['academicYear','studio','students','teachers','subjects'])
                ->whereIn('id',$this->allowedGroupIds())
                ->orderBy('academic_year_id')
                ->orderBy('study_year')
                ->orderBy('name')
                ->get(),
            'users'=>User::orderBy('name')->get(),
            'subjects'=>Subject::orderBy('title')->get(),
            'academicYears'=>AcademicYear::orderByDesc('starts_on')->get(),
        ]);
    }

    public function saveGroup(Request $request, ?StudyGroup $group=null)
    {
        $data=$request->validate([
            'academic_year_id'=>'nullable|exists:academic_years,id',
            'name'=>'required|string|max:180',
            'study_year'=>'required|integer|min:1|max:2',
            'code'=>'required|string|max:80|unique:study_groups,code,'.optional($group)->id,
            'studio_id'=>'nullable|exists:studios,id',
            'curator_name'=>'nullable|string|max:180',
            'is_active'=>'nullable|boolean',
        ]);

        $group ??= new StudyGroup();
        $data['academic_year_id']=$data['academic_year_id']
            ?? AcademicYear::where('is_current',true)->value('id');
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

    public function importStudentsCsv(Request $request, StudyGroup $group)
    {
        abort_unless(auth()->user()->is_admin,403);

        $request->validate([
            'students_file'=>'required|file|max:10240',
        ]);

        $file=$request->file('students_file');
        $extension=strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($extension,['csv','txt'],true),422,'Загрузите CSV-файл.');

        $raw=file_get_contents($file->getRealPath());
        if($raw===false){
            return back()->withErrors(['students_file'=>'Не удалось прочитать файл.']);
        }

        $raw=preg_replace('/^\xEF\xBB\xBF/','',$raw);
        if(!mb_check_encoding($raw,'UTF-8')){
            $raw=mb_convert_encoding($raw,'UTF-8','Windows-1251');
        }

        $lines=preg_split('/\r\n|\r|\n/',$raw);
        $lines=array_values(array_filter($lines,fn($line)=>trim($line)!==''));

        if(!$lines){
            return back()->withErrors(['students_file'=>'Файл пуст.']);
        }

        $delimiter=substr_count($lines[0],';')>=substr_count($lines[0],',') ? ';' : ',';
        $rows=array_map(fn($line)=>str_getcsv($line,$delimiter),$lines);

        $first=array_map(fn($v)=>mb_strtolower(trim((string)$v)),$rows[0]);
        $hasHeader=collect($first)->contains(fn($v)=>in_array($v,['фио','имя','name','email','e-mail','пароль','password'],true));
        if($hasHeader){
            array_shift($rows);
        }

        $created=0;
        $attached=0;
        $credentials=[];

        foreach($rows as $row){
            $name=trim((string)($row[0] ?? ''));
            $email=trim((string)($row[1] ?? ''));
            $password=trim((string)($row[2] ?? ''));

            if($name==='') continue;

            $user=$email!=='' ? User::where('email',$email)->first() : null;

            if(!$user){
                $base=Str::slug($name) ?: 'student';
                if($email===''){
                    $email=$base.'.'.random_int(100,999).'@student.local';
                    while(User::where('email',$email)->exists()){
                        $email=$base.'.'.random_int(1000,9999).'@student.local';
                    }
                }elseif(User::where('email',$email)->exists()){
                    continue;
                }

                if($password==='') $password=Str::random(10);

                $user=User::create([
                    'name'=>$name,
                    'email'=>$email,
                    'password'=>Hash::make($password),
                    'is_admin'=>false,
                    'registration_status'=>'active',
                    'activated_at'=>now(),
                    'activated_by'=>auth()->id(),
                ]);
                $created++;

                $credentials[]=[
                    'name'=>$name,
                    'email'=>$email,
                    'password'=>$password,
                ];
            }

            $exists=$group->users()
                ->where('users.id',$user->id)
                ->wherePivot('role','student')
                ->exists();

            if(!$exists){
                $group->users()->attach($user->id,['role'=>'student']);
                $attached++;
            }

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

        ActivityLog::record(
            'students.imported',
            $group,
            'Импорт учеников: создано '.$created.', добавлено в группу '.$attached
        );

        return back()
            ->with('success','Импорт завершён: создано '.$created.', добавлено в группу '.$attached.'.')
            ->with('imported_student_credentials',$credentials);
    }

    public function removeMember(StudyGroup $group, User $user, string $role)
    {
        abort_unless(auth()->user()->is_admin,403);
        $group->users()->wherePivot('role',$role)->detach($user->id);
        return back()->with('success','Пользователь удалён из группы.');
    }

    public function subjects()
    {
        $subjectsQuery=Subject::with('studio')->withCount('lessons');

        if(!auth()->user()->is_admin){
            $teacherId=auth()->id();
            $subjectsQuery->whereHas('groups',function($q) use($teacherId){
                $q->where('group_subjects.teacher_id',$teacherId);
            });
        }

        $groups=StudyGroup::with(['subjects'])
            ->where('is_active',true)
            ->whereIn('id',$this->allowedGroupIds())
            ->orderBy('name')
            ->get();

        $groups=$this->restrictGroupSubjectsForTeacher($groups);

        return view('admin.academic.subjects',[
            'subjects'=>$subjectsQuery->orderBy('title')->get(),
            'groups'=>$groups,
            'teachers'=>auth()->user()->is_admin
                ? User::where(function($q){
                    $q->where('is_teacher',true)
                      ->orWhereHas('studyGroups',fn($g)=>$g->where('role','teacher'));
                })->orderBy('name')->get()
                : collect([auth()->user()]),
            'studios'=>auth()->user()->is_admin
                ? Studio::where('is_active',true)->orderBy('sort_order')->get()
                : collect(),
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

        $subject->load('studio');

        $lessons=$subject->lessons()
            ->with('media')
            ->orderBy('sort_order')
            ->orderBy('lesson_number')
            ->paginate(10)
            ->withQueryString();

        return view('admin.academic.subject-lessons',compact('subject','lessons'));
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

    public function importSubjectLessonsJson(Request $request, Subject $subject)
    {
        $this->ensureSubjectPlanAllowed($subject);

        $request->validate([
            'json_file'=>'nullable|file|max:10240|required_without:json_text',
            'json_text'=>'nullable|string|required_without:json_file',
            'existing_action'=>'required|in:update,skip',
        ],[
            'json_file.required_without'=>'Выберите JSON-файл или вставьте JSON в поле.',
            'json_text.required_without'=>'Выберите JSON-файл или вставьте JSON в поле.',
        ]);

        $json=$request->filled('json_text')
            ? $request->input('json_text')
            : file_get_contents($request->file('json_file')->getRealPath());

        // Убираем UTF-8 BOM, который часто появляется после сохранения JSON в Windows.
        $json=preg_replace('/^\xEF\xBB\xBF/','',$json ?? '');

        try{
            $payload=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        }catch(\JsonException $e){
            return back()->withErrors([
                'json_file'=>'Некорректный JSON: '.$e->getMessage()
            ])->withInput();
        }

        // Поддерживаются два варианта:
        // 1) {"lessons":[...]}
        // 2) непосредственно массив уроков [...]
        $lessons=is_array($payload) && array_key_exists('lessons',$payload)
            ? $payload['lessons']
            : $payload;

        if(!is_array($lessons) || !array_is_list($lessons)){
            return back()->withErrors([
                'json_file'=>'JSON должен содержать массив lessons либо сам быть массивом уроков.'
            ])->withInput();
        }

        if(count($lessons)===0){
            return back()->withErrors(['json_file'=>'В JSON нет уроков для импорта.'])->withInput();
        }

        if(count($lessons)>1000){
            return back()->withErrors(['json_file'=>'За один импорт допускается не более 1000 уроков.'])->withInput();
        }

        $prepared=[];

        foreach($lessons as $index=>$row){
            if(!is_array($row)){
                return back()->withErrors([
                    'json_file'=>'Элемент '.($index+1).' должен быть объектом урока.'
                ])->withInput();
            }

            $validator=validator($row,[
                'lesson_number'=>'required|integer|min:1|max:9999',
                'title'=>'required|string|max:255',
                'content'=>'nullable|string|max:50000',
                'homework_description'=>'nullable|string|max:30000',
                'homework_due_days'=>'nullable|integer|min:0|max:365',
                'homework_max_score'=>'nullable|integer|min:1|max:100',
                'sort_order'=>'nullable|integer|min:0|max:99999',
                'is_published'=>'nullable|boolean',
                'media'=>'nullable|array|max:100',
                'media.*.type'=>'required_with:media|string|in:photo,panorama,video,model,audio,file,link',
                'media.*.title'=>'nullable|string|max:255',
                'media.*.url'=>'required_with:media|string|max:2000',
                'media.*.caption'=>'nullable|string|max:3000',
                'media.*.sort_order'=>'nullable|integer|min:0|max:99999',
                'media.*.is_visible'=>'nullable|boolean',
            ]);

            if($validator->fails()){
                return back()->withErrors([
                    'json_file'=>'Ошибка в уроке '.($index+1).': '.$validator->errors()->first()
                ])->withInput();
            }

            $data=$validator->validated();

            $prepared[]=[
                'lesson'=>[
                    'lesson_number'=>(int)$data['lesson_number'],
                    'title'=>$data['title'],
                    'content'=>$data['content'] ?? null,
                    'homework_description'=>$data['homework_description'] ?? null,
                    'homework_due_days'=>$data['homework_due_days'] ?? null,
                    'homework_max_score'=>$data['homework_max_score'] ?? 5,
                    'sort_order'=>$data['sort_order'] ?? (int)$data['lesson_number'],
                    'is_published'=>array_key_exists('is_published',$data)
                        ? (bool)$data['is_published']
                        : true,
                ],
                'media'=>$data['media'] ?? [],
            ];
        }

        // Защита от двух одинаковых номеров внутри одного JSON.
        $numbers=array_column(array_column($prepared,'lesson'),'lesson_number');
        if(count($numbers)!==count(array_unique($numbers))){
            return back()->withErrors([
                'json_file'=>'В JSON есть повторяющиеся номера уроков.'
            ])->withInput();
        }

        $created=0;
        $updated=0;
        $skipped=0;
        $mediaCount=0;
        $existingAction=$request->input('existing_action','update');

        DB::transaction(function() use(
            $subject,$prepared,$existingAction,
            &$created,&$updated,&$skipped,&$mediaCount
        ){
            foreach($prepared as $item){
                $lessonData=$item['lesson'];

                $lesson=SubjectLesson::where('subject_id',$subject->id)
                    ->where('lesson_number',$lessonData['lesson_number'])
                    ->first();

                if($lesson && $existingAction==='skip'){
                    $skipped++;
                    continue;
                }

                if($lesson){
                    $lesson->fill($lessonData)->save();
                    $updated++;
                }else{
                    $lesson=SubjectLesson::create(array_merge(
                        ['subject_id'=>$subject->id],
                        $lessonData
                    ));
                    $created++;
                }

                // Из JSON импортируются только материалы по URL.
                // Повторный импорт обновляет существующую запись, а не плодит дубликаты.
                foreach($item['media'] as $media){
                    $type=$media['type'];
                    $url=$media['url'];

                    $lesson->media()->updateOrCreate(
                        [
                            'type'=>$type,
                            'url'=>$url,
                        ],
                        [
                            'title'=>$media['title'] ?? null,
                            'caption'=>$media['caption'] ?? null,
                            'sort_order'=>$media['sort_order'] ?? 0,
                            'is_visible'=>array_key_exists('is_visible',$media)
                                ? (bool)$media['is_visible']
                                : true,
                            'is_featured'=>false,
                        ]
                    );

                    $mediaCount++;
                }
            }
        });

        $message="Импорт КТП завершён: создано {$created}, обновлено {$updated}";
        if($skipped){
            $message.=", пропущено {$skipped}";
        }
        if($mediaCount){
            $message.=", материалов обработано {$mediaCount}";
        }
        $message.='.';

        return redirect()
            ->route('admin.subjects.lessons',$subject)
            ->with('success',$message);
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
        $dateFrom=$request->input('date_from');
        $dateTo=$request->input('date_to');
        $lessonQuery=trim((string)$request->input('lesson_q',''));
        $studentId=$request->integer('student_id');

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
        $planLessons=collect();

        if($group && $subjectId){
            $subject=$group->subjects->firstWhere('id',$subjectId);

            if($subject){
                $subject->load([
                    'lessons'=>fn($q)=>$q->where('is_published',true)
                        ->orderBy('sort_order')
                        ->orderBy('lesson_number')
                ]);
                $planLessons=$subject->lessons;
            }
        }

        $lessons=collect();
        if($group && $subject){
            if($studentId && !$group->students->contains('id',$studentId)){
                $studentId=0;
            }

            $lessonBuilder=JournalLesson::with(['entries','planLesson','homeworkAssignment'])
                ->where('study_group_id',$group->id)
                ->where('subject_id',$subject->id);

            if($dateFrom){
                $lessonBuilder->whereDate('lesson_date','>=',$dateFrom);
            }

            if($dateTo){
                $lessonBuilder->whereDate('lesson_date','<=',$dateTo);
            }

            if($lessonQuery!==''){
                $lessonBuilder->where(function($q) use($lessonQuery){
                    $q->where('topic','like','%'.$lessonQuery.'%')
                        ->orWhereHas('planLesson',function($plan) use($lessonQuery){
                            $plan->where('title','like','%'.$lessonQuery.'%');

                            if(ctype_digit($lessonQuery)){
                                $plan->orWhere('lesson_number',(int)$lessonQuery);
                            }
                        });
                });
            }

            $lessons=$lessonBuilder
                ->orderByDesc('lesson_date')
                ->orderByDesc('id')
                ->paginate(10)
                ->withQueryString();
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
            'groups','group','subject','lessons','subjectOptions','planLessons',
            'dateFrom','dateTo','lessonQuery','studentId'
        ));
    }

    public function createJournalLesson(Request $request)
    {
        $data=$request->validate([
            'study_group_id'=>'required|exists:study_groups,id',
            'subject_id'=>'required|exists:subjects,id',
            'subject_lesson_id'=>'required|exists:subject_lessons,id',
            'lesson_date'=>'required|date',
            'notes'=>'nullable|string|max:5000',
        ]);

        $this->ensureSubjectAllowed(
            (int)$data['study_group_id'],
            (int)$data['subject_id']
        );
        $this->ensureGroupWritable((int)$data['study_group_id']);

        $planLesson=SubjectLesson::where('id',$data['subject_lesson_id'])
            ->where('subject_id',$data['subject_id'])
            ->where('is_published',true)
            ->firstOrFail();

        $lesson=JournalLesson::create([
            'study_group_id'=>$data['study_group_id'],
            'subject_id'=>$data['subject_id'],
            'subject_lesson_id'=>$planLesson->id,
            'teacher_id'=>auth()->id(),
            'lesson_date'=>$data['lesson_date'],
            'topic'=>$planLesson->title,
            'notes'=>$data['notes'] ?? null,
        ]);

        $students=StudyGroup::findOrFail($data['study_group_id'])->students;
        foreach($students as $student){
            JournalEntry::firstOrCreate([
                'journal_lesson_id'=>$lesson->id,
                'student_id'=>$student->id,
            ],['attendance'=>'present']);
        }

        if(trim((string)$planLesson->homework_description)!==''){
            $dueAt=null;
            if($planLesson->homework_due_days!==null){
                $dueAt=\Carbon\Carbon::parse($data['lesson_date'])
                    ->addDays((int)$planLesson->homework_due_days)
                    ->endOfDay();
            }

            HomeworkAssignment::firstOrCreate(
                ['journal_lesson_id'=>$lesson->id],
                [
                    'study_group_id'=>$data['study_group_id'],
                    'subject_id'=>$data['subject_id'],
                    'subject_lesson_id'=>$planLesson->id,
                    'teacher_id'=>auth()->id(),
                    'title'=>'Домашнее задание: '.$planLesson->title,
                    'description'=>$planLesson->homework_description,
                    'due_at'=>$dueAt,
                    'max_score'=>$planLesson->homework_max_score ?: 5,
                    'is_published'=>true,
                ]
            );
        }

        return back()->with('success','Урок из календарно-тематического плана добавлен в журнал.');
    }

    public function saveJournalEntry(Request $request, JournalEntry $entry)
    {
        $data=$request->validate([
            'attendance'=>'required|in:present,absent,late,excused',
            'absence_reason'=>'nullable|string|max:2000',
            'absence_document'=>'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'grade'=>'nullable|numeric|min:1|max:100',
            'grade_label'=>'nullable|string|max:30',
            'comment'=>'nullable|string|max:1000',
        ]);
        $entry->load('lesson');
        $this->ensureSubjectAllowed(
            (int)$entry->lesson->study_group_id,
            (int)$entry->lesson->subject_id
        );
        $this->ensureGroupWritable((int)$entry->lesson->study_group_id);
        $before=$entry->toArray();

        if($request->hasFile('absence_document')){
            if($entry->absence_document){
                Storage::disk('public')->delete($entry->absence_document);
            }
            $data['absence_document']=$request->file('absence_document')->store('attendance/proofs','public');
        }else{
            unset($data['absence_document']);
        }

        $entry->update($data);

        ActivityLog::record(
            'journal.entry.updated',
            $entry,
            'Изменена запись журнала для ученика #'.$entry->student_id,
            $before,
            $entry->fresh()->toArray()
        );

        if($request->expectsJson()){
            return response()->json([
                'ok'=>true,
                'message'=>'Запись сохранена',
                'entry'=>[
                    'id'=>$entry->id,
                    'attendance'=>$entry->attendance,
                    'grade'=>$entry->grade,
                    'grade_label'=>$entry->grade_label,
                    'comment'=>$entry->comment,
                ],
            ]);
        }

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

    public function reports()
    {
        $groups=StudyGroup::with(['subjects','studio'])
            ->whereIn('id',$this->allowedGroupIds())
            ->orderBy('study_year')
            ->orderBy('name')
            ->get();

        $groups=$this->restrictGroupSubjectsForTeacher($groups);

        $subjectOptions=$groups->mapWithKeys(function($group){
            return [
                $group->id=>$group->subjects->map(fn($subject)=>[
                    'id'=>$subject->id,
                    'title'=>$subject->title,
                ])->values(),
            ];
        });

        $ktpSubjects=$groups
            ->flatMap(fn($group)=>$group->subjects)
            ->unique('id')
            ->sortBy('title')
            ->values();

        return view('admin.academic.reports',compact(
            'groups','subjectOptions','ktpSubjects'
        ));
    }

    public function printGroupProgressReport(Request $request)
    {
        $data=$request->validate([
            'group_id'=>'required|exists:study_groups,id',
            'date_from'=>'nullable|date',
            'date_to'=>'nullable|date',
        ]);

        $groupId=(int)$data['group_id'];
        $this->ensureGroupAllowed($groupId);

        $dateFrom=$data['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo=$data['date_to'] ?? now()->toDateString();

        $group=StudyGroup::with(['students','studio','academicYear','subjects'])->findOrFail($groupId);
        $allowedSubjectIds=auth()->user()->is_admin
            ? $group->subjects->pluck('id')
            : $this->allowedSubjectIdsForGroup($groupId);

        $entries=JournalEntry::with(['lesson.subject','student'])
            ->whereHas('lesson',function($q) use($groupId,$allowedSubjectIds,$dateFrom,$dateTo){
                $q->where('study_group_id',$groupId)
                    ->whereIn('subject_id',$allowedSubjectIds)
                    ->whereBetween('lesson_date',[$dateFrom,$dateTo]);
            })
            ->get();

        $rows=$group->students->sortBy('name')->values()->map(function($student) use($entries){
            $items=$entries->where('student_id',$student->id);
            $total=$items->count();
            $present=$items->where('attendance','present')->count();
            $late=$items->where('attendance','late')->count();
            $absent=$items->where('attendance','absent')->count();
            $excused=$items->where('attendance','excused')->count();
            $grades=$items->whereNotNull('grade')->pluck('grade')->map(fn($v)=>(float)$v);

            return [
                'student'=>$student,
                'lessons'=>$total,
                'present'=>$present,
                'late'=>$late,
                'absent'=>$absent,
                'excused'=>$excused,
                'attendance'=>$total ? round((($present+$late)/$total)*100,1) : null,
                'average'=>$grades->count() ? round($grades->avg(),2) : null,
                'grades_count'=>$grades->count(),
            ];
        });

        $subjects=$group->subjects->whereIn('id',$allowedSubjectIds)->values();

        return view('admin.academic.print-group-progress',compact(
            'group','rows','subjects','dateFrom','dateTo'
        ));
    }

    public function printJournalReport(Request $request)
    {
        $data=$request->validate([
            'group_id'=>'required|exists:study_groups,id',
            'subject_id'=>'required|exists:subjects,id',
            'date_from'=>'nullable|date',
            'date_to'=>'nullable|date',
        ]);

        $groupId=(int)$data['group_id'];
        $subjectId=(int)$data['subject_id'];
        $this->ensureSubjectAllowed($groupId,$subjectId);

        $dateFrom=$data['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo=$data['date_to'] ?? now()->toDateString();

        $group=StudyGroup::with(['students','studio'])->findOrFail($groupId);
        $subject=Subject::with('studio')->findOrFail($subjectId);

        $lessons=JournalLesson::with(['entries','planLesson','teacher'])
            ->where('study_group_id',$groupId)
            ->where('subject_id',$subjectId)
            ->whereBetween('lesson_date',[$dateFrom,$dateTo])
            ->orderBy('lesson_date')
            ->orderBy('id')
            ->get();

        $students=$group->students->sortBy('name')->values();

        $attendanceMatrix=[];
        $gradeMatrix=[];
        $studentSummary=[];

        foreach($students as $student){
            $present=0;
            $late=0;
            $absent=0;
            $excused=0;
            $grades=[];

            foreach($lessons as $lesson){
                $entry=$lesson->entries->firstWhere('student_id',$student->id);

                $attendance=$entry?->attendance;
                $attendanceMatrix[$student->id][$lesson->id]=match($attendance){
                    'present'=>'Б',
                    'absent'=>'Н',
                    'late'=>'О',
                    'excused'=>'У',
                    default=>'—',
                };

                if($attendance==='present')$present++;
                if($attendance==='late')$late++;
                if($attendance==='absent')$absent++;
                if($attendance==='excused')$excused++;

                $grade=$entry?->grade ?? $entry?->grade_label;
                $gradeMatrix[$student->id][$lesson->id]=$grade ?: '—';

                if(is_numeric($entry?->grade)){
                    $grades[]=(float)$entry->grade;
                }
            }

            $total=$lessons->count();
            $studentSummary[$student->id]=[
                'present'=>$present,
                'late'=>$late,
                'absent'=>$absent,
                'excused'=>$excused,
                'attendance_rate'=>$total ? round((($present+$late)/$total)*100,1) : null,
                'average_grade'=>count($grades) ? round(array_sum($grades)/count($grades),2) : null,
            ];
        }

        return view('admin.academic.print-journal',compact(
            'group','subject','lessons','students','attendanceMatrix','gradeMatrix',
            'studentSummary','dateFrom','dateTo'
        ));
    }

    public function exportJournalCsv(Request $request)
    {
        $data=$request->validate([
            'group_id'=>'required|exists:study_groups,id',
            'subject_id'=>'required|exists:subjects,id',
            'date_from'=>'nullable|date',
            'date_to'=>'nullable|date',
        ]);

        $groupId=(int)$data['group_id'];
        $subjectId=(int)$data['subject_id'];
        $this->ensureSubjectAllowed($groupId,$subjectId);

        $dateFrom=$data['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo=$data['date_to'] ?? now()->toDateString();

        $group=StudyGroup::findOrFail($groupId);
        $subject=Subject::findOrFail($subjectId);

        $lessons=JournalLesson::with(['entries.student','planLesson'])
            ->where('study_group_id',$groupId)
            ->where('subject_id',$subjectId)
            ->whereBetween('lesson_date',[$dateFrom,$dateTo])
            ->orderBy('lesson_date')
            ->get();

        $filename='journal-'.Str::slug($group->name).'-'.Str::slug($subject->title).'-'.$dateFrom.'-'.$dateTo.'.csv';

        return response()->streamDownload(function() use($lessons,$group,$subject,$dateFrom,$dateTo){
            $out=fopen('php://output','w');
            fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['Группа',$group->name],';');
            fputcsv($out,['Предмет',$subject->title],';');
            fputcsv($out,['Период',$dateFrom.' — '.$dateTo],';');
            fputcsv($out,[], ';');
            fputcsv($out,['Дата','Урок КТП','Тема','Ученик','Посещение','Оценка','Комментарий'],';');

            $attendanceLabels=[
                'present'=>'Был',
                'absent'=>'Отсутствовал',
                'late'=>'Опоздал',
                'excused'=>'Уважительная причина',
            ];

            foreach($lessons as $lesson){
                foreach($lesson->entries->sortBy(fn($e)=>$e->student?->name ?? '') as $entry){
                    fputcsv($out,[
                        $lesson->lesson_date->format('d.m.Y'),
                        $lesson->planLesson?->lesson_number,
                        $lesson->topic,
                        $entry->student?->name,
                        $attendanceLabels[$entry->attendance] ?? $entry->attendance,
                        $entry->grade ?? $entry->grade_label,
                        $entry->comment,
                    ],';');
                }
            }

            fclose($out);
        },$filename,[
            'Content-Type'=>'text/csv; charset=UTF-8',
        ]);
    }

    public function printKtpReport(Request $request, Subject $subject)
    {
        $this->ensureSubjectPlanAllowed($subject);

        $groupId=$request->integer('group_id');
        $group=null;

        if($groupId){
            $this->ensureSubjectAllowed($groupId,$subject->id);
            $group=StudyGroup::findOrFail($groupId);
        }

        $subject->load([
            'studio',
            'lessons'=>fn($q)=>$q->withCount('media')
                ->orderBy('sort_order')
                ->orderBy('lesson_number'),
        ]);

        $actualDates=collect();

        if($group){
            $actualDates=JournalLesson::where('study_group_id',$group->id)
                ->where('subject_id',$subject->id)
                ->whereNotNull('subject_lesson_id')
                ->orderBy('lesson_date')
                ->get()
                ->groupBy('subject_lesson_id')
                ->map(fn($items)=>$items->pluck('lesson_date')
                    ->filter()
                    ->map(fn($date)=>$date->format('d.m.Y'))
                    ->implode(', '));
        }

        return view('admin.academic.print-ktp',compact(
            'subject','group','actualDates'
        ));
    }

    public function markAllPresent(JournalLesson $lesson)
    {
        $lesson->load('entries');
        $this->ensureSubjectAllowed(
            (int)$lesson->study_group_id,
            (int)$lesson->subject_id
        );
        $this->ensureGroupWritable((int)$lesson->study_group_id);

        $before=$lesson->entries->map(fn($entry)=>[
            'id'=>$entry->id,
            'attendance'=>$entry->attendance,
        ])->values()->all();

        JournalEntry::where('journal_lesson_id',$lesson->id)
            ->update(['attendance'=>'present','updated_at'=>now()]);

        ActivityLog::record(
            'journal.all_present',
            $lesson,
            'Все ученики отмечены присутствующими: '.$lesson->topic,
            ['entries'=>$before],
            ['attendance'=>'present']
        );

        return back()->with('success','Все ученики отмечены как присутствующие.');
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
        $this->ensureGroupWritable((int)$data['study_group_id']);

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
        $this->ensureGroupWritable((int)$submission->assignment->study_group_id);
        if(!auth()->user()->is_admin){
            abort_unless((int)$submission->assignment->teacher_id===auth()->id(),403);
        }
        $data=$request->validate([
            'score'=>'nullable|numeric|min:0',
            'teacher_comment'=>'nullable|string|max:5000',
            'status'=>'required|in:reviewed,returned',
        ]);

        $before=$submission->toArray();
        $data['reviewed_at']=now();
        $submission->update($data);

        ActivityLog::record(
            'homework.reviewed',
            $submission,
            'Проверена домашняя работа ученика #'.$submission->student_id,
            $before,
            $submission->fresh()->toArray()
        );

        return back()->with('success','Работа проверена.');
    }
}
