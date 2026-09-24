<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Event extends Model {
 protected $fillable=['title','slug','description','starts_at','location','cover','registration_url','is_published'];
 protected $casts=['starts_at'=>'datetime','is_published'=>'boolean'];
 public function getRouteKeyName(){ return 'slug'; }
}
