<?php
namespace App\Repositories;

use App\Config\Database;
use PDO;

final class StoreRepository
{
    public function ensureSchema(): void
    {
        $pdo = Database::connection();

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS store_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                description VARCHAR(255) NOT NULL,
                kind ENUM('COUPON','BENEFIT') NOT NULL DEFAULT 'COUPON',
                points_cost INT NOT NULL,
                discount_percent DECIMAL(5,2) NULL,
                stock INT NULL,
                status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_store_items_status_points(status, points_cost)
            ) ENGINE=InnoDB"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS store_redemptions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                item_id INT UNSIGNED NOT NULL,
                code VARCHAR(32) NOT NULL,
                points_spent INT NOT NULL,
                status ENUM('AVAILABLE','USED','CANCELLED') NOT NULL DEFAULT 'AVAILABLE',
                redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                used_at TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_store_redemption_user FOREIGN KEY(user_id) REFERENCES users(id),
                CONSTRAINT fk_store_redemption_item FOREIGN KEY(item_id) REFERENCES store_items(id),
                UNIQUE KEY uq_store_redemption_code(code),
                INDEX idx_store_redemption_user(user_id, status)
            ) ENGINE=InnoDB"
        );

        $seed = $pdo->prepare(
            "INSERT IGNORE INTO store_items
             (id,name,description,kind,points_cost,discount_percent,stock,status)
             VALUES (?,?,?,?,?,?,?, 'ACTIVE')"
        );

        $seed->execute([1, 'Cupón 5% OFF', 'Cupón beta para obtener 5% de descuento en una compra.', 'COUPON', 100, 5, null]);
        $seed->execute([2, 'Cupón 10% OFF', 'Cupón beta para obtener 10% de descuento en una compra.', 'COUPON', 220, 10, null]);
        $seed->execute([3, 'Cupón 15% OFF', 'Cupón beta premium para obtener 15% de descuento.', 'COUPON', 380, 15, null]);
        $seed->execute([4, 'Detalle sorpresa', 'Beneficio beta reservado para una sorpresa especial de Dulce Naranja.', 'BENEFIT', 500, null, 25]);
    }

    public function activeItems(): array
    {
        $s = Database::connection()->query(
            "SELECT * FROM store_items WHERE status='ACTIVE' ORDER BY points_cost ASC, id ASC"
        );
        return $s->fetchAll();
    }

    public function userRedemptions(int $userId): array
    {
        $s = Database::connection()->prepare(
            "SELECT sr.*,si.name,si.kind,si.discount_percent
             FROM store_redemptions sr
             JOIN store_items si ON si.id=sr.item_id
             WHERE sr.user_id=?
             ORDER BY sr.redeemed_at DESC
             LIMIT 20"
        );
        $s->execute([$userId]);
        return $s->fetchAll();
    }

    public function redeem(int $userId, int $itemId): string
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $itemStmt = $pdo->prepare(
                "SELECT * FROM store_items WHERE id=? AND status='ACTIVE' LIMIT 1 FOR UPDATE"
            );
            $itemStmt->execute([$itemId]);
            $item = $itemStmt->fetch();

            if (!$item) {
                throw new \RuntimeException('La recompensa ya no está disponible.');
            }
            if ($item['stock'] !== null && (int)$item['stock'] <= 0) {
                throw new \RuntimeException('Esta recompensa se quedó sin stock.');
            }

            $userStmt = $pdo->prepare('SELECT points_balance FROM users WHERE id=? LIMIT 1 FOR UPDATE');
            $userStmt->execute([$userId]);
            $balance = $userStmt->fetchColumn();
            if ($balance === false) {
                throw new \RuntimeException('Usuario no encontrado.');
            }

            $cost = (int)$item['points_cost'];
            if ((int)$balance < $cost) {
                throw new \RuntimeException('No tienes puntos suficientes para este canje.');
            }

            $code = $this->generateCode($pdo);

            $pdo->prepare('UPDATE users SET points_balance=points_balance-? WHERE id=?')
                ->execute([$cost, $userId]);

            if ($item['stock'] !== null) {
                $pdo->prepare('UPDATE store_items SET stock=stock-1 WHERE id=? AND stock>0')
                    ->execute([$itemId]);
            }

            $pdo->prepare(
                "INSERT INTO store_redemptions(user_id,item_id,code,points_spent,status)
                 VALUES(?,?,?,?,'AVAILABLE')"
            )->execute([$userId, $itemId, $code, $cost]);

            $pdo->prepare(
                "INSERT INTO point_movements(user_id,order_id,type,points,description)
                 VALUES(?,NULL,'REDEEM',?,?)"
            )->execute([
                $userId,
                -$cost,
                'Canje en Tienda Beta: '.$item['name'].' · Código '.$code,
            ]);

            $pdo->commit();
            return $code;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function generateCode(PDO $pdo): string
    {
        $check = $pdo->prepare('SELECT COUNT(*) FROM store_redemptions WHERE code=?');
        do {
            $code = 'DN-'.strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
            $check->execute([$code]);
        } while ((int)$check->fetchColumn() > 0);

        return $code;
    }
}
