<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MediaItem extends Model {
 protected $fillable=['studio_id','type','title','url','thumbnail','caption','sort_order','is_featured'];
 protected $casts=['is_featured'=>'boolean'];
 public function studio(){ return $this->belongsTo(Studio::class); }
}
