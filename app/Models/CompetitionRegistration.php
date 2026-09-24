<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
class CompetitionRegistration extends Model {
 protected $fillable=['competition_id','user_id','status','submission_text','submission_url','file_path','file_name','submitted_at'];
 protected $casts=['submitted_at'=>'datetime'];
 protected $appends=['file_url'];
 public function competition(){return $this->belongsTo(Competition::class);}
 public function user(){return $this->belongsTo(User::class);}
 public function documents(){return $this->hasMany(CompetitionDocument::class);}
 public function getFileUrlAttribute(){return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;}
}