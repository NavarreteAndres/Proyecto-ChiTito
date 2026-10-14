<?php
return ['default'=>env('DB_CONNECTION','mysql'), 'connections'=>[
    'mysql'=>['driver'=>'mysql','host'=>env('DB_HOST','db'),'port'=>3306,'database'=>env('DB_DATABASE','chitito'),'username'=>env('DB_USERNAME','chitito'),'password'=>env('DB_PASSWORD'),'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true,'timezone'=>'+00:00'],
    'sqlite'=>['driver'=>'sqlite','database'=>env('DB_DATABASE',':memory:'),'prefix'=>'','foreign_key_constraints'=>true],
], 'migrations'=>['table'=>'migrations','update_date_on_publish'=>true]];
