<?php
namespace App\Helpers;
use App\Config\Database;
final class Settings {
    public static function get(string $key, mixed $default=null): mixed {
        $s=Database::connection()->prepare('SELECT setting_value FROM system_settings WHERE setting_key=? LIMIT 1');
        $s->execute([$key]); $v=$s->fetchColumn(); return $v===false?$default:$v;
    }
    public static function set(string $key,string $value):void {
        $s=Database::connection()->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        $s->execute([$key,$value]);
    }
    public static function pointsPerUsd():float { return max(0,(float)self::get('points_per_usd','10')); }
}
