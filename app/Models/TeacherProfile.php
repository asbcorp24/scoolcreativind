<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TeacherProfile extends Model
{
    protected $fillable=[
        'user_id','studio_id','slug','avatar','headline','bio','education','experience',
        'competencies','courses','publications','professional_achievements',
        'website_url','vk_url','telegram_url','is_public'
    ];

    protected $casts=['is_public'=>'boolean'];
    protected $appends=['avatar_url'];

    public function user(){ return $this->belongsTo(User::class); }
    public function studio(){ return $this->belongsTo(Studio::class); }
    public function portfolio(){ return $this->hasMany(TeacherPortfolioItem::class)->latest('completed_at'); }
    public function mentoredWorks(){ return $this->hasMany(PortfolioItem::class,'mentor_teacher_id','user_id')->latest('completed_at'); }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if(!$this->avatar) return null;
        if(preg_match('~^(https?:)?//~i',$this->avatar)) return $this->avatar;
        return Storage::disk('public')->url($this->avatar);
    }
}
