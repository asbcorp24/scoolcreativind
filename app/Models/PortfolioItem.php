<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
class PortfolioItem extends Model {
 protected $fillable=['student_profile_id','studio_id','title','type','description','cover','file_path','file_name','mime_type','file_size','project_url','video_url','completed_at','is_featured','is_public'];
 protected $casts=['completed_at'=>'date','is_featured'=>'boolean','is_public'=>'boolean'];
 protected $appends=['cover_url','file_url','human_file_size'];
 public function student(){return $this->belongsTo(StudentProfile::class,'student_profile_id');}
 public function studio(){return $this->belongsTo(Studio::class);}
 public function getCoverUrlAttribute(){if(!$this->cover)return null; if(preg_match('~^(https?:)?//~i',$this->cover))return $this->cover; return Storage::disk('public')->url($this->cover);}
 public function getFileUrlAttribute(){return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;}
 public function getHumanFileSizeAttribute(){if(!$this->file_size)return null; $units=['Б','КБ','МБ','ГБ']; $size=$this->file_size; $i=0; while($size>=1024 && $i<count($units)-1){$size/=1024;$i++;} return round($size,$i?1:0).' '.$units[$i];}
}