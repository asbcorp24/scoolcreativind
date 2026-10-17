<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $fillable=['name','starts_on','ends_on','is_current','is_closed'];
    protected $casts=[
        'starts_on'=>'date',
        'ends_on'=>'date',
        'is_current'=>'boolean',
        'is_closed'=>'boolean',
    ];

    public function groups(){ return $this->hasMany(StudyGroup::class); }
}
