<?php
namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionRegistration;
use App\Models\CompetitionDocument;
use App\Services\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompetitionParticipationController extends Controller
{
 public function register(Competition $competition){
  abort_unless($competition->is_published,404);
  CompetitionRegistration::firstOrCreate([
   'competition_id'=>$competition->id,
   'user_id'=>auth()->id(),
  ],['status'=>'registered']);

  return back()->with('success','Вы записались на конкурс.');
 }

 public function submit(Request $request, Competition $competition){
  abort_unless($competition->is_published,404);
  $registration=CompetitionRegistration::where('competition_id',$competition->id)
    ->where('user_id',auth()->id())->firstOrFail();

  $data=$request->validate([
   'submission_text'=>'nullable|string|max:10000',
   'submission_url'=>'nullable|url|max:2000',
   'submission_file'=>'nullable|file|max:102400',
  ]);

  if(!$request->filled('submission_text') && !$request->filled('submission_url') && !$request->hasFile('submission_file')){
   return back()->withErrors(['submission_text'=>'Добавьте описание, ссылку или файл конкурсной работы.']);
  }

  if($request->hasFile('submission_file')){
   $file=$request->file('submission_file');
   if(!StorageQuota::canStore((int)$file->getSize())){
    return back()->withErrors(['submission_file'=>'Недостаточно выделенного места для файла.']);
   }
   if($registration->file_path) Storage::disk('public')->delete($registration->file_path);
   $data['file_path']=$file->store('competitions/submissions','public');
   $data['file_name']=$file->getClientOriginalName();
  }

  unset($data['submission_file']);
  $data['status']='submitted';
  $data['submitted_at']=now();
  $registration->update($data);

  return back()->with('success','Конкурсная работа отправлена.');
 }


 public function uploadDocument(Request $request, Competition $competition, string $documentKey){
  abort_unless($competition->is_published,404);

  $registration=CompetitionRegistration::where('competition_id',$competition->id)
    ->where('user_id',auth()->id())
    ->firstOrFail();

  $requirements=collect($competition->required_documents_json ?: []);
  $requirement=$requirements->firstWhere('key',$documentKey);
  abort_unless($requirement,404);

  $request->validate([
   'document'=>'required|file|max:20480|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip',
  ]);

  $file=$request->file('document');

  if(!StorageQuota::canStore((int)$file->getSize())){
   return back()->withErrors(['document'=>'Недостаточно выделенного места для документа.']);
  }

  $existing=CompetitionDocument::where('competition_registration_id',$registration->id)
    ->where('document_key',$documentKey)
    ->first();

  if($existing?->file_path){
   Storage::disk('public')->delete($existing->file_path);
  }

  $path=$file->store('competitions/documents/'.$competition->id.'/'.$registration->id,'public');

  CompetitionDocument::updateOrCreate(
   [
    'competition_registration_id'=>$registration->id,
    'document_key'=>$documentKey,
   ],
   [
    'document_label'=>$requirement['label'],
    'file_path'=>$path,
    'file_name'=>$file->getClientOriginalName(),
    'mime_type'=>$file->getMimeType(),
    'file_size'=>$file->getSize(),
   ]
  );

  return back()->with('success','Документ «'.$requirement['label'].'» загружен.');
 }

 public function deleteDocument(Competition $competition, string $documentKey){
  $registration=CompetitionRegistration::where('competition_id',$competition->id)
    ->where('user_id',auth()->id())
    ->firstOrFail();

  $document=CompetitionDocument::where('competition_registration_id',$registration->id)
    ->where('document_key',$documentKey)
    ->firstOrFail();

  if($document->file_path) Storage::disk('public')->delete($document->file_path);
  $document->delete();

  return back()->with('success','Документ удалён.');
 }

 public function cancel(Competition $competition){
  CompetitionRegistration::where('competition_id',$competition->id)
    ->where('user_id',auth()->id())
    ->where('status','registered')
    ->delete();

  return back()->with('success','Запись на конкурс отменена.');
 }
}
