<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable=['name','email','phone','password','is_admin','admin_sections'];
    protected $hidden=['password','remember_token'];
    protected $casts=['email_verified_at'=>'datetime','is_admin'=>'boolean','admin_sections'=>'array'];

    public function isSectionAdmin(): bool
    {
        return !$this->is_admin && !empty($this->admin_sections);
    }

    public function canAdminSection(string $section): bool
    {
        return $this->is_admin || in_array($section, $this->admin_sections ?? [], true);
    }

    public function adminLandingRoute(): string
    {
        if ($this->is_admin) return 'admin.dashboard';

        $map=[
            'studios'=>'admin.dashboard',
            'news'=>'admin.news.create',
            'projects'=>'admin.projects',
            'events'=>'admin.events',
            'team'=>'admin.team',
            'equipment'=>'admin.equipment',
            'students'=>'admin.students',
            'competitions'=>'admin.competitions',
            'quizzes'=>'admin.quizzes',
            'groups'=>'admin.groups',
            'subjects'=>'admin.subjects',
            'schedule'=>'admin.schedule',
            'journal'=>'admin.journal',
            'homework'=>'admin.homework',
            'settings'=>'admin.settings',
            'documents'=>'admin.documents',
            'questions'=>'admin.questions',
            'applications'=>'admin.dashboard',
        ];

        foreach ($this->admin_sections ?? [] as $section) {
            if (isset($map[$section])) return $map[$section];
        }

        return 'cabinet';
    }

    public function studyGroups()
    {
        return $this->belongsToMany(StudyGroup::class,'study_group_user')
            ->withPivot('role')->withTimestamps();
    }

    public function studentGroups()
    {
        return $this->studyGroups()->wherePivot('role','student');
    }

    public function teacherGroups()
    {
        return $this->studyGroups()->wherePivot('role','teacher');
    }

    public function homeworkSubmissions()
    {
        return $this->hasMany(HomeworkSubmission::class,'student_id');
    }

    public function competitionRegistrations()
    {
        return $this->hasMany(CompetitionRegistration::class);
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
