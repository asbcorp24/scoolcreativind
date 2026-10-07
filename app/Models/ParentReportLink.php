<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentReportLink extends Model
{
    protected $fillable=[
        'student_profile_id','token','report_until','is_active','last_opened_at'
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
}
