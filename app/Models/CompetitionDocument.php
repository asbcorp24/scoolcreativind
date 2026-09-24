<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompetitionDocument extends Model
{
    protected $fillable=[
        'competition_registration_id','document_key','document_label',
        'file_path','file_name','mime_type','file_size'
    ];

    protected $appends=['file_url'];

    public function registration(){ return $this->belongsTo(CompetitionRegistration::class,'competition_registration_id'); }

    public function getFileUrlAttribute(){
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }
}
