<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\OfficialDocument;
use App\Services\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminDocumentController extends Controller
{
    public function index()
    {
        return view('admin.documents',[
            'documents'=>OfficialDocument::orderBy('sort_order')->orderByDesc('document_date')->orderByDesc('id')->get(),
        ]);
    }

    public function edit(OfficialDocument $document)
    {
        return view('admin.documents',[
            'documents'=>OfficialDocument::orderBy('sort_order')->orderByDesc('document_date')->orderByDesc('id')->get(),
            'editDocument'=>$document,
        ]);
    }

    public function save(Request $request, ?OfficialDocument $document=null)
    {
        $data=$request->validate([
            'title'=>'required|string|max:255',
            'item_type'=>'required|in:document,file,application,link',
            'category'=>'nullable|string|max:180',
            'document_number'=>'nullable|string|max:120',
            'document_date'=>'nullable|date',
            'description'=>'nullable|string|max:5000',
            'document_file'=>'nullable|file|max:204800',
            'external_url'=>'nullable|url|max:2000',
            'button_label'=>'nullable|string|max:80',
            'sort_order'=>'nullable|integer|min:0|max:999999',
            'is_published'=>'nullable|boolean',
        ],[
            'document_file.max'=>'Максимальный размер файла — 200 МБ.',
            'external_url.url'=>'Укажите полный адрес ссылки, например https://example.com.',
        ]);

        $document ??= new OfficialDocument();

        if(!$request->hasFile('document_file') && !$request->filled('external_url') && !$document->file_path){
            throw ValidationException::withMessages([
                'document_file'=>'Загрузите файл или укажите внешнюю ссылку.',
            ]);
        }

        if($request->hasFile('document_file')){
            $file=$request->file('document_file');
            $allowed=['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','rtf','odt','ods','zip','rar','7z','exe','msi','apk','dmg','pkg','deb','rpm','iso','jpg','jpeg','png','webp','svg','mp3','wav','ogg','mp4','webm'];
            $extension=strtolower($file->getClientOriginalExtension());

            if(!in_array($extension,$allowed,true)){
                throw ValidationException::withMessages([
                    'document_file'=>'Этот тип файла не разрешён. Разрешены документы, архивы, установщики, APK, изображения, аудио и видео.',
                ]);
            }

            if(!StorageQuota::canStore((int)$file->getSize())){
                return back()->withErrors(['document_file'=>'Недостаточно выделенного места в хранилище.'])->withInput();
            }

            if($document->file_path){
                Storage::disk('public')->delete($document->file_path);
            }

            $data['file_path']=$file->store('documents','public');
            $data['file_name']=$file->getClientOriginalName();
            $data['file_size']=$file->getSize();
        }

        unset($data['document_file']);
        $data['external_url']=$request->filled('external_url') ? $request->input('external_url') : null;
        $data['sort_order']=$data['sort_order'] ?? 0;
        $data['is_published']=$request->boolean('is_published');

        $document->fill($data)->save();

        return redirect()->route('admin.documents')->with('success','Материал сохранён.');
    }

    public function delete(OfficialDocument $document)
    {
        ActivityLog::record(
            'document.trashed',
            $document,
            'Материал перемещён в корзину: '.$document->title,
            $document->toArray(),
            null
        );

        $document->delete();

        return back()->with('success','Материал перемещён в корзину.');
    }
}
