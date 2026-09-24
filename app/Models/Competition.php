<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Competition extends Model {
 protected $fillable=['title','organizer','starts_on','ends_on','location','description','required_documents_json','url','cover','is_published'];
 protected $casts=['starts_on'=>'date','ends_on'=>'date','required_documents_json'=>'array','is_published'=>'boolean'];
 public function achievements(){return $this->hasMany(Achievement::class);}
 public function registrations(){return $this->hasMany(CompetitionRegistration::class);}
}