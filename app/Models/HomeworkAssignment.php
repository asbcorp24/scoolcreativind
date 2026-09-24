<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
class HomeworkAssignment extends Model {
 protected $fillable=['study_group_id','subject_id','teacher_id','title','description','due_at','attachment','external_url','max_score','is_published'];
 protected $casts=['due_at'=>'datetime','is_published'=>'boolean'];
 protected $appends=['attachment_url'];
 public function group(){return $this->belongsTo(StudyGroup::class,'study_group_id');}
 public function subject(){return $this->belongsTo(Subject::class);}
 public function teacher(){return $this->belongsTo(User::class,'teacher_id');}
 public function submissions(){return $this->hasMany(HomeworkSubmission::class);}
 public function getAttachmentUrlAttribute(){if(!$this->attachment)return null; return Storage::disk('public')->url($this->attachment);}
}