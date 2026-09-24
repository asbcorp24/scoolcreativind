<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AdmissionApplication extends Model {
 protected $fillable=['user_id','name','birth_date','phone','email','studio_id','message','status'];
 protected $casts=['birth_date'=>'date'];
 public function studio(){ return $this->belongsTo(Studio::class); }
 public function user(){ return $this->belongsTo(User::class); }
}
