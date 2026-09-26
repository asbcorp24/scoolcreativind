<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Clip extends Model
{
    protected $fillable=[
        'title','slug','description','cover_path','archive_name','content_dir',
        'entry_file','archive_size','sort_order','is_published'
    ];

    protected $casts=['is_published'=>'boolean'];
    protected $appends=['player_url','cover_url'];

    public function getPlayerUrlAttribute(): string
    {
        return Storage::disk('public')->url(trim($this->content_dir,'/').'/'.ltrim($this->entry_file,'/'));
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
