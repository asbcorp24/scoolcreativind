<?php
namespace App\Http\Controllers;

use App\Models\Clip;
use App\Services\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class AdminClipController extends Controller
{
    private const ALLOWED_EXTENSIONS=[
        'html','htm','css','js','mjs','json','txt',
        'mp3','wav','ogg','m4a','aac',
        'png','jpg','jpeg','webp','gif','svg','ico',
        'glb','gltf','bin',
        'woff','woff2','ttf','otf',
        'mp4','webm'
    ];

    public function index()
    {
        return view('admin.clips',[
            'clips'=>Clip::orderBy('sort_order')->orderByDesc('id')->get(),
        ]);
    }

    public function edit(Clip $clip)
    {
        return view('admin.clips',[
            'clips'=>Clip::orderBy('sort_order')->orderByDesc('id')->get(),
            'editClip'=>$clip,
        ]);
    }

    public function save(Request $request, ?Clip $clip=null)
    {
        $data=$request->validate([
            'title'=>'required|string|max:255',
            'slug'=>'nullable|string|max:180|regex:/^[a-z0-9\-]+$/|unique:clips,slug,'.($clip?->id ?? 'NULL'),
            'description'=>'nullable|string|max:5000',
            'archive'=>[$clip ? 'nullable' : 'required','file','max:153600','mimes:zip'],
            'cover'=>'nullable|image|max:8192|mimes:jpg,jpeg,png,webp',
            'sort_order'=>'nullable|integer|min:0|max:999999',
            'is_published'=>'nullable|boolean',
        ]);

        $clip ??= new Clip();
        $slug=$data['slug'] ?: Str::slug($data['title']);
        if(!$slug)$slug='clip-'.Str::lower(Str::random(8));

        $clip->title=$data['title'];
        $clip->slug=$slug;
        $clip->description=$data['description'] ?? null;
        $clip->sort_order=$data['sort_order'] ?? 0;
        $clip->is_published=$request->boolean('is_published');

        if($request->hasFile('cover')){
            $cover=$request->file('cover');
            if(!StorageQuota::canStore((int)$cover->getSize())){
                return back()->withErrors(['cover'=>'Недостаточно места в хранилище.'])->withInput();
            }
            if($clip->cover_path)Storage::disk('public')->delete($clip->cover_path);
            $clip->cover_path=$cover->store('clips/covers','public');
        }

        if($request->hasFile('archive')){
            $archive=$request->file('archive');
            $result=$this->installArchive($archive,$clip);

            if(isset($result['error'])){
                return back()->withErrors(['archive'=>$result['error']])->withInput();
            }

            if($clip->content_dir){
                Storage::disk('public')->deleteDirectory($clip->content_dir);
            }

            $clip->content_dir=$result['content_dir'];
            $clip->entry_file=$result['entry_file'];
            $clip->archive_name=$archive->getClientOriginalName();
            $clip->archive_size=$archive->getSize();
        }

        $clip->save();

        return redirect()->route('admin.clips')->with('success','Клип сохранён.');
    }

    private function installArchive($uploadedFile, Clip $clip): array
    {
        $zip=new ZipArchive();
        $status=$zip->open($uploadedFile->getRealPath());
        if($status!==true){
            return ['error'=>'Не удалось открыть ZIP-архив.'];
        }

        if($zip->numFiles<1 || $zip->numFiles>800){
            $zip->close();
            return ['error'=>'В архиве должно быть от 1 до 800 файлов.'];
        }

        $indexCandidates=[];
        $totalUncompressed=0;

        for($i=0;$i<$zip->numFiles;$i++){
            $stat=$zip->statIndex($i);
            $name=str_replace('\\','/',$stat['name'] ?? '');

            if($name==='' || str_contains($name,"\0") || str_starts_with($name,'/') || preg_match('/^[A-Za-z]:\//',$name)){
                $zip->close();
                return ['error'=>'Архив содержит недопустимый путь.'];
            }

            $parts=array_values(array_filter(explode('/',$name),fn($p)=>$p!==''));
            if(in_array('..',$parts,true)){
                $zip->close();
                return ['error'=>'Архив содержит небезопасный путь "../".'];
            }

            if(str_ends_with($name,'/'))continue;

            $ext=Str::lower(pathinfo($name,PATHINFO_EXTENSION));
            if(!in_array($ext,self::ALLOWED_EXTENSIONS,true)){
                $zip->close();
                return ['error'=>'Недопустимый файл в архиве: '.$name];
            }

            $totalUncompressed+=(int)($stat['size'] ?? 0);
            if($totalUncompressed>350*1024*1024){
                $zip->close();
                return ['error'=>'Распакованный клип превышает 350 МБ.'];
            }

            if(Str::lower(basename($name))==='index.html'){
                $indexCandidates[]=$name;
            }
        }

        if(!$indexCandidates){
            $zip->close();
            return ['error'=>'В архиве не найден index.html.'];
        }

        if(!StorageQuota::canStore($totalUncompressed)){
            $zip->close();
            return ['error'=>'Недостаточно места в хранилище для распакованного клипа.'];
        }

        usort($indexCandidates,fn($a,$b)=>substr_count($a,'/')<=>substr_count($b,'/'));
        $entry=$indexCandidates[0];

        $contentDir='clips/content/'.Str::uuid();
        $target=storage_path('app/public/'.$contentDir);
        File::ensureDirectoryExists($target);

        if(!$zip->extractTo($target)){
            $zip->close();
            File::deleteDirectory($target);
            return ['error'=>'Не удалось распаковать архив.'];
        }

        $zip->close();

        return [
            'content_dir'=>$contentDir,
            'entry_file'=>$entry,
        ];
    }

    public function delete(Clip $clip)
    {
        if($clip->content_dir)Storage::disk('public')->deleteDirectory($clip->content_dir);
        if($clip->cover_path)Storage::disk('public')->delete($clip->cover_path);
        $clip->delete();

        return back()->with('success','Клип удалён.');
    }
}
