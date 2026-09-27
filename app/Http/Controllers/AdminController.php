<?php
namespace App\Http\Controllers;

use App\Models\AdmissionApplication;
use App\Models\MediaLibraryItem;
use App\Models\NewsPost;
use App\Models\NewsMedia;
use App\Models\Studio;
use App\Models\User;
use App\Models\StudentProfile;
use App\Models\StudyGroup;
use App\Services\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function dashboard()
    {
        $user=auth()->user();

        if (!$user->is_admin && !$user->canAdminSection('studios') && !$user->canAdminSection('applications') && !$user->canAdminSection('news')) {
            return redirect()->route($user->adminLandingRoute());
        }

        return view('admin.dashboard', [
            'studios'=>$user->canAdminSection('studios') ? Studio::orderBy('sort_order')->get() : collect(),
            'news'=>$user->canAdminSection('news') ? NewsPost::latest()->take(8)->get() : collect(),
            'applications'=>$user->canAdminSection('applications') ? AdmissionApplication::with(['studio','user'])->latest()->take(30)->get() : collect(),
            'groups'=>$user->canAdminSection('applications') ? StudyGroup::where('is_active',true)->orderBy('study_year')->orderBy('name')->get() : collect(),
        ]);
    }

    public function studioForm(?Studio $studio=null)
    {
        return view('admin.studio-form', compact('studio'));
    }

    public function saveStudio(Request $request, ?Studio $studio=null)
    {
        $data=$request->validate([
            'title'=>'required|string|max:180','slug'=>'nullable|string|max:180','subtitle'=>'nullable|string|max:255',
            'description'=>'nullable|string','icon'=>'nullable|string|max:80','accent'=>'nullable|string|max:40',
            'cover'=>'nullable|image|max:10240','sort_order'=>'nullable|integer|min:0','is_active'=>'nullable|boolean'
        ]);
        $studio ??= new Studio();
        $data['slug']=$data['slug'] ?: Str::slug($data['title']);
        $data['is_active']=$request->boolean('is_active');
        if ($request->hasFile('cover')) {
            if ($studio->cover) Storage::disk('public')->delete($studio->cover);
            $data['cover']=$request->file('cover')->store('studios','public');
        }
        $studio->fill($data)->save();
        return redirect()->route('admin.dashboard')->with('success','Раздел сохранён.');
    }

    public function deleteStudio(Studio $studio)
    {
        $studio->delete();
        return back()->with('success','Раздел удалён.');
    }

    public function mediaForm(Studio $studio){ return view('admin.media-form', compact('studio')); }

    public function addMedia(Request $request, Studio $studio)
    {
        $data=$request->validate([
            'type'=>'required|in:photo,panorama,video,model,audio',
            'title'=>'nullable|string|max:180',
            'url'=>'nullable|string|max:2000',
            'file'=>'nullable|file|max:51200',
            'thumbnail'=>'nullable|string|max:2000',
            'caption'=>'nullable|string|max:2000',
            'hotspots_json'=>'nullable|json',
            'sort_order'=>'nullable|integer|min:0',
            'is_visible'=>'nullable|boolean',
            'is_featured'=>'nullable|boolean'
        ]);

        if ($request->hasFile('file')) {
            $file=$request->file('file');
            $extension=strtolower($file->getClientOriginalExtension());

            $allowedExtensions=[
                'photo'=>['jpg','jpeg','png','webp'],
                'panorama'=>['jpg','jpeg','png','webp'],
                'model'=>['glb','gltf'],
                'audio'=>['mp3','wav','ogg','m4a','aac'],
                'video'=>[],
            ];

            if (!in_array($extension,$allowedExtensions[$data['type']] ?? [],true)) {
                return back()->withErrors([
                    'file'=>'Недопустимый файл для выбранного типа. Для 3D используйте .glb или .gltf.'
                ])->withInput();
            }
        }

        if ($data['type']==='video' && !$request->filled('url')) {
            return back()->withErrors(['url'=>'Для Rutube-видео укажите ссылку.'])->withInput();
        }

        if (!$request->filled('url') && !$request->hasFile('file')) {
            return back()->withErrors(['url'=>'Укажите URL или загрузите файл.'])->withInput();
        }

        if ($request->hasFile('file')) {
            $file=$request->file('file');

            if (!StorageQuota::canStore((int)$file->getSize())) {
                $remaining=StorageQuota::formatBytes(StorageQuota::remainingBytes());
                return back()->withErrors([
                    'file'=>'Недостаточно выделенного места. Свободно: '.$remaining.'. Удалите ненужные файлы или увеличьте лимит в Настройки сайта / Хранилище.'
                ])->withInput();
            }

            $data['url']=$file->store('media/'.$data['type'],'public');

            if (!$data['url']) {
                return back()->withErrors([
                    'file'=>'Не удалось сохранить файл на сервере.'
                ])->withInput();
            }
        }

        unset($data['file']);
        $data['is_visible']=$request->boolean('is_visible');
        $data['is_featured']=$request->boolean('is_featured');
        $studio->media()->create($data);
        return back()->with('success','Медиа добавлено.');
    }

    public function updateMediaFlags(Request $request, MediaLibraryItem $media)
    {
        $data=$request->validate([
            'is_visible'=>'nullable|boolean',
            'is_featured'=>'nullable|boolean',
        ]);

        $media->update([
            'is_visible'=>$request->boolean('is_visible'),
            'is_featured'=>$request->boolean('is_featured'),
        ]);

        return back()->with('success','Настройки медиа обновлены.');
    }

    public function deleteMedia(MediaLibraryItem $media)
    {
        if ($media->url && !preg_match('~^(https?:)?//~i',$media->url)) {
            Storage::disk('public')->delete($media->url);
        }
        $media->delete();
        return back()->with('success','Медиа удалено.');
    }

    public function newsForm(?NewsPost $post=null)
    {
        if($post)$post->load('media');
        return view('admin.news-form', compact('post'));
    }

    public function saveNews(Request $request, ?NewsPost $post=null)
    {
        $data=$request->validate([
            'title'=>'required|string|max:220',
            'slug'=>'nullable|string|max:220',
            'excerpt'=>'nullable|string|max:1000',
            'body'=>'required|string',
            'cover_url'=>'nullable|url|max:2000',
            'cover_file'=>'nullable|image|max:15360|mimes:jpg,jpeg,png,webp',
            'published_at'=>'nullable|date',
            'is_published'=>'nullable|boolean'
        ]);

        $post ??= new NewsPost();
        $data['slug']=$data['slug'] ?: Str::slug($data['title']);
        $data['is_published']=$request->boolean('is_published');

        if($request->hasFile('cover_file')){
            $file=$request->file('cover_file');

            if(!StorageQuota::canStore((int)$file->getSize())){
                return back()->withErrors(['cover_file'=>'Недостаточно места в хранилище.'])->withInput();
            }

            if($post->cover && !preg_match('~^(https?:)?//~i',$post->cover)){
                Storage::disk('public')->delete($post->cover);
            }

            $data['cover']=$file->store('news/covers','public');
        } elseif($request->filled('cover_url')) {
            if($post->cover && !preg_match('~^(https?:)?//~i',$post->cover)){
                Storage::disk('public')->delete($post->cover);
            }
            $data['cover']=$request->input('cover_url');
        } else {
            unset($data['cover']);
        }

        unset($data['cover_file'],$data['cover_url']);
        $post->fill($data)->save();

        return redirect()->route('admin.news.edit',$post)->with('success','Новость сохранена.');
    }

    public function addNewsMedia(Request $request, NewsPost $post)
    {
        $data=$request->validate([
            'type'=>'required|in:image,video,audio,file,link',
            'title'=>'nullable|string|max:255',
            'url'=>'nullable|url|max:2000',
            'file'=>'nullable|file|max:102400',
            'sort_order'=>'nullable|integer|min:0|max:999999',
        ]);

        if(!$request->hasFile('file') && !$request->filled('url')){
            return back()->withErrors(['file'=>'Загрузите файл или укажите ссылку.'])->withInput();
        }

        $allowed=[
            'image'=>['jpg','jpeg','png','webp','gif'],
            'video'=>['mp4','webm'],
            'audio'=>['mp3','wav','ogg','m4a','aac'],
            'file'=>['pdf','doc','docx','xls','xlsx','ppt','pptx','zip'],
            'link'=>[],
        ];

        $mediaData=[
            'type'=>$data['type'],
            'title'=>$data['title'] ?? null,
            'url'=>$data['url'] ?? null,
            'sort_order'=>$data['sort_order'] ?? 0,
        ];

        if($request->hasFile('file')){
            $file=$request->file('file');
            $ext=strtolower($file->getClientOriginalExtension());

            if($data['type']==='link' || !in_array($ext,$allowed[$data['type']] ?? [],true)){
                return back()->withErrors(['file'=>'Формат файла не подходит для выбранного типа медиа.'])->withInput();
            }

            if(!StorageQuota::canStore((int)$file->getSize())){
                return back()->withErrors(['file'=>'Недостаточно места в хранилище.'])->withInput();
            }

            $mediaData['path']=$file->store('news/media/'.$data['type'],'public');
            $mediaData['file_name']=$file->getClientOriginalName();
            $mediaData['mime_type']=$file->getMimeType();
            $mediaData['file_size']=$file->getSize();
        }

        $post->media()->create($mediaData);

        return back()->with('success','Медиа добавлено к новости.');
    }

    public function deleteNewsMedia(NewsMedia $media)
    {
        if($media->path)Storage::disk('public')->delete($media->path);
        $media->delete();
        return back()->with('success','Медиа удалено.');
    }


    public function enrollApplication(Request $request, AdmissionApplication $application)
    {
        $data=$request->validate([
            'study_group_id'=>'required|exists:study_groups,id',
        ]);

        $group=StudyGroup::findOrFail($data['study_group_id']);
        $user=$application->user;

        if(!$user && $application->email){
            $user=User::where('email',$application->email)->first();
        }

        $createdPassword=null;

        if(!$user){
            $email=$application->email;
            if(!$email){
                $base=Str::slug($application->name) ?: 'student';
                $email=$base.'.'.random_int(1000,9999).'@student.local';
                while(User::where('email',$email)->exists()){
                    $email=$base.'.'.random_int(10000,99999).'@student.local';
                }
            }

            $createdPassword=Str::random(10);

            $user=User::create([
                'name'=>$application->name,
                'email'=>$email,
                'phone'=>$application->phone,
                'password'=>Hash::make($createdPassword),
                'is_admin'=>false,
            ]);
        }

        $group->users()->syncWithoutDetaching([
            $user->id=>['role'=>'student']
        ]);

        StudentProfile::firstOrCreate(
            ['user_id'=>$user->id],
            [
                'studio_id'=>$group->studio_id,
                'class_name'=>$group->name,
                'portfolio_slug'=>Str::slug($user->name).'-'.$user->id,
                'is_public'=>false,
            ]
        );

        $application->update([
            'user_id'=>$user->id,
            'status'=>'accepted',
        ]);

        $redirect=back()->with('success','Ученик зачислен в группу «'.$group->name.'».');

        if($createdPassword){
            $redirect->with('created_student_credentials',[
                'name'=>$user->name,
                'email'=>$user->email,
                'password'=>$createdPassword,
            ]);
        }

        return $redirect;
    }

    public function applicationStatus(Request $request, AdmissionApplication $application)
    {
        $data=$request->validate(['status'=>'required|in:new,processing,accepted,rejected']);
        $application->update($data);
        return back()->with('success','Статус заявки изменён.');
    }
}
