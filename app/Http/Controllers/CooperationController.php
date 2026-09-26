<?php
namespace App\Http\Controllers;

use App\Models\CooperationApplication;
use App\Models\CooperationItem;
use Illuminate\Http\Request;

class CooperationController extends Controller
{
    public function index()
    {
        $items=CooperationItem::where('is_published',true)->orderBy('sort_order')->orderBy('id')->get();
        return view('cooperation.index',[
            'proposals'=>$items->where('type','proposal'),
            'partners'=>$items->where('type','partner'),
            'projects'=>$items->where('type','project'),
        ]);
    }

    public function store(Request $request)
    {
        $data=$request->validate([
            'role'=>'required|in:partner,curator,teacher,other',
            'name'=>'required|string|max:180',
            'organization'=>'nullable|string|max:255',
            'phone'=>'nullable|string|max:80',
            'email'=>'nullable|email|max:255',
            'website'=>'nullable|url|max:2000',
            'message'=>'nullable|string|max:5000',
        ]);

        if(!$request->filled('phone') && !$request->filled('email')){
            return back()->withErrors(['email'=>'Укажите телефон или email для связи.'])->withInput();
        }

        CooperationApplication::create($data);
        return redirect()->route('cooperation.index')->with('success','Заявка отправлена. Мы свяжемся с вами.');
    }
}
