<?php
namespace App\Helpers;
final class Flash {
    public static function set(string $type,string $message):void { $_SESSION['_flash'][]=['type'=>$type,'message'=>$message]; }
    public static function all():array { $f=$_SESSION['_flash']??[]; unset($_SESSION['_flash']); return $f; }
}
