<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
class Achievement extends Model {
 protected $fillable=['student_profile_id','competition_id','title','result','level','awarded_at','description','image','is_public'];
 protected $casts=['awarded_at'=>'date','is_public'=>'boolean'];
 protected $appends=['image_url'];
 public function student(){return $this->belongsTo(StudentProfile::class,'student_profile_id');}
 public function competition(){return $this->belongsTo(Competition::class);}
 public function getImageUrlAttribute(){if(!$this->image)return null; if(preg_match('~^(https?:)?//~i',$this->image))return $this->image; return Storage::disk('public')->url($this->image);}
}