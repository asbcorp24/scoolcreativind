<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ScheduleLesson extends Model {
 protected $fillable=['studio_id','title','teacher_name','lesson_date','starts_at','ends_at','room','description','color','is_published'];
 protected $casts=['lesson_date'=>'date','is_published'=>'boolean'];
 public function studio(){return $this->belongsTo(Studio::class);}
}