<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
class HomeworkSubmission extends Model {
 protected $fillable=['homework_assignment_id','student_id','text_answer','file','external_url','status','score','teacher_comment','submitted_at','reviewed_at'];
 protected $casts=['submitted_at'=>'datetime','reviewed_at'=>'datetime'];
 protected $appends=['file_url'];
 public function assignment(){return $this->belongsTo(HomeworkAssignment::class,'homework_assignment_id');}
 public function student(){return $this->belongsTo(User::class,'student_id');}
 public function getFileUrlAttribute(){if(!$this->file)return null; return Storage::disk('public')->url($this->file);}
}