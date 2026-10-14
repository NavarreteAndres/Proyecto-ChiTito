<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Measurement extends Model
{
    public $timestamps=false;
    protected $guarded=[];
    protected function casts(): array { return ['received_at'=>'datetime','peso_raw'=>'integer','distancia_mm'=>'integer','nivel_pct'=>'integer']; }
    public function device() { return $this->belongsTo(Device::class); }
}
