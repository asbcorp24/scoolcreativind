<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentReportLink extends Model
{
    protected $fillable=[
        'target_type','student_profile_id','study_group_id','token','report_until','is_active','last_opened_at'
    ];

    protected $casts=[
        'report_until'=>'date',
        'is_active'=>'boolean',
        'last_opened_at'=>'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(StudentProfile::class,'student_profile_id');
    }

    public function group()
    {
        return $this->belongsTo(StudyGroup::class,'study_group_id');
    }
}
