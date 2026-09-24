<?php
namespace App\Http\Controllers;

use App\Models\EquipmentItem;
use App\Models\Studio;
use App\Models\TeamMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminPeopleEquipmentController extends Controller
{
    public function team()
    {
        return view('admin.team', [
            'members'=>TeamMember::with('studio')->orderBy('sort_order')->get(),
            'studios'=>Studio::orderBy('sort_order')->get(),
        ]);
    }

    public function saveTeam(Request $request, ?TeamMember $member=null)
    {
        $data=$request->validate([
            'studio_id'=>'nullable|exists:studios,id',
            'name'=>'required|string|max:180',
            'role'=>'nullable|string|max:180',
            'bio'=>'nullable|string|max:5000',
            'photo'=>'nullable|image|max:10240',
            'email'=>'nullable|email|max:180',
            'vk_url'=>'nullable|string|max:1000',
            'telegram_url'=>'nullable|string|max:1000',
            'sort_order'=>'nullable|integer|min:0',
            'is_active'=>'nullable|boolean',
        ]);

        $member ??= new TeamMember();

        if ($request->hasFile('photo')) {
            if ($member->photo && !preg_match('~^(https?:)?//~i',$member->photo)) {
                Storage::disk('public')->delete($member->photo);
            }
            $data['photo']=$request->file('photo')->store('team','public');
        }

        $data['is_active']=$request->boolean('is_active');
        $member->fill($data)->save();

        return back()->with('success','Сотрудник сохранён.');
    }

    public function deleteTeam(TeamMember $member)
    {
        if ($member->photo && !preg_match('~^(https?:)?//~i',$member->photo)) {
            Storage::disk('public')->delete($member->photo);
        }
        $member->delete();
        return back()->with('success','Сотрудник удалён.');
    }

    public function equipment()
    {
        return view('admin.equipment', [
            'items'=>EquipmentItem::with('studio')->orderBy('sort_order')->get(),
            'studios'=>Studio::orderBy('sort_order')->get(),
        ]);
    }

    public function saveEquipment(Request $request, ?EquipmentItem $item=null)
    {
        $data=$request->validate([
            'studio_id'=>'nullable|exists:studios,id',
            'title'=>'required|string|max:220',
            'category'=>'nullable|string|max:120',
            'brand'=>'nullable|string|max:120',
            'model'=>'nullable|string|max:160',
            'description'=>'nullable|string|max:5000',
            'image'=>'nullable|image|max:10240',
            'specs_json'=>'nullable|json',
            'sort_order'=>'nullable|integer|min:0',
            'is_featured'=>'nullable|boolean',
        ]);

        $item ??= new EquipmentItem();

        if ($request->hasFile('image')) {
            if ($item->image && !preg_match('~^(https?:)?//~i',$item->image)) {
                Storage::disk('public')->delete($item->image);
            }
            $data['image']=$request->file('image')->store('equipment','public');
        }

        $data['specs']=$request->filled('specs_json') ? json_decode($request->input('specs_json'),true) : null;
        unset($data['specs_json']);
        $data['is_featured']=$request->boolean('is_featured');

        $item->fill($data)->save();

        return back()->with('success','Оборудование сохранено.');
    }

    public function deleteEquipment(EquipmentItem $item)
    {
        if ($item->image && !preg_match('~^(https?:)?//~i',$item->image)) {
            Storage::disk('public')->delete($item->image);
        }
        $item->delete();
        return back()->with('success','Оборудование удалено.');
    }
}
