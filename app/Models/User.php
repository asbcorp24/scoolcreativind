<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable=['name','email','phone','password','is_admin'];
    protected $hidden=['password','remember_token'];
    protected $casts=['email_verified_at'=>'datetime','is_admin'=>'boolean'];

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
}
