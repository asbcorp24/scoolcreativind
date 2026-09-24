<?php
namespace App\Http\Controllers;

use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\JournalEntry;
use App\Models\ScheduleLesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AcademicController extends Controller
{
    public function dashboard()
    {
        $user=auth()->user();
        $groupIds=$user->studentGroups()->pluck('study_groups.id');

        $lessons=ScheduleLesson::with(['studio','group'])
            ->whereIn('study_group_id',$groupIds)
            ->where('lesson_date','>=',now()->toDateString())
            ->orderBy('lesson_date')->orderBy('starts_at')->take(20)->get();

        $homework=HomeworkAssignment::with(['subject','group','submissions'=>fn($q)=>$q->where('student_id',$user->id)])
            ->whereIn('study_group_id',$groupIds)
            ->where('is_published',true)
            ->orderByRaw('due_at IS NULL, due_at ASC')->get();

        $grades=JournalEntry::with(['lesson.subject','lesson.group'])
            ->where('student_id',$user->id)
            ->latest()->take(30)->get();

        return view('academic.dashboard',compact('lessons','homework','grades'));
    }

    public function homework(HomeworkAssignment $assignment)
    {
        $user=auth()->user();
        abort_unless($user->studentGroups()->where('study_groups.id',$assignment->study_group_id)->exists(),403);

        $assignment->load(['subject','group','teacher']);
        $submission=HomeworkSubmission::firstOrNew([
            'homework_assignment_id'=>$assignment->id,
            'student_id'=>$user->id,
        ]);

        return view('academic.homework',compact('assignment','submission'));
    }

    public function submitHomework(Request $request, HomeworkAssignment $assignment)
    {
        $user=auth()->user();
        abort_unless($user->studentGroups()->where('study_groups.id',$assignment->study_group_id)->exists(),403);

        $data=$request->validate([
            'text_answer'=>'nullable|string|max:20000',
            'external_url'=>'nullable|string|max:2000',
            'file'=>'nullable|file|max:51200',
        ]);

        $submission=HomeworkSubmission::firstOrNew([
            'homework_assignment_id'=>$assignment->id,
            'student_id'=>$user->id,
        ]);

        if($request->hasFile('file')){
            if($submission->file) Storage::disk('public')->delete($submission->file);
            $data['file']=$request->file('file')->store('homework/submissions','public');
        }

        $data['status']='submitted';
        $data['submitted_at']=now();
        $submission->fill($data)->save();

        return back()->with('success','Домашняя работа отправлена.');
    }
}
