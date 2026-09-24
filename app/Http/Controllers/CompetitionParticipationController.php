<?php
namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionRegistration;
use App\Models\CompetitionDocument;
use App\Services\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
   'document'=>'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
  ],[
   'document.max'=>'Максимальный размер файла — 10 МБ.',
   'document.mimes'=>'Можно загрузить только PDF, JPG, PNG или WEBP.',
  ]);

  $file=$request->file('document');
  $existing=CompetitionDocument::where('competition_registration_id',$registration->id)
    ->where('document_key',$documentKey)
    ->first();

  $directory='competitions/documents/'.$competition->id.'/'.$registration->id;
  $extension=strtolower($file->getClientOriginalExtension());

  if($extension==='pdf'){
   if(!StorageQuota::canStore((int)$file->getSize())){
    return back()->withErrors(['document'=>'Недостаточно выделенного места для документа.']);
   }

   $path=$file->store($directory,'public');
   $storedName=$file->getClientOriginalName();
   $mimeType='application/pdf';
   $storedSize=$file->getSize();
  }else{
   if(!function_exists('imagecreatefromstring')){
    return back()->withErrors(['document'=>'На сервере не включено расширение GD для обработки изображений.']);
   }

   $raw=file_get_contents($file->getRealPath());
   $source=@imagecreatefromstring($raw);
   if(!$source){
    return back()->withErrors(['document'=>'Не удалось прочитать изображение.']);
   }

   $width=imagesx($source);
   $height=imagesy($source);
   $scale=min(1,1024/max($width,$height));
   $newWidth=max(1,(int)round($width*$scale));
   $newHeight=max(1,(int)round($height*$scale));

   $target=imagecreatetruecolor($newWidth,$newHeight);
   $white=imagecolorallocate($target,255,255,255);
   imagefill($target,0,0,$white);
   imagecopyresampled($target,$source,0,0,0,0,$newWidth,$newHeight,$width,$height);

   ob_start();
   imagejpeg($target,null,88);
   $jpegData=ob_get_clean();

   imagedestroy($source);
   imagedestroy($target);

   $storedSize=strlen($jpegData);
   if(!StorageQuota::canStore($storedSize)){
    return back()->withErrors(['document'=>'Недостаточно выделенного места для обработанного изображения.']);
   }

   $base=pathinfo($file->getClientOriginalName(),PATHINFO_FILENAME);
   $storedName=$base.'.jpg';
   $path=$directory.'/'.Str::uuid().'.jpg';
   Storage::disk('public')->put($path,$jpegData);
   $mimeType='image/jpeg';
  }

  if($existing?->file_path){
   Storage::disk('public')->delete($existing->file_path);
  }

  CompetitionDocument::updateOrCreate(
   [
    'competition_registration_id'=>$registration->id,
    'document_key'=>$documentKey,
   ],
   [
    'document_label'=>$requirement['label'],
    'file_path'=>$path,
    'file_name'=>$storedName,
    'mime_type'=>$mimeType,
    'file_size'=>$storedSize,
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
