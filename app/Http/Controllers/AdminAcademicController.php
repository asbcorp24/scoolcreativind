<?php
namespace App\Http\Controllers;

use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\JournalEntry;
use App\Models\JournalLesson;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminAcademicController extends Controller
{
    private function allowedGroupIds()
    {
        if (auth()->user()->is_admin) return StudyGroup::pluck('id');
        return auth()->user()->teacherGroups()->pluck('study_groups.id');
    }

    private function ensureGroupAllowed(int $groupId): void
    {
        abort_unless($this->allowedGroupIds()->contains($groupId),403);
    }

    public function groups()
    {
        return view('admin.academic.groups',[
            'groups'=>StudyGroup::with(['studio','students','teachers','subjects'])->whereIn('id',$this->allowedGroupIds())->orderBy('study_year')->orderBy('name')->get(),
            'users'=>User::orderBy('name')->get(),
            'subjects'=>Subject::orderBy('title')->get(),
        ]);
    }

    public function saveGroup(Request $request, ?StudyGroup $group=null)
    {
        $data=$request->validate([
            'name'=>'required|string|max:180',
            'study_year'=>'required|integer|min:1|max:2',
            'code'=>'required|string|max:80|unique:study_groups,code,'.optional($group)->id,
            'studio_id'=>'nullable|exists:studios,id',
            'curator_name'=>'nullable|string|max:180',
            'is_active'=>'nullable|boolean',
        ]);

        $group ??= new StudyGroup();
        $data['is_active']=$request->boolean('is_active');
        $group->fill($data)->save();
        return back()->with('success','Учебная группа сохранена.');
    }

    public function addMember(Request $request, StudyGroup $group)
    {
        $data=$request->validate([
            'user_id'=>'required|exists:users,id',
            'role'=>'required|in:student,teacher',
        ]);

        abort_unless(auth()->user()->is_admin,403);
        $group->users()->syncWithoutDetaching([
            $data['user_id']=>['role'=>$data['role']]
        ]);

        return back()->with('success','Пользователь добавлен в группу.');
    }

    public function removeMember(StudyGroup $group, User $user, string $role)
    {
        abort_unless(auth()->user()->is_admin,403);
        $group->users()->wherePivot('role',$role)->detach($user->id);
        return back()->with('success','Пользователь удалён из группы.');
    }

    public function subjects()
    {
        return view('admin.academic.subjects',[
            'subjects'=>Subject::with('studio')->orderBy('title')->get(),
            'groups'=>StudyGroup::where('is_active',true)->whereIn('id',$this->allowedGroupIds())->orderBy('name')->get(),
            'teachers'=>User::whereHas('studyGroups',fn($q)=>$q->where('role','teacher'))->orderBy('name')->get(),
        ]);
    }

    public function saveSubject(Request $request, ?Subject $subject=null)
    {
        $data=$request->validate([
            'title'=>'required|string|max:180',
            'studio_id'=>'nullable|exists:studios,id',
            'description'=>'nullable|string|max:1000',
        ]);
        $subject ??= new Subject();
        $subject->fill($data)->save();
        return back()->with('success','Предмет сохранён.');
    }

    public function attachSubject(Request $request, StudyGroup $group)
    {
        $data=$request->validate([
            'subject_id'=>'required|exists:subjects,id',
            'teacher_id'=>'nullable|exists:users,id',
        ]);
        abort_unless(auth()->user()->is_admin,403);
        $group->subjects()->syncWithoutDetaching([
            $data['subject_id']=>['teacher_id'=>$data['teacher_id'] ?? null]
        ]);
        return back()->with('success','Предмет закреплён за группой.');
    }

    public function journal(Request $request)
    {
        $groupId=$request->integer('group_id');
        $subjectId=$request->integer('subject_id');

        $groups=StudyGroup::with('subjects')->whereIn('id',$this->allowedGroupIds())->orderBy('name')->get();
        if($groupId) $this->ensureGroupAllowed($groupId);
        $group=$groupId ? StudyGroup::with('students')->find($groupId) : null;
        $subject=$subjectId ? Subject::find($subjectId) : null;

        $lessons=collect();
        if($group && $subject){
            $lessons=JournalLesson::with('entries')->where('study_group_id',$group->id)
                ->where('subject_id',$subject->id)->orderBy('lesson_date')->get();
        }

        return view('admin.academic.journal',compact('groups','group','subject','lessons'));
    }

    public function createJournalLesson(Request $request)
    {
        $data=$request->validate([
            'study_group_id'=>'required|exists:study_groups,id',
            'subject_id'=>'required|exists:subjects,id',
            'lesson_date'=>'required|date',
            'topic'=>'required|string|max:255',
            'notes'=>'nullable|string|max:5000',
        ]);

        $this->ensureGroupAllowed((int)$data['study_group_id']);
        $data['teacher_id']=auth()->id();
        $lesson=JournalLesson::create($data);

        $students=StudyGroup::findOrFail($data['study_group_id'])->students;
        foreach($students as $student){
            JournalEntry::firstOrCreate([
                'journal_lesson_id'=>$lesson->id,
                'student_id'=>$student->id,
            ],['attendance'=>'present']);
        }

        return back()->with('success','Урок добавлен в журнал.');
    }

    public function saveJournalEntry(Request $request, JournalEntry $entry)
    {
        $data=$request->validate([
            'attendance'=>'required|in:present,absent,late,excused',
            'grade'=>'nullable|numeric|min:1|max:100',
            'grade_label'=>'nullable|string|max:30',
            'comment'=>'nullable|string|max:1000',
        ]);
        $entry->load('lesson');
        $this->ensureGroupAllowed((int)$entry->lesson->study_group_id);
        $entry->update($data);
        return back()->with('success','Запись журнала сохранена.');
    }

    public function homework()
    {
        return view('admin.academic.homework',[
            'assignments'=>HomeworkAssignment::with(['group','subject','submissions'])->whereIn('study_group_id',$this->allowedGroupIds())->latest()->get(),
            'groups'=>StudyGroup::with('subjects')->where('is_active',true)->whereIn('id',$this->allowedGroupIds())->orderBy('name')->get(),
            'subjects'=>Subject::orderBy('title')->get(),
        ]);
    }

    public function saveHomework(Request $request, ?HomeworkAssignment $assignment=null)
    {
        $data=$request->validate([
            'study_group_id'=>'required|exists:study_groups,id',
            'subject_id'=>'required|exists:subjects,id',
            'title'=>'required|string|max:255',
            'description'=>'nullable|string|max:20000',
            'due_at'=>'nullable|date',
            'attachment'=>'nullable|file|max:51200',
            'external_url'=>'nullable|string|max:2000',
            'max_score'=>'required|integer|min:1|max:100',
            'is_published'=>'nullable|boolean',
        ]);

        $this->ensureGroupAllowed((int)$data['study_group_id']);
        $assignment ??= new HomeworkAssignment();
        if($request->hasFile('attachment')){
            if($assignment->attachment) Storage::disk('public')->delete($assignment->attachment);
            $data['attachment']=$request->file('attachment')->store('homework/assignments','public');
        }
        $data['teacher_id']=auth()->id();
        $data['is_published']=$request->boolean('is_published');
        $assignment->fill($data)->save();

        return back()->with('success','Домашнее задание сохранено.');
    }

    public function submissions(HomeworkAssignment $assignment)
    {
        $this->ensureGroupAllowed((int)$assignment->study_group_id);
        $assignment->load(['group','subject','submissions.student']);
        return view('admin.academic.submissions',compact('assignment'));
    }

    public function reviewSubmission(Request $request, HomeworkSubmission $submission)
    {
        $submission->load('assignment');
        $this->ensureGroupAllowed((int)$submission->assignment->study_group_id);
        $data=$request->validate([
            'score'=>'nullable|numeric|min:0',
            'teacher_comment'=>'nullable|string|max:5000',
            'status'=>'required|in:reviewed,returned',
        ]);

        $data['reviewed_at']=now();
        $submission->update($data);

        return back()->with('success','Работа проверена.');
    }
}
