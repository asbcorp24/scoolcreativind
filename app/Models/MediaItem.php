<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaItem extends Model
{
    protected $fillable=[
        'studio_id','type','title','url','thumbnail','caption',
        'hotspots_json','sort_order','is_featured'
    ];

    protected $casts=['is_featured'=>'boolean'];

    protected $appends=['display_url'];

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }

    public function getDisplayUrlAttribute(): string
    {
        $url=(string)$this->url;
        if (preg_match('~^(https?:)?//~i',$url) || str_starts_with($url,'data:')) {
            return $url;
        }
        return Storage::disk('public')->url($url);
    }
}
