<?php
namespace App\Http\Controllers;

use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminTeacherController extends Controller
{
    private function ensureSuperAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin,403);
    }

    public function index(?User $teacher=null)
    {
        $this->ensureSuperAdmin();

        if($teacher){
            abort_unless($this->isTeacher($teacher),404);
        }

        return view('admin.teachers',[
            'teachers'=>$this->teachersQuery()
                ->with(['teacherGroups'=>fn($q)=>$q->orderBy('study_groups.name')])
                ->orderBy('name')
                ->get(),
            'groups'=>StudyGroup::with(['subjects'=>fn($q)=>$q->orderBy('subjects.title')])
                ->where('is_active',true)
                ->orderBy('study_year')
                ->orderBy('name')
                ->get(),
            'editTeacher'=>$teacher,
            'assignedSubjects'=>$teacher ? DB::table('group_subjects')
                ->where('teacher_id',$teacher->id)
                ->get()
                ->mapWithKeys(fn($row)=>[$row->study_group_id.':'.$row->subject_id=>true])
                ->all() : [],
        ]);
    }

    private function teachersQuery()
    {
        return User::query()->where(function($q){
            $q->whereHas('teacherGroups')
              ->orWhereIn('id',function($sub){
                  $sub->select('teacher_id')
                      ->from('group_subjects')
                      ->whereNotNull('teacher_id');
              });
        });
    }

    private function isTeacher(User $user): bool
    {
        return $user->teacherGroups()->exists()
            || DB::table('group_subjects')->where('teacher_id',$user->id)->exists();
    }

    public function save(Request $request, ?User $teacher=null)
    {
        $this->ensureSuperAdmin();

        if($teacher){
            abort_unless($this->isTeacher($teacher),404);
        }

        $data=$request->validate([
            'name'=>'required|string|max:180',
            'email'=>'required|email|max:255|unique:users,email,'.($teacher?->id ?? 'NULL'),
            'phone'=>'nullable|string|max:80',
            'password'=>$teacher ? 'nullable|string|min:8|max:255' : 'nullable|string|min:8|max:255',
            'groups'=>'nullable|array',
            'groups.*'=>'integer|exists:study_groups,id',
            'subjects'=>'nullable|array',
            'subjects.*'=>'string|regex:/^\\d+:\\d+$/',
        ]);

        $generatedPassword=null;

        DB::transaction(function() use($data,&$teacher,&$generatedPassword){
            if(!$teacher){
                $teacher=new User();
                $teacher->is_admin=false;
                $teacher->admin_sections=null;
            }

            $teacher->name=$data['name'];
            $teacher->email=$data['email'];
            $teacher->phone=$data['phone'] ?? null;

            $password=$data['password'] ?? null;
            if(!$teacher->exists && !$password){
                $password=Str::password(12);
                $generatedPassword=$password;
            }

            if($password){
                $teacher->password=Hash::make($password);
                if(!$generatedPassword && !$teacher->exists){
                    $generatedPassword=$password;
                }
            }

            $teacher->save();

            // Обновляем только роль teacher, не затрагивая возможную роль student.
            DB::table('study_group_user')
                ->where('user_id',$teacher->id)
                ->where('role','teacher')
                ->delete();

            $groupIds=collect($data['groups'] ?? [])->map(fn($id)=>(int)$id)->unique();
            foreach($groupIds as $groupId){
                DB::table('study_group_user')->insert([
                    'study_group_id'=>$groupId,
                    'user_id'=>$teacher->id,
                    'role'=>'teacher',
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]);
            }

            // Сначала снимаем старые назначения предметов этого преподавателя.
            DB::table('group_subjects')
                ->where('teacher_id',$teacher->id)
                ->update(['teacher_id'=>null,'updated_at'=>now()]);

            foreach($data['subjects'] ?? [] as $pair){
                [$groupId,$subjectId]=array_map('intval',explode(':',$pair,2));

                // Назначаем только существующую связку предмета с группой.
                DB::table('group_subjects')
                    ->where('study_group_id',$groupId)
                    ->where('subject_id',$subjectId)
                    ->update([
                        'teacher_id'=>$teacher->id,
                        'updated_at'=>now(),
                    ]);

                // Если выбран предмет группы, преподаватель обязательно состоит в этой группе.
                $exists=DB::table('study_group_user')
                    ->where('study_group_id',$groupId)
                    ->where('user_id',$teacher->id)
                    ->where('role','teacher')
                    ->exists();

                if(!$exists){
                    DB::table('study_group_user')->insert([
                        'study_group_id'=>$groupId,
                        'user_id'=>$teacher->id,
                        'role'=>'teacher',
                        'created_at'=>now(),
                        'updated_at'=>now(),
                    ]);
                }
            }
        });

        $response=redirect()->route('admin.teachers')->with('success','Преподаватель сохранён.');

        if($generatedPassword){
            $response->with('teacher_credentials',[
                'name'=>$teacher->name,
                'login'=>$teacher->email,
                'password'=>$generatedPassword,
            ]);
        }

        return $response;
    }

    public function edit(User $teacher)
    {
        return $this->index($teacher);
    }

    public function resetPassword(Request $request, User $teacher)
    {
        $this->ensureSuperAdmin();
        abort_unless($this->isTeacher($teacher),404);

        $data=$request->validate([
            'password'=>'nullable|string|min:8|max:255',
        ]);

        $password=$data['password'] ?? Str::password(12);
        $teacher->update(['password'=>Hash::make($password)]);

        return back()
            ->with('success','Пароль преподавателя изменён.')
            ->with('teacher_credentials',[
                'name'=>$teacher->name,
                'login'=>$teacher->email,
                'password'=>$password,
            ]);
    }

    public function remove(User $teacher)
    {
        $this->ensureSuperAdmin();
        abort_unless($this->isTeacher($teacher),404);

        DB::transaction(function() use($teacher){
            DB::table('group_subjects')
                ->where('teacher_id',$teacher->id)
                ->update(['teacher_id'=>null,'updated_at'=>now()]);

            DB::table('study_group_user')
                ->where('user_id',$teacher->id)
                ->where('role','teacher')
                ->delete();
        });

        return redirect()->route('admin.teachers')
            ->with('success','Роль преподавателя снята. Сам аккаунт пользователя не удалён.');
    }
}
