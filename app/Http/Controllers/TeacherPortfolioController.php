<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PortfolioItem;
use App\Services\StorageQuota;
use App\Models\MediaLibraryItem;
use App\Models\Studio;
use App\Models\Subject;
use App\Models\TeacherPortfolioItem;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TeacherPortfolioController extends Controller
{
    public function index()
    {
        $profiles=TeacherProfile::with(['user','studio'])
            ->where('is_public',true)
            ->whereHas('user',function($q){
                $q->where('is_teacher',true)
                  ->orWhereHas('studyGroups',fn($g)=>$g->where('role','teacher'));
            })
            ->orderBy('id')
            ->get();

        return view('teachers.index',compact('profiles'));
    }

    public function show(TeacherProfile $profile)
    {
        abort_unless(
            $profile->is_public ||
            (auth()->check() && (auth()->user()->is_admin || auth()->id()===$profile->user_id)),
            404
        );

        $profile->load([
            'user',
            'studio',
            'portfolio'=>fn($q)=>$q->where('is_public',true)->orderByDesc('is_featured')->latest('completed_at'),
            'portfolio.studio',
            'portfolio.media'=>fn($q)=>$q->where('is_visible',true)->orderBy('sort_order')->orderBy('id'),
            'mentoredWorks'=>fn($q)=>$q->where('is_public',true)->latest('completed_at')->take(24),
            'mentoredWorks.student.user',
            'mentoredWorks.subject',
            'mentoredWorks.lesson.subject',
        ]);

        $subjects=Subject::whereHas('groups',function($q) use($profile){
                $q->where('group_subjects.teacher_id',$profile->user_id);
            })
            ->orderBy('title')
            ->get();

        return view('teachers.show',compact('profile','subjects'));
    }

    public function edit(?User $teacher=null)
    {
        $user=auth()->user();

        if(!$user->is_admin){
            $teacher=$user;
        }else{
            $teacher ??= $user;
        }

        abort_unless($teacher->isTeacherRole() || $teacher->is_admin,404);

        if(!$user->is_admin){
            abort_unless($teacher->id===$user->id,403);
        }

        $profile=TeacherProfile::firstOrCreate(
            ['user_id'=>$teacher->id],
            [
                'slug'=>$this->uniqueSlug($teacher->name,$teacher->id),
                'headline'=>'Преподаватель ШКИ',
                'is_public'=>false,
            ]
        );

        $profile->load(['portfolio.studio','portfolio.media','studio','mentoredWorks.student.user','mentoredWorks.subject']);

        return view('admin.teacher-portfolio.edit',[
            'teacher'=>$teacher,
            'profile'=>$profile,
            'studios'=>Studio::orderBy('sort_order')->get(),
        ]);
    }

    public function saveProfile(Request $request, ?User $teacher=null)
    {
        $user=auth()->user();
        $teacher=$user->is_admin ? ($teacher ?? $user) : $user;

        abort_unless($teacher->isTeacherRole() || $teacher->is_admin,404);
        if(!$user->is_admin) abort_unless($teacher->id===$user->id,403);

        $profile=TeacherProfile::firstOrNew(['user_id'=>$teacher->id]);

        $data=$request->validate([
            'studio_id'=>'nullable|exists:studios,id',
            'slug'=>'nullable|string|max:180',
            'avatar'=>'nullable|image|max:10240',
            'headline'=>'nullable|string|max:255',
            'bio'=>'nullable|string|max:10000',
            'education'=>'nullable|string|max:10000',
            'experience'=>'nullable|string|max:10000',
            'competencies'=>'nullable|string|max:10000',
            'courses'=>'nullable|string|max:10000',
            'publications'=>'nullable|string|max:10000',
            'professional_achievements'=>'nullable|string|max:10000',
            'website_url'=>'nullable|string|max:2000',
            'vk_url'=>'nullable|string|max:2000',
            'telegram_url'=>'nullable|string|max:2000',
            'is_public'=>'nullable|boolean',
        ]);

        $before=$profile->exists ? $profile->toArray() : null;

        $slug=Str::slug($data['slug'] ?: $teacher->name);
        if($slug==='') $slug='teacher-'.$teacher->id;

        $exists=TeacherProfile::where('slug',$slug)
            ->when($profile->exists,fn($q)=>$q->where('id','<>',$profile->id))
            ->exists();

        if($exists) $slug.='-'.$teacher->id;
        $data['slug']=$slug;

        if($request->hasFile('avatar')){
            if($profile->avatar && !preg_match('~^(https?:)?//~i',$profile->avatar)){
                Storage::disk('public')->delete($profile->avatar);
            }
            $data['avatar']=$request->file('avatar')->store('teachers','public');
        }

        $data['is_public']=$request->boolean('is_public');
        $profile->fill($data);
        $profile->user_id=$teacher->id;
        $profile->save();

        ActivityLog::record(
            'teacher_portfolio.profile',
            $profile,
            'Обновлено портфолио преподавателя: '.$teacher->name,
            $before,
            $profile->fresh()->toArray()
        );

        return back()->with('success','Профиль преподавателя сохранён.');
    }

    public function saveProject(Request $request, TeacherProfile $profile, ?TeacherPortfolioItem $item=null)
    {
        $this->authorizeProfile($profile);

        if($item){
            abort_unless((int)$item->teacher_profile_id===(int)$profile->id,404);
        }

        $data=$request->validate([
            'studio_id'=>'nullable|exists:studios,id',
            'title'=>'required|string|max:255',
            'type'=>'required|string|max:80',
            'description'=>'nullable|string|max:10000',
            'cover'=>'nullable|image|max:10240',
            'project_url'=>'nullable|string|max:2000',
            'video_url'=>'nullable|string|max:2000',
            'completed_at'=>'nullable|date',
            'is_featured'=>'nullable|boolean',
            'is_public'=>'nullable|boolean',
        ]);

        $item ??= new TeacherPortfolioItem(['teacher_profile_id'=>$profile->id]);
        $before=$item->exists ? $item->toArray() : null;

        if($request->hasFile('cover')){
            if($item->cover && !preg_match('~^(https?:)?//~i',$item->cover)){
                Storage::disk('public')->delete($item->cover);
            }
            $data['cover']=$request->file('cover')->store('teachers/projects','public');
        }

        $data['is_featured']=$request->boolean('is_featured');
        $data['is_public']=$request->boolean('is_public');
        $item->teacher_profile_id=$profile->id;
        $item->fill($data)->save();

        ActivityLog::record(
            $before ? 'teacher_portfolio.project.updated' : 'teacher_portfolio.project.created',
            $item,
            'Проект преподавателя: '.$item->title,
            $before,
            $item->fresh()->toArray()
        );

        return back()->with('success','Проект преподавателя сохранён.');
    }

    public function media(TeacherProfile $profile, TeacherPortfolioItem $item)
    {
        $this->authorizeProfile($profile);
        abort_unless((int)$item->teacher_profile_id===(int)$profile->id,404);

        $item->load(['teacherProfile.user','studio','media']);

        return view('admin.teacher-portfolio.media',[
            'profile'=>$profile,
            'item'=>$item,
            'limits'=>[
                'photo'=>'10 МБ',
                'panorama'=>'20 МБ',
                'video'=>'50 МБ',
                'model'=>'30 МБ',
                'audio'=>'20 МБ',
                'file'=>'25 МБ',
            ],
        ]);
    }

    public function addMedia(Request $request, TeacherProfile $profile, TeacherPortfolioItem $item)
    {
        $this->authorizeProfile($profile);
        abort_unless((int)$item->teacher_profile_id===(int)$profile->id,404);

        if($item->media()->count()>=30){
            return back()->withErrors(['file'=>'Для одного проекта разрешено не более 30 материалов.'])->withInput();
        }

        $data=$request->validate([
            'type'=>'required|in:photo,panorama,video,model,audio,file,link',
            'title'=>'nullable|string|max:180',
            'url'=>'nullable|string|max:2000',
            'file'=>'nullable|file|max:51200',
            'thumbnail'=>'nullable|string|max:2000',
            'caption'=>'nullable|string|max:3000',
            'hotspots_json'=>'nullable|json',
            'sort_order'=>'nullable|integer|min:0|max:9999',
            'is_visible'=>'nullable|boolean',
            'is_featured'=>'nullable|boolean',
        ]);

        if(!$request->filled('url') && !$request->hasFile('file')){
            return back()->withErrors(['file'=>'Укажите URL или загрузите файл.'])->withInput();
        }

        if($request->hasFile('file')){
            $file=$request->file('file');
            $ext=strtolower($file->getClientOriginalExtension());

            $allowed=[
                'photo'=>['jpg','jpeg','png','webp'],
                'panorama'=>['jpg','jpeg','png','webp'],
                'video'=>['mp4','webm','mov'],
                'model'=>['glb','gltf','stl'],
                'audio'=>['mp3','wav','ogg','m4a','aac'],
                'file'=>['pdf','doc','docx','xls','xlsx','ppt','pptx','zip'],
                'link'=>[],
            ];

            $maxBytes=[
                'photo'=>10*1024*1024,
                'panorama'=>20*1024*1024,
                'video'=>50*1024*1024,
                'model'=>30*1024*1024,
                'audio'=>20*1024*1024,
                'file'=>25*1024*1024,
                'link'=>0,
            ];

            if(!in_array($ext,$allowed[$data['type']] ?? [],true)){
                return back()->withErrors([
                    'file'=>'Формат файла не подходит для выбранного типа медиа.'
                ])->withInput();
            }

            if($file->getSize()>($maxBytes[$data['type']] ?? 0)){
                $mb=round(($maxBytes[$data['type']] ?? 0)/1024/1024);
                return back()->withErrors([
                    'file'=>'Файл слишком большой. Лимит для этого типа: '.$mb.' МБ.'
                ])->withInput();
            }

            if(!StorageQuota::canStore((int)$file->getSize())){
                return back()->withErrors([
                    'file'=>'Недостаточно свободного места в хранилище сайта.'
                ])->withInput();
            }

            $data['url']=$file->store('teachers/media/'.$data['type'],'public');
            $data['file_name']=$file->getClientOriginalName();
            $data['mime_type']=$file->getMimeType();
            $data['file_size']=$file->getSize();
        }

        unset($data['file']);
        $data['sort_order']=$data['sort_order'] ?? 0;
        $data['is_visible']=$request->boolean('is_visible');
        $data['is_featured']=$request->boolean('is_featured');

        $media=$item->media()->create($data);

        if($media->is_featured){
            $item->media()->where('id','<>',$media->id)->update(['is_featured'=>false]);
        }

        ActivityLog::record(
            'teacher_portfolio.media.created',
            $media,
            'Добавлено медиа к проекту преподавателя: '.$item->title
        );

        return back()->with('success','Материал добавлен.');
    }

    public function updateMediaFlags(Request $request, TeacherProfile $profile, TeacherPortfolioItem $item, MediaLibraryItem $media)
    {
        $this->authorizeProfile($profile);
        abort_unless((int)$item->teacher_profile_id===(int)$profile->id,404);
        abort_unless(
            $media->attachable_type===TeacherPortfolioItem::class &&
            (int)$media->attachable_id===(int)$item->id,
            404
        );

        $media->update([
            'is_visible'=>$request->boolean('is_visible'),
            'is_featured'=>$request->boolean('is_featured'),
        ]);

        if($media->is_featured){
            $item->media()->where('id','<>',$media->id)->update(['is_featured'=>false]);
        }

        return back()->with('success','Настройки материала обновлены.');
    }

    public function deleteMedia(TeacherProfile $profile, TeacherPortfolioItem $item, MediaLibraryItem $media)
    {
        $this->authorizeProfile($profile);
        abort_unless((int)$item->teacher_profile_id===(int)$profile->id,404);
        abort_unless(
            $media->attachable_type===TeacherPortfolioItem::class &&
            (int)$media->attachable_id===(int)$item->id,
            404
        );

        if($media->url && !preg_match('~^(https?:)?//~i',$media->url)){
            Storage::disk('public')->delete($media->url);
        }

        ActivityLog::record(
            'teacher_portfolio.media.deleted',
            $media,
            'Удалено медиа проекта преподавателя: '.$item->title,
            $media->toArray(),
            null
        );

        $media->delete();

        return back()->with('success','Материал удалён.');
    }

    public function deleteProject(TeacherProfile $profile, TeacherPortfolioItem $item)
    {
        $this->authorizeProfile($profile);
        abort_unless((int)$item->teacher_profile_id===(int)$profile->id,404);

        $item->load('media');

        foreach($item->media as $media){
            if($media->url && !preg_match('~^(https?:)?//~i',$media->url)){
                Storage::disk('public')->delete($media->url);
            }
            $media->delete();
        }

        if($item->cover && !preg_match('~^(https?:)?//~i',$item->cover)){
            Storage::disk('public')->delete($item->cover);
        }

        ActivityLog::record(
            'teacher_portfolio.project.deleted',
            $item,
            'Удалён проект преподавателя: '.$item->title,
            $item->toArray(),
            null
        );

        $item->delete();

        return back()->with('success','Проект удалён.');
    }

    private function authorizeProfile(TeacherProfile $profile): void
    {
        $user=auth()->user();
        abort_unless($user->is_admin || (int)$profile->user_id===$user->id,403);
    }

    private function uniqueSlug(string $name, int $userId): string
    {
        $slug=Str::slug($name) ?: 'teacher-'.$userId;
        if(TeacherProfile::where('slug',$slug)->exists()) $slug.='-'.$userId;
        return $slug;
    }
}
