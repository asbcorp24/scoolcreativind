<?php
namespace App\Http\Controllers;

use App\Models\AdmissionApplication;
use App\Models\MediaItem;
use App\Models\NewsPost;
use App\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', [
            'studios'=>Studio::orderBy('sort_order')->get(),
            'news'=>NewsPost::latest()->take(8)->get(),
            'applications'=>AdmissionApplication::with('studio')->latest()->take(12)->get(),
        ]);
    }

    public function studioForm(?Studio $studio=null)
    {
        return view('admin.studio-form', compact('studio'));
    }

    public function saveStudio(Request $request, ?Studio $studio=null)
    {
        $data=$request->validate([
            'title'=>'required|string|max:180','slug'=>'nullable|string|max:180','subtitle'=>'nullable|string|max:255',
            'description'=>'nullable|string','icon'=>'nullable|string|max:80','accent'=>'nullable|string|max:40',
            'cover'=>'nullable|image|max:10240','sort_order'=>'nullable|integer|min:0','is_active'=>'nullable|boolean'
        ]);
        $studio ??= new Studio();
        $data['slug']=$data['slug'] ?: Str::slug($data['title']);
        $data['is_active']=$request->boolean('is_active');
        if ($request->hasFile('cover')) {
            if ($studio->cover) Storage::disk('public')->delete($studio->cover);
            $data['cover']=$request->file('cover')->store('studios','public');
        }
        $studio->fill($data)->save();
        return redirect()->route('admin.dashboard')->with('success','Раздел сохранён.');
    }

    public function deleteStudio(Studio $studio)
    {
        $studio->delete();
        return back()->with('success','Раздел удалён.');
    }

    public function mediaForm(Studio $studio){ return view('admin.media-form', compact('studio')); }

    public function addMedia(Request $request, Studio $studio)
    {
        $data=$request->validate([
            'type'=>'required|in:photo,panorama,video,model','title'=>'nullable|string|max:180','url'=>'required|string|max:2000',
            'thumbnail'=>'nullable|string|max:2000','caption'=>'nullable|string|max:2000','hotspots_json'=>'nullable|json','sort_order'=>'nullable|integer|min:0','is_featured'=>'nullable|boolean'
        ]);
        $data['is_featured']=$request->boolean('is_featured');
        $studio->media()->create($data);
        return back()->with('success','Медиа добавлено.');
    }

    public function deleteMedia(MediaItem $media){ $media->delete(); return back()->with('success','Медиа удалено.'); }

    public function newsForm(?NewsPost $post=null){ return view('admin.news-form', compact('post')); }

    public function saveNews(Request $request, ?NewsPost $post=null)
    {
        $data=$request->validate([
            'title'=>'required|string|max:220','slug'=>'nullable|string|max:220','excerpt'=>'nullable|string|max:1000',
            'body'=>'required|string','cover'=>'nullable|string|max:2000','published_at'=>'nullable|date','is_published'=>'nullable|boolean'
        ]);
        $post ??= new NewsPost();
        $data['slug']=$data['slug'] ?: Str::slug($data['title']);
        $data['is_published']=$request->boolean('is_published');
        $post->fill($data)->save();
        return redirect()->route('admin.dashboard')->with('success','Новость сохранена.');
    }

    public function applicationStatus(Request $request, AdmissionApplication $application)
    {
        $data=$request->validate(['status'=>'required|in:new,processing,accepted,rejected']);
        $application->update($data);
        return back()->with('success','Статус заявки изменён.');
    }
}
