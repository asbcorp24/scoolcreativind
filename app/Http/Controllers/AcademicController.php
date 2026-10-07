<?php
namespace App\Http\Controllers;

use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\JournalEntry;
use App\Models\JournalLesson;
use App\Models\PortfolioItem;
use App\Models\ScheduleLesson;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AcademicController extends Controller
{
    /**
     * Возвращает все группы, к которым ученик фактически относится.
     *
     * Основной источник — study_group_user. Для старых аккаунтов дополнительно
     * учитываем группы из записей журнала: раньше часть учеников могла получить
     * JournalEntry, но не иметь корректной pivot-записи role=student.
     */
    private function studentGroupIds(User $user)
    {
        $directGroupIds=$user->studentGroups()
            ->pluck('study_groups.id');

        $journalGroupIds=JournalEntry::query()
            ->where('student_id',$user->id)
            ->join('journal_lessons','journal_lessons.id','=','journal_entries.journal_lesson_id')
            ->whereNotNull('journal_lessons.study_group_id')
            ->pluck('journal_lessons.study_group_id');

        return $directGroupIds
            ->merge($journalGroupIds)
            ->filter()
            ->map(fn($id)=>(int)$id)
            ->unique()
            ->values();
    }

    private function canAccessLesson(User $user, JournalLesson $lesson): bool
    {
        return $this->studentGroupIds($user)->contains((int)$lesson->study_group_id);
    }

    public function dashboard()
    {
        $user=auth()->user();
        $groupIds=$this->studentGroupIds($user);

        $lessons=ScheduleLesson::with(['studio','group'])
            ->whereIn('study_group_id',$groupIds)
            ->where('lesson_date','>=',now()->toDateString())
            ->orderBy('lesson_date')->orderBy('starts_at')->take(20)->get();

        $homework=HomeworkAssignment::with(['subject','group','submissions'=>fn($q)=>$q->where('student_id',$user->id)])
            ->whereIn('study_group_id',$groupIds)
            ->where('is_published',true)
            ->orderByRaw('due_at IS NULL, due_at ASC')->get();

        $grades=JournalEntry::with(['lesson.subject','lesson.group'])
            ->where('student_id',$user->id)
            ->latest()->take(30)->get();

        $profile=StudentProfile::firstOrCreate(
            ['user_id'=>$user->id],
            [
                'studio_id'=>$user->studentGroups()->whereNotNull('study_groups.studio_id')->value('study_groups.studio_id'),
                'portfolio_slug'=>'student-'.$user->id,
                'is_public'=>false,
            ]
        );

        $practicalLessons=JournalLesson::with(['subject','group','teacher'])
            ->whereIn('study_group_id',$groupIds)
            ->orderByDesc('lesson_date')
            ->orderByDesc('id')
            ->take(40)
            ->get();

        $practicalWorks=PortfolioItem::where('student_profile_id',$profile->id)
            ->whereNotNull('journal_lesson_id')
            ->get()
            ->keyBy('journal_lesson_id');

        $attendanceEntries=JournalEntry::with(['lesson.subject','lesson.group'])
            ->where('student_id',$user->id)
            ->whereHas('lesson',function($q) use($groupIds){
                $q->whereIn('study_group_id',$groupIds)
                    ->whereDate('lesson_date','<=',now()->toDateString());
            })
            ->get();

        $attendanceTotal=$attendanceEntries->count();
        $attendanceSummary=[
            'total'=>$attendanceTotal,
            'present'=>$attendanceEntries->where('attendance','present')->count(),
            'late'=>$attendanceEntries->where('attendance','late')->count(),
            'absent'=>$attendanceEntries->where('attendance','absent')->count(),
            'excused'=>$attendanceEntries->where('attendance','excused')->count(),
            'rate'=>$attendanceTotal
                ? round((($attendanceEntries->where('attendance','present')->count()+$attendanceEntries->where('attendance','late')->count())/$attendanceTotal)*100,1)
                : null,
        ];

        $attendanceBySubject=$attendanceEntries
            ->groupBy(fn($entry)=>$entry->lesson?->subject_id ?: 0)
            ->map(function($items){
                $total=$items->count();
                $present=$items->where('attendance','present')->count();
                $late=$items->where('attendance','late')->count();
                return [
                    'subject'=>$items->first()?->lesson?->subject?->title ?: 'Без предмета',
                    'total'=>$total,
                    'present'=>$present,
                    'late'=>$late,
                    'absent'=>$items->where('attendance','absent')->count(),
                    'excused'=>$items->where('attendance','excused')->count(),
                    'rate'=>$total ? round((($present+$late)/$total)*100,1) : 0,
                ];
            })
            ->sortBy('subject')
            ->values();

        return view('academic.dashboard',compact(
            'lessons','homework','grades','profile','practicalLessons','practicalWorks',
            'attendanceSummary','attendanceBySubject'
        ));
    }

    public function lesson(JournalLesson $lesson)
    {
        $user=auth()->user();
        abort_unless($this->canAccessLesson($user,$lesson),403);

        $lesson->load(['subject','group','teacher']);
        $profile=StudentProfile::firstOrCreate(
            ['user_id'=>$user->id],
            [
                'studio_id'=>$lesson->group?->studio_id,
                'class_name'=>$lesson->group?->name,
                'portfolio_slug'=>'student-'.$user->id,
                'is_public'=>false,
            ]
        );

        $work=PortfolioItem::with('media')
            ->where('student_profile_id',$profile->id)
            ->where('journal_lesson_id',$lesson->id)
            ->first();

        return view('academic.lesson',compact('lesson','profile','work'));
    }

    public function submitPractical(Request $request, JournalLesson $lesson)
    {
        $user=auth()->user();
        abort_unless($this->canAccessLesson($user,$lesson),403);

        $lesson->load(['subject','group']);

        $data=$request->validate([
            'kind'=>'required|in:drawing,stl,text',
            'title'=>'nullable|string|max:220',
            'description'=>'nullable|string|max:5000',
            'text_content'=>'nullable|string|max:30000',
            'image'=>'nullable|file|max:20480',
            'stl'=>'nullable|file|max:51200',
        ]);

        if($data['kind']==='drawing' && !$request->hasFile('image')){
            return back()->withErrors(['image'=>'Для рисунка выберите изображение.'])->withInput();
        }

        if($data['kind']==='stl' && !$request->hasFile('stl')){
            return back()->withErrors(['stl'=>'Для 3D-работы выберите STL-файл.'])->withInput();
        }

        if($data['kind']==='text' && trim((string)($data['text_content'] ?? ''))===''){
            return back()->withErrors(['text_content'=>'Введите текст практической работы.'])->withInput();
        }

        if($request->hasFile('stl')){
            $extension=strtolower($request->file('stl')->getClientOriginalExtension());
            if($extension!=='stl'){
                return back()->withErrors(['stl'=>'Разрешён только файл .STL.'])->withInput();
            }
        }

        $profile=StudentProfile::firstOrCreate(
            ['user_id'=>$user->id],
            [
                'studio_id'=>$lesson->group?->studio_id,
                'class_name'=>$lesson->group?->name,
                'portfolio_slug'=>'student-'.$user->id,
                'is_public'=>true,
            ]
        );

        $studioId=$lesson->group?->studio_id
            ?: $lesson->subject?->studio_id
            ?: $profile->studio_id;

        if($data['kind']==='drawing' && $request->hasFile('image')){
            if(!StorageQuota::canStore((int)$request->file('image')->getSize())){
                return back()->withErrors(['image'=>'Недостаточно места в хранилище.'])->withInput();
            }
        }

        if($data['kind']==='stl' && $request->hasFile('stl')){
            if(!StorageQuota::canStore((int)$request->file('stl')->getSize())){
                return back()->withErrors(['stl'=>'Недостаточно места в хранилище.'])->withInput();
            }
        }

        $work=PortfolioItem::firstOrNew([
            'student_profile_id'=>$profile->id,
            'journal_lesson_id'=>$lesson->id,
        ]);

        if($work->exists){
            $work->load('media');
            foreach($work->media as $media){
                if($media->url && !preg_match('~^(https?:)?//~i',$media->url)){
                    Storage::disk('public')->delete($media->url);
                }
                $media->delete();
            }
        }

        $title=trim((string)($data['title'] ?? ''));
        if($title===''){
            $title=$lesson->topic ?: ($lesson->subject?->title ?: 'Практическая работа');
        }

        $description=$data['kind']==='text'
            ? trim((string)$data['text_content'])
            : trim((string)($data['description'] ?? ''));

        $work->fill([
            'student_profile_id'=>$profile->id,
            'journal_lesson_id'=>$lesson->id,
            'practical_kind'=>$data['kind'],
            'studio_id'=>$studioId,
            'title'=>$title,
            'type'=>match($data['kind']){
                'drawing'=>'drawing',
                'stl'=>'3d',
                default=>'text',
            },
            'description'=>$description,
            'completed_at'=>$lesson->lesson_date ?: now()->toDateString(),
            'is_featured'=>false,
            'is_public'=>true,
        ])->save();

        if(!$profile->is_public){
            $profile->is_public=true;
        }
        if(!$profile->studio_id && $studioId){
            $profile->studio_id=$studioId;
        }
        if(!$profile->class_name && $lesson->group?->name){
            $profile->class_name=$lesson->group->name;
        }
        $profile->save();

        if($data['kind']==='drawing'){
            $file=$request->file('image');
            try{
                $stored=$this->storePracticalJpeg($file->getRealPath(),$profile->id,$lesson->id);
            }catch(\Throwable $e){
                report($e);
                return back()->withErrors([
                    'image'=>'Не удалось обработать изображение. Проверьте JPG/PNG/WebP и наличие GD на сервере.'
                ])->withInput();
            }

            $work->media()->create([
                'type'=>'photo',
                'title'=>$title,
                'url'=>$stored,
                'file_name'=>pathinfo($stored,PATHINFO_BASENAME),
                'mime_type'=>'image/jpeg',
                'file_size'=>Storage::disk('public')->size($stored),
                'sort_order'=>0,
                'is_visible'=>true,
                'is_featured'=>true,
            ]);
        }

        if($data['kind']==='stl'){
            $file=$request->file('stl');
            $path=$file->storeAs(
                'portfolio/practical/stl/'.$profile->id,
                'lesson-'.$lesson->id.'-'.Str::uuid().'.stl',
                'public'
            );

            $work->media()->create([
                'type'=>'model',
                'title'=>$title,
                'url'=>$path,
                'file_name'=>$file->getClientOriginalName(),
                'mime_type'=>$file->getMimeType() ?: 'model/stl',
                'file_size'=>$file->getSize(),
                'sort_order'=>0,
                'is_visible'=>true,
                'is_featured'=>true,
            ]);
        }

        return redirect()
            ->route('academic.lesson',$lesson)
            ->with('success','Практическая работа сохранена и добавлена в портфолио.');
    }

    private function storePracticalJpeg(string $sourcePath, int $profileId, int $lessonId): string
    {
        if(!function_exists('imagecreatefromstring')){
            throw new \RuntimeException('PHP GD extension is not available.');
        }

        $binary=file_get_contents($sourcePath);
        if($binary===false){
            throw new \RuntimeException('Cannot read image.');
        }

        $src=@imagecreatefromstring($binary);
        if(!$src){
            throw new \RuntimeException('Unsupported image.');
        }

        $width=imagesx($src);
        $height=imagesy($src);
        if($width<1 || $height<1){
            imagedestroy($src);
            throw new \RuntimeException('Invalid image dimensions.');
        }

        $scale=min(1920/$width,1080/$height,1);
        $targetW=max(1,(int)round($width*$scale));
        $targetH=max(1,(int)round($height*$scale));

        $dst=imagecreatetruecolor($targetW,$targetH);
        $white=imagecolorallocate($dst,255,255,255);
        imagefill($dst,0,0,$white);

        imagecopyresampled(
            $dst,$src,
            0,0,0,0,
            $targetW,$targetH,$width,$height
        );

        ob_start();
        imagejpeg($dst,null,88);
        $jpeg=ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        if(!$jpeg){
            throw new \RuntimeException('JPEG encoding failed.');
        }

        $path='portfolio/practical/images/'.$profileId.'/lesson-'.$lessonId.'-'.Str::uuid().'.jpg';
        Storage::disk('public')->put($path,$jpeg);

        return $path;
    }

    public function homework(HomeworkAssignment $assignment)
    {
        $user=auth()->user();
        abort_unless($user->studentGroups()->where('study_groups.id',$assignment->study_group_id)->exists(),403);

        $assignment->load(['subject','group','teacher']);
        $submission=HomeworkSubmission::firstOrNew([
            'homework_assignment_id'=>$assignment->id,
            'student_id'=>$user->id,
        ]);

        return view('academic.homework',compact('assignment','submission'));
    }

    public function submitHomework(Request $request, HomeworkAssignment $assignment)
    {
        $user=auth()->user();
        abort_unless($user->studentGroups()->where('study_groups.id',$assignment->study_group_id)->exists(),403);

        $data=$request->validate([
            'text_answer'=>'nullable|string|max:20000',
            'external_url'=>'nullable|string|max:2000',
            'file'=>'nullable|file|max:51200',
        ]);

        $submission=HomeworkSubmission::firstOrNew([
            'homework_assignment_id'=>$assignment->id,
            'student_id'=>$user->id,
        ]);

        if($request->hasFile('file')){
            if($submission->file) Storage::disk('public')->delete($submission->file);
            $data['file']=$request->file('file')->store('homework/submissions','public');
        }

        $data['status']='submitted';
        $data['submitted_at']=now();
        $submission->fill($data)->save();

        return back()->with('success','Домашняя работа отправлена.');
    }
}
