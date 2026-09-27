<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\PortfolioItem;
use App\Models\MediaLibraryItem;
use App\Models\StudentProfile;
use App\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\StorageQuota;
use Illuminate\Support\Str;

class AdminContentController extends Controller
{
    public function projects()
    {
        return view('admin.projects', [
            'projects'=>PortfolioItem::with(['student.user','studio'])->withCount('media')->latest()->get(),
            'profiles'=>StudentProfile::with('user')->orderByDesc('id')->get(),
            'studios'=>Studio::orderBy('sort_order')->get(),
        ]);
    }

    public function saveProject(Request $request, ?PortfolioItem $project=null)
    {
        $data=$request->validate([
            'student_profile_id'=>'required|exists:student_profiles,id',
            'studio_id'=>'nullable|exists:studios,id',
            'title'=>'required|string|max:220',
            'type'=>'required|string|max:80',
            'description'=>'nullable|string|max:10000',
            'cover'=>'nullable|image|max:10240',
            'project_file'=>'nullable|file|max:102400',
            'project_url'=>'nullable|string|max:2000',
            'video_url'=>'nullable|string|max:2000',
            'completed_at'=>'nullable|date',
            'is_public'=>'nullable|boolean',
            'is_featured'=>'nullable|boolean',
        ]);

        $isNew=$project===null;
        $project ??= new PortfolioItem();

        if($request->hasFile('cover')){
            if($project->cover && !preg_match('~^(https?:)?//~i',$project->cover)){
                Storage::disk('public')->delete($project->cover);
            }
            $data['cover']=$request->file('cover')->store('projects/covers','public');
        }

        if($request->hasFile('project_file')){
            if($project->file_path) Storage::disk('public')->delete($project->file_path);
            $file=$request->file('project_file');
            $data['file_path']=$file->store('projects/files','public');
            $data['file_name']=$file->getClientOriginalName();
            $data['mime_type']=$file->getMimeType();
            $data['file_size']=$file->getSize();
        }

        unset($data['project_file']);
        $data['is_public']=$request->boolean('is_public');
        $data['is_featured']=$request->boolean('is_featured');
        $project->fill($data)->save();

        if($isNew){
            return redirect()->route('admin.projects.media',$project)->with('success','Работа создана. Теперь добавьте фото, 360°, 3D, видео или аудио.');
        }

        return back()->with('success','Проект / работа сохранён.');
    }

    public function deleteProject(PortfolioItem $project)
    {
        foreach($project->media as $media){
            if($media->url && !preg_match('~^(https?:)?//~i',$media->url)){
                Storage::disk('public')->delete($media->url);
            }
            $media->delete();
        }
        if($project->cover && !preg_match('~^(https?:)?//~i',$project->cover)){
            Storage::disk('public')->delete($project->cover);
        }
        if($project->file_path) Storage::disk('public')->delete($project->file_path);
        $project->delete();
        return back()->with('success','Проект удалён.');
    }

    public function projectMedia(PortfolioItem $project)
    {
        $project->load(['student.user','studio','media']);
        return view('admin.project-media',compact('project'));
    }

    public function addProjectMedia(Request $request, PortfolioItem $project)
    {
        $data=$request->validate([
            'type'=>'required|in:photo,panorama,video,model,audio,file,link',
            'title'=>'nullable|string|max:180',
            'url'=>'nullable|string|max:2000',
            'file'=>'nullable|file|max:102400',
            'thumbnail'=>'nullable|string|max:2000',
            'caption'=>'nullable|string|max:3000',
            'hotspots_json'=>'nullable|json',
            'sort_order'=>'nullable|integer|min:0',
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
                'photo'=>['jpg','jpeg','png','webp','gif'],
                'panorama'=>['jpg','jpeg','png','webp'],
                'video'=>['mp4','webm','mov'],
                'model'=>['glb','gltf'],
                'audio'=>['mp3','wav','ogg','m4a','aac'],
                'file'=>['pdf','doc','docx','xls','xlsx','ppt','pptx','zip'],
                'link'=>[],
            ];

            if(!in_array($ext,$allowed[$data['type']] ?? [],true)){
                return back()->withErrors(['file'=>'Формат файла не подходит для выбранного типа медиа.'])->withInput();
            }

            if(!StorageQuota::canStore((int)$file->getSize())){
                return back()->withErrors(['file'=>'Недостаточно места в хранилище.'])->withInput();
            }

            $data['url']=$file->store('media/'.$data['type'],'public');
        }

        unset($data['file']);
        $data['sort_order']=$data['sort_order'] ?? 0;
        $data['is_visible']=$request->boolean('is_visible');
        $data['is_featured']=$request->boolean('is_featured');
        $project->media()->create($data);

        return back()->with('success','Медиа добавлено к работе.');
    }

    public function updateProjectMediaFlags(Request $request, MediaLibraryItem $media)
    {
        abort_unless($media->attachable_type===PortfolioItem::class,404);
        $media->update([
            'is_visible'=>$request->boolean('is_visible'),
            'is_featured'=>$request->boolean('is_featured'),
        ]);
        return back()->with('success','Настройки медиа обновлены.');
    }

    public function deleteProjectMedia(MediaLibraryItem $media)
    {
        abort_unless($media->attachable_type===PortfolioItem::class,404);
        if($media->url && !preg_match('~^(https?:)?//~i',$media->url)){
            Storage::disk('public')->delete($media->url);
        }
        $media->delete();
        return back()->with('success','Медиа удалено.');
    }

    public function events()
    {
        return view('admin.events', ['events'=>Event::orderByDesc('starts_at')->get()]);
    }

    public function saveEvent(Request $request, ?Event $event=null)
    {
        $data=$request->validate([
            'title'=>'required|string|max:220',
            'slug'=>'nullable|string|max:220',
            'description'=>'nullable|string|max:5000',
            'starts_at'=>'required|date',
            'location'=>'nullable|string|max:255',
            'cover'=>'nullable|string|max:2000',
            'registration_url'=>'nullable|string|max:2000',
            'is_published'=>'nullable|boolean',
        ]);
        $event ??= new Event();
        $data['slug']=$data['slug'] ?: Str::slug($data['title']);
        $data['is_published']=$request->boolean('is_published');
        $event->fill($data)->save();
        return back()->with('success','Событие сохранено.');
    }

    public function deleteEvent(Event $event)
    {
        $event->delete();
        return back()->with('success','Событие удалено.');
    }
}
