<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TeamMember extends Model
{
    protected $fillable=[
        'studio_id','name','role','bio','photo','email','vk_url','telegram_url','sort_order','is_active'
    ];

    protected $casts=['is_active'=>'boolean'];
    protected $appends=['photo_url'];

    public function studio(){ return $this->belongsTo(Studio::class); }

    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) return null;
        if (preg_match('~^(https?:)?//~i',$this->photo)) return $this->photo;
        return Storage::disk('public')->url($this->photo);
    }
}
