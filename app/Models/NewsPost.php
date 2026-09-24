<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NewsPost extends Model {
 protected $fillable=['title','slug','excerpt','body','cover','published_at','is_published'];
 protected $casts=['published_at'=>'datetime','is_published'=>'boolean'];
 public function getRouteKeyName(){ return 'slug'; }
}
