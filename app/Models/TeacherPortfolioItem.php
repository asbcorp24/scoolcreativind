<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TeacherPortfolioItem extends Model
{
    protected $fillable=[
        'teacher_profile_id','studio_id','title','type','description','cover',
        'project_url','video_url','completed_at','is_featured','is_public'
    ];

    protected $casts=[
        'completed_at'=>'date',
        'is_featured'=>'boolean',
        'is_public'=>'boolean',
    ];

    protected $appends=['cover_url'];

    public function teacherProfile(){ return $this->belongsTo(TeacherProfile::class); }
    public function studio(){ return $this->belongsTo(Studio::class); }
    public function media(){ return $this->morphMany(MediaLibraryItem::class,'attachable')->orderBy('sort_order')->orderBy('id'); }

    public function getCoverUrlAttribute(): ?string
    {
        if(!$this->cover) return null;
        if(preg_match('~^(https?:)?//~i',$this->cover)) return $this->cover;
        return Storage::disk('public')->url($this->cover);
    }
}
