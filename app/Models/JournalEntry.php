<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JournalEntry extends Model {
 protected $appends=['absence_document_url'];
 protected $fillable=['journal_lesson_id','student_id','attendance','absence_reason','absence_document','grade','grade_label','comment'];
 public function lesson(){return $this->belongsTo(JournalLesson::class,'journal_lesson_id');}
 public function student(){return $this->belongsTo(User::class,'student_id');}
 public function getAbsenceDocumentUrlAttribute(){return $this->absence_document ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->absence_document) : null;}
}