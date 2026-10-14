<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class AuthController
{
    public function login(Request $r) {
        $credentials=$r->validate(['email'=>'required|email|max:254','password'=>'required|string|max:200']);
        if(!Auth::attempt($credentials)) throw ValidationException::withMessages(['email'=>['Credenciales incorrectas.']]);
        $r->session()->regenerate();
        return $this->session($r);
    }
    public function session(Request $r) { return response()->json(['data'=>['name'=>$r->user()->name,'email'=>$r->user()->email]]); }
    public function logout(Request $r) { Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return response()->json(['data'=>null]); }
}
