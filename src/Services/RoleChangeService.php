<?php
namespace App\Services;

use App\Config\Database;

final class RoleChangeService
{
    private const KEY = '_role_change';

    public function request(int $actorId, int $userId, string $role): string
    {
        // ADMIN es un rol protegido: no se puede asignar desde Gestión de usuarios.
        if (!in_array($role, ['VENDEDOR','MODERADOR','COMPRADOR'], true)) {
            throw new \RuntimeException('Rol inválido o protegido.');
        }
        if ($actorId === $userId) {
            throw new \RuntimeException('No puedes cambiar tu propio rol.');
        }

        $pdo = Database::connection();

        $target = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? LIMIT 1');
        $target->execute([$userId]);
        if ($target->fetchColumn() === 'ADMIN') {
            throw new \RuntimeException('La cuenta ADMIN está protegida y su rol no puede modificarse desde aquí.');
        }

        $s = $pdo->prepare(
            "SELECT email FROM users u
             JOIN roles r ON r.id=u.role_id
             WHERE r.name='ADMIN' AND u.status='ACTIVE' AND u.email<>''
             ORDER BY u.id LIMIT 1"
        );
        $s->execute();
        $email = (string)$s->fetchColumn();

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('No existe un administrador activo con correo válido.');
        }

        $code = (string)random_int(100000, 999999);
        $_SESSION[self::KEY] = [
            'actor' => $actorId,
            'user' => $userId,
            'role' => $role,
            'hash' => password_hash($code, PASSWORD_DEFAULT),
            'expires' => time()+600,
            'attempts' => 0,
            'email' => $email,
        ];

        (new EmailService())->sendVerificationCode(
            $email,
            $code,
            'cambio de rol de un usuario. Si tú realizaste la solicitud, ingresa el código; de lo contrario no lo compartas con nadie'
        );

        return $this->mask($email);
    }

    public function verify(int $actorId, string $code): bool
    {
        $s = $_SESSION[self::KEY] ?? null;
        if (!$s || $s['actor'] !== $actorId || time() > $s['expires'] || $s['attempts'] >= 5) {
            unset($_SESSION[self::KEY]);
            return false;
        }

        $_SESSION[self::KEY]['attempts']++;
        if (!password_verify($code, $s['hash'])) {
            return false;
        }

        // Defensa adicional: incluso una sesión de verificación manipulada nunca asigna ADMIN.
        if (!in_array($s['role'], ['VENDEDOR','MODERADOR','COMPRADOR'], true)) {
            unset($_SESSION[self::KEY]);
            return false;
        }

        $pdo = Database::connection();
        $target = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? LIMIT 1');
        $target->execute([(int)$s['user']]);
        if ($target->fetchColumn() === 'ADMIN') {
            unset($_SESSION[self::KEY]);
            return false;
        }

        $q = $pdo->prepare('UPDATE users SET role_id=(SELECT id FROM roles WHERE name=?) WHERE id=?');
        $q->execute([$s['role'], $s['user']]);
        unset($_SESSION[self::KEY]);
        return true;
    }

    public function pending(): ?array
    {
        return $_SESSION[self::KEY] ?? null;
    }

    private function mask(string $e): string
    {
        [$l,$d] = array_pad(explode('@', $e, 2), 2, '');
        return substr($l,0,2).str_repeat('*',max(2,strlen($l)-2)).'@'.$d;
    }
}
