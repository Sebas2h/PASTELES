<?php
namespace App\Config;
use App\Helpers\Env; use PDO; use PDOException;
final class Database {
    private static ?PDO $pdo=null;
    public static function connection(): PDO {
        if (self::$pdo) return self::$pdo;
        $dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', Env::get('DB_HOST','127.0.0.1'), Env::get('DB_PORT','3306'), Env::get('DB_NAME','pasteleria_db'));
        try {
            self::$pdo=new PDO($dsn, Env::get('DB_USER','root'), Env::get('DB_PASSWORD',''), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
            return self::$pdo;
        } catch (PDOException $e) { http_response_code(500); exit('No se pudo conectar a la base de datos. Revisa tu archivo .env y ejecuta database/schema.sql.'); }
    }
}
