<?php
namespace App\Helpers;
use App\Config\Database;
final class Setup {
    public static function completed():bool { try { $v=Database::connection()->query("SELECT setting_value FROM system_settings WHERE setting_key='setup_completed' LIMIT 1")->fetchColumn(); return $v==='1'; } catch (\Throwable) { return false; } }
    public static function adminCount():int { try { return (int)Database::connection()->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='ADMIN'")->fetchColumn(); } catch (\Throwable){ return 0; } }
    public static function installKeyPath():string { return dirname(__DIR__,2).'/storage/install.key'; }
    public static function verifyKey(string $key):bool { $p=self::installKeyPath(); return is_file($p) && hash_equals(trim((string)file_get_contents($p)), trim($key)); }
}
