<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Subject extends Model {
 protected $fillable=['title','studio_id','description'];
 public function studio(){return $this->belongsTo(Studio::class);}
 public function groups(){return $this->belongsToMany(StudyGroup::class,'group_subjects')->withPivot('teacher_id')->withTimestamps();}
}