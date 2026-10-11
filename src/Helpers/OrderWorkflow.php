<?php
namespace App\Helpers;

use PDO;

final class OrderWorkflow
{
    public static function ensureSchema(PDO $pdo): void
    {
        $column = $pdo->query("SHOW COLUMNS FROM orders LIKE 'delivery_method'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_method ENUM('RETIRO','DOMICILIO') NOT NULL DEFAULT 'RETIRO' AFTER status_id");
        }

        $pdo->exec(
            "INSERT IGNORE INTO order_statuses(name) VALUES
             ('PENDIENTE'),('LISTO PARA RETIRAR'),('ENVIADO'),('RECIBIDO'),('CANCELADO')"
        );
    }

    public static function customerStatus(array $order): string
    {
        $status = strtoupper((string)($order['status_name'] ?? 'PENDIENTE'));
        $method = strtoupper((string)($order['delivery_method'] ?? 'RETIRO'));

        return match ($status) {
            'LISTO' => $method === 'DOMICILIO' ? 'ENVIADO' : 'LISTO PARA RETIRAR',
            'LISTO PARA RETIRAR' => 'LISTO PARA RETIRAR',
            'ENVIADO' => 'ENVIADO',
            'ENTREGADO', 'RECIBIDO' => 'RECIBIDO',
            'CANCELADO' => 'CANCELADO',
            default => 'PENDIENTE',
        };
    }

    public static function allowedStatusNames(array $order): array
    {
        $status = strtoupper((string)($order['status_name'] ?? 'PENDIENTE'));
        $next = strtoupper((string)($order['delivery_method'] ?? 'RETIRO')) === 'DOMICILIO'
            ? 'ENVIADO'
            : 'LISTO PARA RETIRAR';

        if (in_array($status, ['RECIBIDO', 'ENTREGADO'], true)) return ['RECIBIDO'];
        if ($status === 'CANCELADO') return ['CANCELADO'];
        if (in_array($status, ['LISTO', 'LISTO PARA RETIRAR', 'ENVIADO'], true)) {
            return array_values(array_unique([$status === 'LISTO' ? $next : $status, 'RECIBIDO', 'CANCELADO']));
        }
        if (in_array($status, ['CONFIRMADO', 'PREPARANDO'], true)) return [$status, $next, 'CANCELADO'];
        return ['PENDIENTE', $next, 'CANCELADO'];
    }
}
