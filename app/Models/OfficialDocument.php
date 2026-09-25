<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class OfficialDocument extends Model
{
    protected $fillable=[
        'title','category','document_number','document_date','description',
        'file_path','file_name','file_size','sort_order','is_published'
    ];

    protected $casts=[
        'document_date'=>'date',
        'is_published'=>'boolean',
    ];

    protected $appends=['file_url','human_file_size'];

    public function getFileUrlAttribute(){
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    public function getHumanFileSizeAttribute(){
        if(!$this->file_size)return null;
        $units=['Б','КБ','МБ','ГБ'];
        $size=$this->file_size;
        $i=0;
        while($size>=1024 && $i<count($units)-1){$size/=1024;$i++;}
        return round($size,$i?1:0).' '.$units[$i];
    }
}
