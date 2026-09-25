<?php
namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;

class AdminContactController extends Controller
{
    public function edit()
    {
        $settings=SiteSetting::pluck('value','key')->all();
        return view('admin.contacts',compact('settings'));
    }

    public function update(Request $request)
    {
        $data=$request->validate([
            'contact_title'=>'nullable|string|max:255',
            'contact_intro'=>'nullable|string|max:2000',
            'contact_address'=>'nullable|string|max:500',
            'contact_phone'=>'nullable|string|max:120',
            'contact_phone_extra'=>'nullable|string|max:120',
            'contact_email'=>'nullable|email|max:255',
            'contact_work_hours'=>'nullable|string|max:500',
            'contact_vk'=>'nullable|url|max:1000',
            'contact_telegram'=>'nullable|url|max:1000',
            'contact_lat'=>'nullable|numeric|between:-90,90',
            'contact_lng'=>'nullable|numeric|between:-180,180',
            'contact_map_zoom'=>'nullable|integer|min:5|max:19',
            'contact_org_name'=>'nullable|string|max:500',
            'contact_requisites'=>'nullable|string|max:5000',
        ]);

        foreach($data as $key=>$value){
            SiteSetting::setValue($key,$value);
        }

        return back()->with('success','Контактные данные сохранены.');
    }
}
