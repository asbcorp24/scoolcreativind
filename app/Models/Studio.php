<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Studio extends Model {
 protected $fillable=['title','slug','subtitle','description','icon','accent','cover','sort_order','is_active'];
 protected $casts=['is_active'=>'boolean'];
 public function media(){ return $this->morphMany(MediaLibraryItem::class,'attachable')->orderBy('sort_order'); }
 public function projects(){ return $this->hasMany(PortfolioItem::class)->where('is_public',true)->latest('completed_at'); }
 public function team(){ return $this->hasMany(TeamMember::class)->where('is_active',true)->orderBy('sort_order'); }
 public function equipment(){ return $this->hasMany(EquipmentItem::class)->orderBy('sort_order'); }
 public function getRouteKeyName(){ return 'slug'; }
}
