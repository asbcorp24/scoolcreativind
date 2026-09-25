<?php
namespace App\Http\Controllers;

use App\Models\OfficialDocument;
use App\Services\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'category'=>'nullable|string|max:180',
            'document_number'=>'nullable|string|max:120',
            'document_date'=>'nullable|date',
            'description'=>'nullable|string|max:5000',
            'document_file'=>[$document ? 'nullable' : 'required','file','max:20480','mimes:pdf'],
            'sort_order'=>'nullable|integer|min:0|max:999999',
            'is_published'=>'nullable|boolean',
        ],[
            'document_file.required'=>'Выберите PDF-файл.',
            'document_file.max'=>'Максимальный размер файла — 20 МБ.',
            'document_file.mimes'=>'Для официальных документов разрешён только PDF.',
        ]);

        $document ??= new OfficialDocument();

        if($request->hasFile('document_file')){
            $file=$request->file('document_file');

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
        $data['sort_order']=$data['sort_order'] ?? 0;
        $data['is_published']=$request->boolean('is_published');

        $document->fill($data)->save();

        return redirect()->route('admin.documents')->with('success','Документ сохранён.');
    }

    public function delete(OfficialDocument $document)
    {
        if($document->file_path){
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return back()->with('success','Документ удалён.');
    }
}
