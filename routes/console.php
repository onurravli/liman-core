<?php

use App\User;
use Illuminate\Foundation\Inspiring;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

Artisan::command('init:admin',function (){
    // Check if Admin user is exists.
    $user = User::where('email','admin@liman.app')->first();
    if($user){
        if(!$this->confirm('Administrator kullanıcısı silinip tekrar eklenecektir. Devam etmek istiyor musunuz?')){
            return false;
        }
        $user->delete();
    }

    $password = str_random("16");
    $user = User::create([
        'name' => "administrator",
        'email' => "admin@liman.app",
        'password' => Hash::make($password),
        'status' => 1
    ]);
    $perm = new \App\Permission();
    $perm->user_id = $user->_id;
    $perm->server = [];
    $perm->save();
    $this->comment("Administrator kullanıcısı eklendi. Parola : " . $password . "");
})->describe('Create administrator account to use');

Artisan::command('test',function (){
    if(is_dir(app_path('setup'))){
        $this->comment('var');
    }else{
        $this->comment(__('Kurulacak bir şey bulunamadı.'));
    }
});