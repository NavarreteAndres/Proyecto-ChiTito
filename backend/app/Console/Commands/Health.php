<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class Health extends Command
{
    protected $signature='chitito:health';
    protected $description='Check database connectivity';
    public function handle(): int { try {DB::select('SELECT 1');return 0;}catch(\Throwable){return 1;} }
}
