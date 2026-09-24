<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
class StudentProfile extends Model {
 protected $fillable=['user_id','studio_id','class_name','avatar','bio','portfolio_slug','is_public'];
 protected $casts=['is_public'=>'boolean'];
 protected $appends=['avatar_url'];
 public function user(){return $this->belongsTo(User::class);}
 public function studio(){return $this->belongsTo(Studio::class);}
 public function portfolio(){return $this->hasMany(PortfolioItem::class)->latest('completed_at');}
 public function achievements(){return $this->hasMany(Achievement::class)->latest('awarded_at');}
 public function getAvatarUrlAttribute(){if(!$this->avatar)return null; if(preg_match('~^(https?:)?//~i',$this->avatar))return $this->avatar; return Storage::disk('public')->url($this->avatar);}
}