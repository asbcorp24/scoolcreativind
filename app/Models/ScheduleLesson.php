<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ScheduleLesson extends Model {
 protected $fillable=['studio_id','study_group_id','title','teacher_name','lesson_date','starts_at','ends_at','room','description','color','is_published'];
 protected $casts=['lesson_date'=>'date','is_published'=>'boolean'];
 public function studio(){return $this->belongsTo(Studio::class);}
 public function group(){return $this->belongsTo(StudyGroup::class,'study_group_id');}
}