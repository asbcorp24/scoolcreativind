<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CooperationItem extends Model
{
    protected $fillable=['type','title','description','image_path','url','sort_order','is_published'];
    protected $casts=['is_published'=>'boolean'];
    protected $appends=['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
