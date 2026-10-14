<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Device extends Model
{
    protected $guarded=[];
    protected function casts(): array { return ['last_seen_at'=>'datetime','status_received_at'=>'datetime']; }
    public function measurements() { return $this->hasMany(Measurement::class); }
    public function connectionState(): string {
        if ($this->broker_status === 'offline') return 'offline';
        if (!$this->last_seen_at) return 'unknown';
        return $this->last_seen_at->lt(now()->subSeconds(config('chitito.stale_seconds'))) ? 'stale' : 'online';
    }
}
