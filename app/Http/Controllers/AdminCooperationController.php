<?php
namespace App\Http\Controllers;

use App\Models\CooperationApplication;
use App\Models\CooperationItem;
use App\Services\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminCooperationController extends Controller
{
    public function index()
    {
        return view('admin.cooperation',[
            'items'=>CooperationItem::orderBy('type')->orderBy('sort_order')->orderBy('id')->get(),
            'applications'=>CooperationApplication::latest()->take(200)->get(),
        ]);
    }

    public function saveItem(Request $request, ?CooperationItem $item=null)
    {
        $data=$request->validate([
            'type'=>'required|in:proposal,partner,project',
            'title'=>'required|string|max:255',
            'description'=>'nullable|string|max:5000',
            'url'=>'nullable|url|max:2000',
            'image'=>'nullable|image|max:10240|mimes:jpg,jpeg,png,webp',
            'sort_order'=>'nullable|integer|min:0|max:999999',
            'is_published'=>'nullable|boolean',
        ]);

        $item ??= new CooperationItem();

        if($request->hasFile('image')){
            $file=$request->file('image');
            if(!StorageQuota::canStore((int)$file->getSize())){
                return back()->withErrors(['image'=>'Недостаточно места в хранилище.'])->withInput();
            }
            if($item->image_path)Storage::disk('public')->delete($item->image_path);
            $data['image_path']=$file->store('cooperation','public');
        }

        unset($data['image']);
        $data['sort_order']=$data['sort_order'] ?? 0;
        $data['is_published']=$request->boolean('is_published');
        $item->fill($data)->save();

        return back()->with('success','Материал сотрудничества сохранён.');
    }

    public function deleteItem(CooperationItem $item)
    {
        if($item->image_path)Storage::disk('public')->delete($item->image_path);
        $item->delete();
        return back()->with('success','Материал удалён.');
    }

    public function updateApplication(Request $request, CooperationApplication $application)
    {
        $data=$request->validate(['status'=>'required|in:new,processing,accepted,rejected']);
        $application->update($data);
        return back()->with('success','Статус заявки обновлён.');
    }

    public function deleteApplication(CooperationApplication $application)
    {
        $application->delete();
        return back()->with('success','Заявка удалена.');
    }
}
