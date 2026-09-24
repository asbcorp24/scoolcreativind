<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\StudentProject;
use App\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminContentController extends Controller
{
    public function projects()
    {
        return view('admin.projects', [
            'projects'=>StudentProject::with('studio')->latest()->get(),
            'studios'=>Studio::orderBy('sort_order')->get(),
        ]);
    }

    public function saveProject(Request $request, ?StudentProject $project=null)
    {
        $data=$request->validate([
            'studio_id'=>'nullable|exists:studios,id',
            'title'=>'required|string|max:200',
            'author'=>'nullable|string|max:160',
            'year'=>'nullable|string|max:20',
            'description'=>'nullable|string|max:3000',
            'cover'=>'nullable|string|max:2000',
            'project_url'=>'nullable|string|max:2000',
            'is_featured'=>'nullable|boolean',
        ]);
        $data['is_featured']=$request->boolean('is_featured');
        $project ??= new StudentProject();
        $project->fill($data)->save();
        return back()->with('success','Проект сохранён.');
    }

    public function deleteProject(StudentProject $project)
    {
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
