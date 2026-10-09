<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectLesson extends Model
{
    protected $fillable=[
        'subject_id',
        'lesson_number',
        'title',
        'content',
        'homework_description',
        'homework_due_days',
        'homework_max_score',
        'sort_order',
        'is_published',
    ];

    protected $casts=[
        'is_published'=>'boolean',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function media()
    {
        return $this->morphMany(MediaLibraryItem::class,'attachable')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function journalLessons()
    {
        return $this->hasMany(JournalLesson::class,'subject_lesson_id');
    }

    public function homeworkAssignments()
    {
        return $this->hasMany(HomeworkAssignment::class,'subject_lesson_id');
    }
}
