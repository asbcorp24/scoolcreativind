<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StudentProject extends Model {
 protected $fillable=['studio_id','title','author','year','description','cover','project_url','is_featured'];
 protected $casts=['is_featured'=>'boolean'];
 public function studio(){ return $this->belongsTo(Studio::class); }
}
