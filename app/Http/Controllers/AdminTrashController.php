<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\NewsPost;
use App\Models\OfficialDocument;
use App\Models\ScheduleLesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminTrashController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->is_admin,403);

        return view('admin.trash.index',[
            'schedule'=>ScheduleLesson::onlyTrashed()->with(['group','teacher'])->latest('deleted_at')->get(),
            'documents'=>OfficialDocument::onlyTrashed()->latest('deleted_at')->get(),
            'news'=>NewsPost::onlyTrashed()->latest('deleted_at')->get(),
        ]);
    }

    public function restore(Request $request, string $type, int $id)
    {
        abort_unless(auth()->user()->is_admin,403);

        $model=$this->find($type,$id);
        $model->restore();

        ActivityLog::record(
            'trash.restored',
            $model,
            'Восстановлено из корзины: '.$this->label($type,$model)
        );

        return back()->with('success','Запись восстановлена.');
    }

    public function purge(Request $request, string $type, int $id)
    {
        abort_unless(auth()->user()->is_admin,403);

        $model=$this->find($type,$id);
        $label=$this->label($type,$model);

        if($type==='document' && $model->file_path){
            Storage::disk('public')->delete($model->file_path);
        }

        if($type==='news'){
            foreach($model->media as $media){
                if($media->url && !preg_match('~^(https?:)?//~i',$media->url)){
                    Storage::disk('public')->delete($media->url);
                }
                $media->delete();
            }

            if($model->cover && !preg_match('~^(https?:)?//~i',$model->cover)){
                Storage::disk('public')->delete($model->cover);
            }
        }

        ActivityLog::record(
            'trash.purged',
            $model,
            'Окончательно удалено: '.$label,
            $model->toArray(),
            null
        );

        $model->forceDelete();

        return back()->with('success','Запись окончательно удалена.');
    }

    private function find(string $type, int $id)
    {
        return match($type){
            'schedule'=>ScheduleLesson::onlyTrashed()->findOrFail($id),
            'document'=>OfficialDocument::onlyTrashed()->findOrFail($id),
            'news'=>NewsPost::onlyTrashed()->with('media')->findOrFail($id),
            default=>abort(404),
        };
    }

    private function label(string $type, $model): string
    {
        return match($type){
            'schedule'=>$model->title,
            'document'=>$model->title,
            'news'=>$model->title,
            default=>'#'.$model->id,
        };
    }
}
