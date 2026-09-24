<?php
namespace App\Helpers;
use App\Config\Database;
final class Auth {
    public static function user(): ?array {
        $id=$_SESSION['user_id'] ?? null; if (!$id) return null;
        $st=Database::connection()->prepare('SELECT u.id,u.first_name,u.second_name,u.last_name,u.second_last_name,u.username,u.email,u.points_balance,u.status,r.name role FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? LIMIT 1');
        $st->execute([$id]); return $st->fetch() ?: null;
    }
    public static function check():bool { return !empty($_SESSION['user_id']); }
    public static function isAdmin():bool { $u=self::user(); return $u && $u['role']==='ADMIN'; }
    public static function can(string $permission):bool {
        $u=self::user(); if(!$u)return false;
        $st=Database::connection()->prepare('SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id JOIN roles r ON r.id=rp.role_id WHERE r.name=? AND p.name=?');
        $st->execute([$u['role'],$permission]); return (int)$st->fetchColumn()>0;
    }
    public static function canAccessManagement():bool { return self::can('dashboard.view'); }
    public static function login(int $id):void { session_regenerate_id(true); $_SESSION['user_id']=$id; }
    public static function logout():void { $_SESSION=[]; if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); } session_destroy(); }
}
