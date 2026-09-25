<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAccessController extends Controller
{
    public const SECTIONS = [
        'studios'=>'Студии и медиа',
        'news'=>'Новости',
        'projects'=>'Проекты / портфолио',
        'events'=>'События',
        'team'=>'Команда',
        'equipment'=>'Оборудование',
        'students'=>'Ученики',
        'competitions'=>'Конкурсы и достижения',
        'quizzes'=>'Викторины',
        'groups'=>'Учебные группы',
        'subjects'=>'Предметы',
        'schedule'=>'Расписание',
        'journal'=>'Журнал',
        'homework'=>'Домашние задания',
        'applications'=>'Заявки на поступление',
        'settings'=>'Главная / SEO / хранилище',
        'documents'=>'Официальные документы',
        'questions'=>'Вопросы и обращения',
    ];

    private function ensureSuperAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin,403);
    }

    public function index()
    {
        $this->ensureSuperAdmin();

        return view('admin.access-admins',[
            'admins'=>User::where(function($q){
                $q->where('is_admin',true)->orWhereNotNull('admin_sections');
            })->orderByDesc('is_admin')->orderBy('name')->get(),
            'sections'=>self::SECTIONS,
        ]);
    }

    public function save(Request $request, ?User $user=null)
    {
        $this->ensureSuperAdmin();

        $data=$request->validate([
            'name'=>'required|string|max:180',
            'email'=>'required|email|max:255|unique:users,email,'.($user?->id ?? 'NULL'),
            'phone'=>'nullable|string|max:80',
            'password'=>$user ? 'nullable|string|min:8|max:255' : 'required|string|min:8|max:255',
            'sections'=>'nullable|array',
            'sections.*'=>'string|in:'.implode(',',array_keys(self::SECTIONS)),
        ]);

        $user ??= new User();
        $user->name=$data['name'];
        $user->email=$data['email'];
        $user->phone=$data['phone'] ?? null;
        $user->is_admin=false;
        $user->admin_sections=array_values(array_unique($data['sections'] ?? []));

        if(!$user->admin_sections){
            return back()->withErrors(['sections'=>'Выберите хотя бы один раздел.'])->withInput();
        }

        if(!empty($data['password'])){
            $user->password=Hash::make($data['password']);
        }

        $user->save();

        return redirect()->route('admin.access-admins')->with('success','Права администратора сохранены.');
    }

    public function edit(User $user)
    {
        $this->ensureSuperAdmin();
        abort_if($user->is_admin,403,'Супер-администратора нельзя редактировать здесь.');

        return view('admin.access-admins',[
            'admins'=>User::where(function($q){
                $q->where('is_admin',true)->orWhereNotNull('admin_sections');
            })->orderByDesc('is_admin')->orderBy('name')->get(),
            'sections'=>self::SECTIONS,
            'editAdmin'=>$user,
        ]);
    }

    public function delete(User $user)
    {
        $this->ensureSuperAdmin();
        abort_if($user->is_admin,403,'Супер-администратора нельзя удалить здесь.');

        $user->update(['admin_sections'=>null]);

        return back()->with('success','Права администратора разделов сняты.');
    }
}
