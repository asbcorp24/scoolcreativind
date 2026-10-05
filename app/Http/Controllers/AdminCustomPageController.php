<?php
namespace App\Http\Controllers;

use App\Models\CustomPage;
use App\Models\MediaLibraryItem;
use App\Services\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminCustomPageController extends Controller
{
    public function index()
    {
        return view('admin.pages.index',[
            'pages'=>CustomPage::with('parent')->orderBy('sort_order')->orderBy('title')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.pages.form',[
            'page'=>null,
            'parents'=>CustomPage::orderBy('sort_order')->orderBy('title')->get(),
        ]);
    }

    public function edit(CustomPage $page)
    {
        $page->load('media');
        return view('admin.pages.form',[
            'page'=>$page,
            'parents'=>CustomPage::whereKeyNot($page->id)->orderBy('sort_order')->orderBy('title')->get(),
        ]);
    }

    public function save(Request $request, ?CustomPage $page=null)
    {
        $data=$request->validate([
            'parent_id'=>'nullable|exists:custom_pages,id',
            'title'=>'required|string|max:255',
            'slug'=>'nullable|string|max:180|regex:/^[a-z0-9\-]+$/|unique:custom_pages,slug,'.($page?->id ?? 'NULL'),
            'menu_title'=>'nullable|string|max:120',
            'subtitle'=>'nullable|string|max:500',
            'body_html'=>'nullable|string|max:200000',
            'cover'=>'nullable|image|max:15360|mimes:jpg,jpeg,png,webp',
            'sort_order'=>'nullable|integer|min:0|max:999999',
            'show_in_menu'=>'nullable|boolean',
            'is_published'=>'nullable|boolean',
        ]);

        $page ??= new CustomPage();

        if(!empty($data['parent_id']) && (int)$data['parent_id']===(int)$page->id){
            return back()->withErrors(['parent_id'=>'Раздел не может быть родителем самого себя.'])->withInput();
        }

        $slug=$data['slug'] ?: Str::slug($data['title']);
        if(!$slug)$slug='page-'.Str::lower(Str::random(8));

        $page->parent_id=$data['parent_id'] ?? null;
        $page->title=$data['title'];
        $page->slug=$slug;
        $page->menu_title=$data['menu_title'] ?? null;
        $page->subtitle=$data['subtitle'] ?? null;
        $page->body_html=$this->sanitizeHtml($data['body_html'] ?? '');
        $page->sort_order=$data['sort_order'] ?? 0;
        $page->show_in_menu=$request->boolean('show_in_menu');
        $page->is_published=$request->boolean('is_published');

        if($request->hasFile('cover')){
            $file=$request->file('cover');

            if(!StorageQuota::canStore((int)$file->getSize())){
                return back()->withErrors(['cover'=>'Недостаточно места в хранилище.'])->withInput();
            }

            if($page->cover_path)Storage::disk('public')->delete($page->cover_path);
            $page->cover_path=$file->store('pages/covers','public');
        }

        $page->save();

        return redirect()->route('admin.pages.edit',$page)->with('success','Страница сохранена.');
    }

    private function sanitizeHtml(string $html): string
    {
        $allowed='<p><br><h2><h3><h4><strong><b><em><i><u><s><blockquote><ul><ol><li><a><img><span>';
        $html=strip_tags($html,$allowed);
        $html=preg_replace('/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i','',$html);
        $html=preg_replace('/javascript\s*:/i','',$html);
        return $html;
    }

    public function uploadEditorImage(Request $request)
    {
        $request->validate([
            'image'=>'required|image|max:15360|mimes:jpg,jpeg,png,webp,gif',
        ]);

        $file=$request->file('image');

        if(!StorageQuota::canStore((int)$file->getSize())){
            return response()->json(['message'=>'Недостаточно места в хранилище.'],422);
        }

        $path=$file->store('pages/editor','public');

        return response()->json([
            'url'=>Storage::disk('public')->url($path),
        ]);
    }

    public function addMedia(Request $request, CustomPage $page)
    {
        $data=$request->validate([
            'type'=>'required|in:photo,panorama,video,model,audio,file,link',
            'title'=>'nullable|string|max:255',
            'url'=>'nullable|string|max:2000',
            'file'=>'nullable|file|max:102400',
            'thumbnail'=>'nullable|string|max:2000',
            'caption'=>'nullable|string|max:3000',
            'hotspots_json'=>'nullable|json',
            'sort_order'=>'nullable|integer|min:0|max:999999',
            'is_visible'=>'nullable|boolean',
            'is_featured'=>'nullable|boolean',
        ]);

        if(!$request->hasFile('file') && !$request->filled('url')){
            return back()->withErrors(['file'=>'Загрузите файл или укажите ссылку.'])->withInput();
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
            $data['file_name']=$file->getClientOriginalName();
            $data['mime_type']=$file->getMimeType();
            $data['file_size']=$file->getSize();
        }

        unset($data['file']);
        $data['sort_order']=$data['sort_order'] ?? 0;
        $data['is_visible']=$request->boolean('is_visible');
        $data['is_featured']=$request->boolean('is_featured');
        $page->media()->create($data);

        return back()->with('success','Медиа добавлено.');
    }

    public function updateMediaFlags(Request $request, MediaLibraryItem $media)
    {
        abort_unless($media->attachable_type===CustomPage::class,404);
        $media->update([
            'is_visible'=>$request->boolean('is_visible'),
            'is_featured'=>$request->boolean('is_featured'),
        ]);
        return back()->with('success','Настройки медиа обновлены.');
    }

    public function deleteMedia(MediaLibraryItem $media)
    {
        abort_unless($media->attachable_type===CustomPage::class,404);
        if($media->url && !preg_match('~^(https?:)?//~i',$media->url)){
            Storage::disk('public')->delete($media->url);
        }
        $media->delete();
        return back()->with('success','Медиа удалено.');
    }

    public function delete(CustomPage $page)
    {
        foreach($page->media as $media){
            if($media->url && !preg_match('~^(https?:)?//~i',$media->url)){
                Storage::disk('public')->delete($media->url);
            }
            $media->delete();
        }
        if($page->cover_path)Storage::disk('public')->delete($page->cover_path);
        $page->delete();
        return redirect()->route('admin.pages')->with('success','Страница удалена.');
    }
}
