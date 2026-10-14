<?php
return ['device'=>'device01','stale_seconds'=>15,'admin_email'=>env('ADMIN_EMAIL'),'mqtt'=>['host'=>env('MQTT_HOST'),'port'=>(int)env('MQTT_PORT',8883),'username'=>env('MQTT_USERNAME'),'password'=>env('MQTT_PASSWORD'),'ca'=>env('MQTT_CA','/run/mqtt/ca.crt')]];
