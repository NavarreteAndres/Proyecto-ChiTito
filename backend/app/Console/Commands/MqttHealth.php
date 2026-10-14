<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Carbon\CarbonImmutable;
class MqttHealth extends Command
{
    protected $signature='chitito:mqtt-health';
    protected $description='Check MQTT consumer heartbeat, independently of device status';
    public function handle(): int {
        try {
            $health=Cache::get('mqtt_health');
            return ($health['connected']??false) && CarbonImmutable::parse($health['heartbeat_at'])->gt(now()->subSeconds(30)) ? 0 : 1;
        }catch(\Throwable){return 1;}
    }
}
