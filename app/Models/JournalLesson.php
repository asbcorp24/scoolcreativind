<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JournalLesson extends Model {
 protected $fillable=['study_group_id','subject_id','teacher_id','lesson_date','topic','notes'];
 protected $casts=['lesson_date'=>'date'];
 public function group(){return $this->belongsTo(StudyGroup::class,'study_group_id');}
 public function subject(){return $this->belongsTo(Subject::class);}
 public function teacher(){return $this->belongsTo(User::class,'teacher_id');}
 public function entries(){return $this->hasMany(JournalEntry::class);}
}