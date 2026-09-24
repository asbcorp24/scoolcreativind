<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StudyGroup extends Model {
 protected $fillable=['name','study_year','code','studio_id','curator_name','is_active'];
 protected $casts=['is_active'=>'boolean'];
 public function studio(){return $this->belongsTo(Studio::class);}
 public function users(){return $this->belongsToMany(User::class,'study_group_user')->withPivot('role')->withTimestamps();}
 public function students(){return $this->belongsToMany(User::class,'study_group_user')->wherePivot('role','student')->withTimestamps();}
 public function teachers(){return $this->belongsToMany(User::class,'study_group_user')->wherePivot('role','teacher')->withTimestamps();}
 public function subjects(){return $this->belongsToMany(Subject::class,'group_subjects')->withPivot('teacher_id')->withTimestamps();}
 public function lessons(){return $this->hasMany(ScheduleLesson::class);}
}