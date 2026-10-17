<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable=[
        'user_id','action','entity_type','entity_id','description',
        'before_json','after_json','ip_address'
    ];

    protected $casts=[
        'before_json'=>'array',
        'after_json'=>'array',
    ];

    public function user(){ return $this->belongsTo(User::class); }

    public static function record(string $action, $entity=null, ?string $description=null, ?array $before=null, ?array $after=null): void
    {
        static::create([
            'user_id'=>auth()->id(),
            'action'=>$action,
            'entity_type'=>$entity ? get_class($entity) : null,
            'entity_id'=>$entity?->id,
            'description'=>$description,
            'before_json'=>$before,
            'after_json'=>$after,
            'ip_address'=>request()?->ip(),
        ]);
    }
}
