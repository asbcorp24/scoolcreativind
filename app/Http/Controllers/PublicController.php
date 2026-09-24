<?php
namespace App\Http\Controllers;

use App\Models\AdmissionApplication;
use App\Models\Event;
use App\Models\MediaItem;
use App\Models\NewsPost;
use App\Models\StudentProject;
use App\Models\Studio;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function home()
    {
        return view('home', [
            'studios' => Studio::where('is_active', true)->orderBy('sort_order')->get(),
            'featuredMedia' => MediaItem::where('is_featured', true)->latest()->take(12)->get(),
            'projects' => StudentProject::where('is_featured', true)->latest()->take(8)->get(),
            'news' => NewsPost::where('is_published', true)->latest('published_at')->take(6)->get(),
            'events' => Event::where('is_published', true)->where('starts_at','>=',now()->subDay())->orderBy('starts_at')->take(4)->get(),
        ]);
    }

    public function studio(Studio $studio)
    {
        abort_unless($studio->is_active, 404);
        $studio->load('media','projects');
        return view('studio', compact('studio'));
    }

    public function news()
    {
        return view('news.index', ['posts'=>NewsPost::where('is_published',true)->latest('published_at')->paginate(9)]);
    }

    public function newsShow(NewsPost $post)
    {
        abort_unless($post->is_published,404);
        return view('news.show', compact('post'));
    }

    public function apply()
    {
        return view('apply', ['studios'=>Studio::where('is_active',true)->orderBy('sort_order')->get()]);
    }

    public function storeApplication(Request $request)
    {
        $data=$request->validate([
            'name'=>'required|string|max:160','birth_date'=>'nullable|date','phone'=>'required|string|max:40',
            'email'=>'nullable|email|max:160','studio_id'=>'nullable|exists:studios,id','message'=>'nullable|string|max:3000'
        ]);
        $data['user_id']=auth()->id();
        AdmissionApplication::create($data);
        return back()->with('success','Заявка отправлена. Мы свяжемся с вами после обработки.');
    }

    public function cabinet()
    {
        return view('cabinet', ['applications'=>AdmissionApplication::where('user_id',auth()->id())->latest()->get()]);
    }
}
