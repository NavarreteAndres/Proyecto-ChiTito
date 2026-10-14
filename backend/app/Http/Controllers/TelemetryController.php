<?php
namespace App\Http\Controllers;
use App\Models\Device;
use App\Http\Requests\TelemetryRange;
use Illuminate\Support\Facades\Cache;
class TelemetryController
{
    private function current(): Device { return Device::where('identifier',config('chitito.device'))->firstOrFail(); }
    public function device() {
        $d=$this->current();
        return response()->json(['data'=>array_merge($d->toArray(),['connection_state'=>$d->connectionState(),'latest'=>$d->measurements()->orderByDesc('received_at')->orderByDesc('id')->first(),'consumer'=>Cache::get('mqtt_health')])]);
    }
    public function latest() { return response()->json(['data'=>$this->current()->measurements()->orderByDesc('received_at')->orderByDesc('id')->first()]); }
    public function history(TelemetryRange $r) {
        [$from,$to]=$r->bounds();
        return response()->json($this->current()->measurements()->whereBetween('received_at',[$from,$to])->orderByDesc('received_at')->orderByDesc('id')->paginate($r->integer('per_page',25)));
    }
    public function chart(TelemetryRange $r) {
        [$from,$to]=$r->bounds();$d=$this->current();$bucket=max(1,(int)ceil($from->diffInSeconds($to)/240));
        // One cache entry per user/device; moving windows never accumulate cache rows.
        $key='chart:'.$d->id.':'.$r->user()->id;
        $signature=intdiv($from->timestamp,15).':'.intdiv($to->timestamp,15).':'.$bucket;
        $cached=Cache::get($key);
        if(($cached['signature']??null)===$signature) $data=$cached['data'];
        else {
        $data=$d->measurements()->whereBetween('received_at',[$from,$to])
            ->selectRaw('FLOOR(UNIX_TIMESTAMP(received_at) / ?) AS bucket, MIN(received_at) AS received_at, AVG(peso_raw) AS peso_raw, AVG(distancia_mm) AS distancia_mm, AVG(nivel_pct) AS nivel_pct, COUNT(*) AS samples',[$bucket])
            ->groupBy('bucket')->orderBy('bucket')->get()->map(fn($m)=>['received_at'=>$m->received_at->toISOString(),'peso_raw'=>$m->peso_raw === null ? null : (float)$m->getRawOriginal('peso_raw'),'distancia_mm'=>$m->distancia_mm === null ? null : (float)$m->getRawOriginal('distancia_mm'),'nivel_pct'=>$m->nivel_pct === null ? null : (float)$m->getRawOriginal('nivel_pct'),'samples'=>(int)$m->samples])->all();
        Cache::put($key,['signature'=>$signature,'data'=>$data],15);
        }
        return response()->json(['data'=>$data,'meta'=>['bucket_seconds'=>$bucket,'aggregation'=>'mean','timezone'=>'UTC']]);
    }
}
