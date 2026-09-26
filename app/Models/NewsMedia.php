<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class NewsMedia extends Model
{
    protected $fillable=[
        'news_post_id','type','title','path','url','file_name','mime_type','file_size','sort_order'
    ];

    protected $appends=['display_url','human_file_size'];

    public function post(){ return $this->belongsTo(NewsPost::class,'news_post_id'); }

    public function getDisplayUrlAttribute(): ?string
    {
        if($this->path) return Storage::disk('public')->url($this->path);
        return $this->url;
    }

    public function getHumanFileSizeAttribute(): ?string
    {
        if(!$this->file_size)return null;
        $units=['Б','КБ','МБ','ГБ'];$size=$this->file_size;$i=0;
        while($size>=1024 && $i<count($units)-1){$size/=1024;$i++;}
        return round($size,$i?1:0).' '.$units[$i];
    }
}
