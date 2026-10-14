<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->unique();$t->string('password');$t->rememberToken();$t->timestamps();});
        Schema::create('devices',function(Blueprint $t){$t->id();$t->string('identifier')->unique();$t->string('name');$t->string('broker_status')->nullable();$t->timestamp('status_received_at')->nullable();$t->timestamp('last_seen_at')->nullable();$t->timestamps();});
        Schema::create('measurements',function(Blueprint $t){$t->id();$t->foreignId('device_id')->constrained();$t->bigInteger('peso_raw')->nullable();$t->unsignedInteger('distancia_mm')->nullable();$t->unsignedTinyInteger('nivel_pct')->nullable();$t->timestamp('received_at',6);$t->index(['device_id','received_at','id']);});
        Schema::create('sessions',function(Blueprint $t){$t->string('id')->primary();$t->foreignId('user_id')->nullable()->index();$t->string('ip_address',45)->nullable();$t->text('user_agent')->nullable();$t->longText('payload');$t->integer('last_activity')->index();});
        Schema::create('cache',function(Blueprint $t){$t->string('key')->primary();$t->mediumText('value');$t->integer('expiration');});
        Schema::create('cache_locks',function(Blueprint $t){$t->string('key')->primary();$t->string('owner');$t->integer('expiration');});
    }
    public function down(): void { foreach(['cache_locks','cache','sessions','measurements','devices','users'] as $table) Schema::dropIfExists($table); }
};
