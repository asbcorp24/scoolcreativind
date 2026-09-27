<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaLibraryItem extends Model
{
    protected $fillable=[
        'attachable_type','attachable_id','type','title','url','file_name','mime_type','file_size','thumbnail','caption',
        'hotspots_json','sort_order','is_visible','is_featured'
    ];

    protected $casts=['is_visible'=>'boolean','is_featured'=>'boolean'];
    protected $appends=['display_url','thumbnail_url','human_file_size'];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function getDisplayUrlAttribute(): string
    {
        $url=(string)$this->url;
        if(preg_match('~^(https?:)?//~i',$url) || str_starts_with($url,'data:'))return $url;
        return Storage::disk('public')->url($url);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if(!$this->thumbnail)return null;
        if(preg_match('~^(https?:)?//~i',$this->thumbnail) || str_starts_with($this->thumbnail,'data:'))return $this->thumbnail;
        return Storage::disk('public')->url($this->thumbnail);
    }

    public function getHumanFileSizeAttribute(): ?string
    {
        if(!$this->file_size)return null;
        $units=['Б','КБ','МБ','ГБ'];
        $size=$this->file_size;$i=0;
        while($size>=1024 && $i<count($units)-1){$size/=1024;$i++;}
        return round($size,$i?1:0).' '.$units[$i];
    }
}
