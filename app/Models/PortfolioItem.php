<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
class PortfolioItem extends Model {
 protected $fillable=['student_profile_id','studio_id','title','type','description','cover','project_url','video_url','completed_at','is_featured','is_public'];
 protected $casts=['completed_at'=>'date','is_featured'=>'boolean','is_public'=>'boolean'];
 protected $appends=['cover_url'];
 public function student(){return $this->belongsTo(StudentProfile::class,'student_profile_id');}
 public function studio(){return $this->belongsTo(Studio::class);}
 public function getCoverUrlAttribute(){if(!$this->cover)return null; if(preg_match('~^(https?:)?//~i',$this->cover))return $this->cover; return Storage::disk('public')->url($this->cover);}
}