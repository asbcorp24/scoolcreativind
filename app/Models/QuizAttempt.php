<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuizAttempt extends Model {
 protected $fillable=['quiz_id','user_id','score','passed','answers_json','certificate_code','completed_at'];
 protected $casts=['passed'=>'boolean','answers_json'=>'array','completed_at'=>'datetime'];
 public function quiz(){return $this->belongsTo(Quiz::class);}
 public function user(){return $this->belongsTo(User::class);}
}