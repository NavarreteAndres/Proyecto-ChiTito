<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\{User,Device};
class CreateAdmin extends Command
{
    protected $signature='chitito:admin {email?} {--initial}';
    protected $description='Create or reset the administrator securely';
    public function handle(): int {
        Device::firstOrCreate(['identifier'=>'device01'],['name'=>'Dispensador ChiTito']);
        $email=$this->argument('email') ?: config('chitito.admin_email');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) {$this->error('Email inválido');return 1;}
        if($this->option('initial')) {
            if(User::where('email',$email)->exists()) return 0;
            $password=bin2hex(random_bytes(24));
            $file='/run/admin/initial-password';
            if(!is_dir(dirname($file))) {$this->error('Private admin directory missing');return 1;}
            file_put_contents($file,$password."\n");chmod($file,0600);
        }else{
            $password = $this->secret('Nueva contraseña');
            if (!is_string($password) || $password === '') {
            $this->error('La contraseña no puede estar vacía');
            return 1;}
        }
        $user=User::updateOrCreate(['email'=>$email],['name'=>'Administrador','password'=>$password]);
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id',$user->id)->delete();
        $this->info('Administrador configurado.');return 0;
    }
}
