<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\TelemetryIngestor;
use PhpMqtt\Client\{MqttClient,ConnectionSettings};
class ConsumeMqtt extends Command
{
    protected $signature='mqtt:consume';
    protected $description='Consume ChiTito telemetry over verified TLS';
    public function handle(TelemetryIngestor $ingestor): int {
        $stop=false;$client=null;
        pcntl_async_signals(true);
        foreach([SIGTERM,SIGINT] as $signal) pcntl_signal($signal,function() use(&$stop,&$client){$stop=true;$client?->interrupt();});
        $delay=2;
        while(!$stop) {
            try {
                $c=config('chitito.mqtt');
                $client=new MqttClient($c['host'],$c['port'],'chitito-laravel-reader');
                $settings=(new ConnectionSettings)->setUsername($c['username'])->setPassword($c['password'])->setUseTls(true)->setTlsVerifyPeer(true)->setTlsVerifyPeerName(true)->setTlsSelfSignedAllowed(false)->setTlsCertificateAuthorityFile($c['ca'])->setConnectTimeout(10)->setKeepAliveInterval(20);
                $client->connect($settings,true);
                $base='chitito/'.config('chitito.device');
                $lastWarning=0;
                foreach(['status','telemetry'] as $suffix) $client->subscribe($base.'/'.$suffix,function($topic,$payload,$retained) use($ingestor,&$lastWarning){
                    try {$ingestor->ingest($topic,$payload,$retained);}catch(\InvalidArgumentException $e){if(time()-$lastWarning>=30){Log::warning('MQTT message rejected',['reason'=>$e->getMessage()]);$lastWarning=time();}}
                },0);
                $lastHeartbeat=0;
                $client->registerLoopEventHandler(function() use(&$lastHeartbeat){if(time()-$lastHeartbeat>=5){Cache::put('mqtt_health',['connected'=>true,'heartbeat_at'=>now()->toISOString()],30);$lastHeartbeat=time();}});
                Log::info('MQTT TLS connected; subscribed to device01');$delay=2;
                $client->loop(true);
                if($client->isConnected()) $client->disconnect();
            }catch(\Throwable $e){
                // Exception messages can contain connection details. Only log the class.
                Log::error('MQTT consumer reconnecting',['exception'=>get_class($e)]);
                try{Cache::put('mqtt_health',['connected'=>false,'heartbeat_at'=>now()->toISOString()],30);}catch(\Throwable){}
                if(!$stop) sleep($delay);$delay=min(30,$delay*2);
            }
        }
        Cache::put('mqtt_health',['connected'=>false,'heartbeat_at'=>now()->toISOString()],30);
        return self::SUCCESS;
    }
}
