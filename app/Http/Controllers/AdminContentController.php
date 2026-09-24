<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\PortfolioItem;
use App\Models\StudentProfile;
use App\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminContentController extends Controller
{
    public function projects()
    {
        return view('admin.projects', [
            'projects'=>PortfolioItem::with(['student.user','studio'])->latest()->get(),
            'profiles'=>StudentProfile::with('user')->orderByDesc('id')->get(),
            'studios'=>Studio::orderBy('sort_order')->get(),
        ]);
    }

    public function saveProject(Request $request, ?PortfolioItem $project=null)
    {
        $data=$request->validate([
            'student_profile_id'=>'required|exists:student_profiles,id',
            'studio_id'=>'nullable|exists:studios,id',
            'title'=>'required|string|max:220',
            'type'=>'required|string|max:80',
            'description'=>'nullable|string|max:10000',
            'cover'=>'nullable|image|max:10240',
            'project_file'=>'nullable|file|max:102400',
            'project_url'=>'nullable|string|max:2000',
            'video_url'=>'nullable|string|max:2000',
            'completed_at'=>'nullable|date',
            'is_public'=>'nullable|boolean',
            'is_featured'=>'nullable|boolean',
        ]);

        $project ??= new PortfolioItem();

        if($request->hasFile('cover')){
            if($project->cover && !preg_match('~^(https?:)?//~i',$project->cover)){
                Storage::disk('public')->delete($project->cover);
            }
            $data['cover']=$request->file('cover')->store('projects/covers','public');
        }

        if($request->hasFile('project_file')){
            if($project->file_path) Storage::disk('public')->delete($project->file_path);
            $file=$request->file('project_file');
            $data['file_path']=$file->store('projects/files','public');
            $data['file_name']=$file->getClientOriginalName();
            $data['mime_type']=$file->getMimeType();
            $data['file_size']=$file->getSize();
        }

        unset($data['project_file']);
        $data['is_public']=$request->boolean('is_public');
        $data['is_featured']=$request->boolean('is_featured');
        $project->fill($data)->save();

        return back()->with('success','Проект / работа сохранён.');
    }

    public function deleteProject(PortfolioItem $project)
    {
        if($project->cover && !preg_match('~^(https?:)?//~i',$project->cover)){
            Storage::disk('public')->delete($project->cover);
        }
        if($project->file_path) Storage::disk('public')->delete($project->file_path);
        $project->delete();
        return back()->with('success','Проект удалён.');
    }

    public function events()
    {
        return view('admin.events', ['events'=>Event::orderByDesc('starts_at')->get()]);
    }

    public function saveEvent(Request $request, ?Event $event=null)
    {
        $data=$request->validate([
            'title'=>'required|string|max:220',
            'slug'=>'nullable|string|max:220',
            'description'=>'nullable|string|max:5000',
            'starts_at'=>'required|date',
            'location'=>'nullable|string|max:255',
            'cover'=>'nullable|string|max:2000',
            'registration_url'=>'nullable|string|max:2000',
            'is_published'=>'nullable|boolean',
        ]);
        $event ??= new Event();
        $data['slug']=$data['slug'] ?: Str::slug($data['title']);
        $data['is_published']=$request->boolean('is_published');
        $event->fill($data)->save();
        return back()->with('success','Событие сохранено.');
    }

    public function deleteEvent(Event $event)
    {
        $event->delete();
        return back()->with('success','Событие удалено.');
    }
}
