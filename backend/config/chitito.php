<?php
return ['device'=>'device01','stale_seconds'=>15,'mqtt'=>['host'=>env('MQTT_HOST','15.235.63.174'),'port'=>8883,'username'=>env('MQTT_USERNAME','chitito-reader'),'password'=>env('MQTT_PASSWORD'),'ca'=>env('MQTT_CA','/run/mqtt/ca.crt')]];
