<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Quiz extends Model {
 protected $fillable=['title','description','pass_score','questions_json','is_published'];
 protected $casts=['questions_json'=>'array','is_published'=>'boolean'];
 public function attempts(){return $this->hasMany(QuizAttempt::class);}
}