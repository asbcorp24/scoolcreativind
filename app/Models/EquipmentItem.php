<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EquipmentItem extends Model
{
    protected $fillable=[
        'studio_id','title','category','brand','model','description','image','specs','is_featured','sort_order'
    ];

    protected $casts=['specs'=>'array','is_featured'=>'boolean'];
    protected $appends=['image_url'];

    public function studio(){ return $this->belongsTo(Studio::class); }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) return null;
        if (preg_match('~^(https?:)?//~i',$this->image)) return $this->image;
        return Storage::disk('public')->url($this->image);
    }
}
