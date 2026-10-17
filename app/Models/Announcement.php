<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable=[
        'author_id','study_group_id','audience','title','body',
        'published_at','expires_at','is_published'
    ];
    protected $casts=[
        'published_at'=>'datetime',
        'expires_at'=>'datetime',
        'is_published'=>'boolean',
    ];

    public function author(){ return $this->belongsTo(User::class,'author_id'); }
    public function group(){ return $this->belongsTo(StudyGroup::class,'study_group_id'); }
}
