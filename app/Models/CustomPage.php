<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CustomPage extends Model
{
    protected $fillable=[
        'parent_id','title','slug','menu_title','subtitle','body_html','cover_path',
        'sort_order','show_in_menu','is_published'
    ];

    protected $casts=[
        'show_in_menu'=>'boolean',
        'is_published'=>'boolean',
    ];

    protected $appends=['cover_url'];

    public function getRouteKeyName(){ return 'slug'; }

    public function parent(){ return $this->belongsTo(self::class,'parent_id'); }
    public function children(){ return $this->hasMany(self::class,'parent_id')->orderBy('sort_order')->orderBy('title'); }
    public function media(){ return $this->morphMany(MediaLibraryItem::class,'attachable')->orderBy('sort_order')->orderBy('id'); }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
