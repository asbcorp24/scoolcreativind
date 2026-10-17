<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleLesson extends Model
{
    protected $fillable=[
        'studio_id','study_group_id','subject_id','subject_lesson_id','journal_lesson_id',
        'teacher_id','title','teacher_name','lesson_date','starts_at','ends_at','room',
        'description','color','is_published','status','status_note',
        'original_lesson_date','original_starts_at'
    ];

    protected $casts=[
        'lesson_date'=>'date',
        'original_lesson_date'=>'date',
        'is_published'=>'boolean',
    ];

    public function studio(){ return $this->belongsTo(Studio::class); }
    public function group(){ return $this->belongsTo(StudyGroup::class,'study_group_id'); }
    public function subject(){ return $this->belongsTo(Subject::class); }
    public function planLesson(){ return $this->belongsTo(SubjectLesson::class,'subject_lesson_id'); }
    public function journalLesson(){ return $this->belongsTo(JournalLesson::class,'journal_lesson_id'); }
    public function teacher(){ return $this->belongsTo(User::class,'teacher_id'); }

    public function getStatusLabelAttribute(): string
    {
        return [
            'planned'=>'Запланировано',
            'completed'=>'Проведено',
            'cancelled'=>'Отменено',
            'moved'=>'Перенесено',
        ][$this->status] ?? $this->status;
    }
}
