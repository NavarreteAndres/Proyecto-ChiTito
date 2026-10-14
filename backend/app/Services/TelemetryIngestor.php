<?php
namespace App\Services;
use App\Models\Device;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
class TelemetryIngestor
{
    public function ingest(string $topic,string $payload,bool $retained): bool {
        $base='chitito/'.config('chitito.device');
        if($topic===$base.'/status') {
            if(!in_array($payload,['online','offline'],true)) throw new InvalidArgumentException('Invalid status');
            $d=Device::where('identifier',config('chitito.device'))->firstOrFail();
            $d->broker_status=$payload;$d->status_received_at=now();
            // Retained status has no source timestamp: never refresh last_seen_at.
            if(!$retained && $payload==='online') $d->last_seen_at=now();
            $d->save();return false;
        }
        if($topic!==$base.'/telemetry') throw new InvalidArgumentException('Unexpected topic');
        if($retained) return false;
        if(strlen($payload)>2048) throw new InvalidArgumentException('Payload too large');
        try {$data=json_decode($payload,true,8,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new InvalidArgumentException('Invalid JSON');}
        if(!is_array($data) || ($data['device']??null)!==config('chitito.device')) throw new InvalidArgumentException('Wrong device');
        $limits=['peso_raw'=>[-2147483648,2147483647],'distancia_mm'=>[0,65535],'nivel_pct'=>[0,100]];$values=[];
        foreach($limits as $field=>[$min,$max]) {
            if(!array_key_exists($field,$data)) throw new InvalidArgumentException('Missing field');
            $v=$data[$field];
            if($v!==null && (!is_int($v) || $v<$min || $v>$max)) throw new InvalidArgumentException('Invalid sensor value');
            $values[$field]=$v;
        }
        DB::transaction(function() use($values){
            $d=Device::where('identifier',config('chitito.device'))->lockForUpdate()->firstOrFail();
            $d->measurements()->create($values+['received_at'=>now()]);
            $d->update(['last_seen_at'=>now(),'broker_status'=>'online']);
        });
        return true;
    }
}
