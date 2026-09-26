<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CustomPageMedia extends Model
{
    protected $fillable=[
        'custom_page_id','type','title','path','url','file_name','mime_type','file_size','sort_order'
    ];

    protected $appends=['display_url','human_file_size'];

    public function page(){ return $this->belongsTo(CustomPage::class,'custom_page_id'); }

    public function getDisplayUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk('public')->url($this->path) : $this->url;
    }

    public function getHumanFileSizeAttribute(): ?string
    {
        if(!$this->file_size)return null;
        $units=['Б','КБ','МБ','ГБ'];$size=$this->file_size;$i=0;
        while($size>=1024 && $i<count($units)-1){$size/=1024;$i++;}
        return round($size,$i?1:0).' '.$units[$i];
    }
}
