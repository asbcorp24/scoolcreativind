<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JournalEntry extends Model {
 protected $fillable=['journal_lesson_id','student_id','attendance','grade','grade_label','comment'];
 public function lesson(){return $this->belongsTo(JournalLesson::class,'journal_lesson_id');}
 public function student(){return $this->belongsTo(User::class,'student_id');}
}