<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable=[
        'user_id','name','email','phone','subject','question','status','answer','answered_at'
    ];

    protected $casts=['answered_at'=>'datetime'];

    public function user(){ return $this->belongsTo(User::class); }
}
