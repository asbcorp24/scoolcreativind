<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NewsPost extends Model {
 protected $fillable=['title','slug','excerpt','body','cover','published_at','is_published'];
 protected $casts=['published_at'=>'datetime','is_published'=>'boolean'];
 protected $appends=['cover_url'];
 public function getRouteKeyName(){ return 'slug'; }
 public function getCoverUrlAttribute(): ?string {
   if(!$this->cover)return null;
   if(preg_match('~^(https?:)?//~i',$this->cover))return $this->cover;
   return \Illuminate\Support\Facades\Storage::disk('public')->url($this->cover);
 }
 public function media(){ return $this->hasMany(NewsMedia::class)->orderBy('sort_order')->orderBy('id'); }
 public function images(){ return $this->hasMany(NewsMedia::class)->where('type','image')->orderBy('sort_order')->orderBy('id'); }
}
