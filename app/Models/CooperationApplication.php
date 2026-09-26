<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CooperationApplication extends Model
{
    protected $fillable=['role','name','organization','phone','email','website','message','status'];
}
