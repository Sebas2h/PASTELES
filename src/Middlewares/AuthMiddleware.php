<?php
namespace App\Middlewares; use App\Helpers\Auth;
final class AuthMiddleware { public static function handle():void { if(!Auth::check()){ header('Location: /login'); exit; } } }
