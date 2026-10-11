<?php
namespace App\Repositories;

use App\Config\Database;
use App\Helpers\WheelConfig;
use PDO;

final class StoreRepository
{
    private const WHEEL_ITEM_ID = 4;
    private const WHEEL_COST = 150;

    public function ensureSchema(): void
    {
        $pdo = Database::connection();

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS store_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                description VARCHAR(255) NOT NULL,
                kind ENUM('COUPON','BENEFIT','WHEEL') NOT NULL DEFAULT 'COUPON',
                points_cost INT NOT NULL,
                discount_percent DECIMAL(5,2) NULL,
                stock INT NULL,
                status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_store_items_status_points(status, points_cost)
            ) ENGINE=InnoDB"
        );

        $kindColumn = $pdo->query("SHOW COLUMNS FROM store_items LIKE 'kind'")->fetch();
        if ($kindColumn && !str_contains($kindColumn['Type'], "'WHEEL'")) {
            $pdo->exec("ALTER TABLE store_items MODIFY kind ENUM('COUPON','BENEFIT','WHEEL') NOT NULL DEFAULT 'COUPON'");
        }

        // Coupon cards are a storefront view of the administrator's rewards.
        // Keep store_items as the redeemable record so existing redemption FKs
        // and history remain valid, while reward_id makes the relationship explicit.
        $storeColumns = array_column($pdo->query('SHOW COLUMNS FROM store_items')->fetchAll(), 'Field');
        if (!in_array('reward_id', $storeColumns, true)) {
            $pdo->exec('ALTER TABLE store_items ADD COLUMN reward_id INT UNSIGNED NULL');
            $pdo->exec('ALTER TABLE store_items ADD UNIQUE KEY uq_store_items_reward(reward_id)');
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS store_redemptions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                item_id INT UNSIGNED NOT NULL,
                code VARCHAR(32) NOT NULL,
                points_spent INT NOT NULL,
                status ENUM('PENDING','AVAILABLE','USED','CANCELLED') NOT NULL DEFAULT 'AVAILABLE',
                prize_name VARCHAR(120) NULL,
                prize_type VARCHAR(20) NULL,
                discount_percent DECIMAL(5,2) NULL,
                redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                used_at TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_store_redemption_user FOREIGN KEY(user_id) REFERENCES users(id),
                CONSTRAINT fk_store_redemption_item FOREIGN KEY(item_id) REFERENCES store_items(id),
                UNIQUE KEY uq_store_redemption_code(code),
                INDEX idx_store_redemption_user(user_id, status)
            ) ENGINE=InnoDB"
        );

        $statusColumn = $pdo->query("SHOW COLUMNS FROM store_redemptions LIKE 'status'")->fetch();
        if ($statusColumn && !str_contains($statusColumn['Type'], "'PENDING'")) {
            $pdo->exec("ALTER TABLE store_redemptions MODIFY status ENUM('PENDING','AVAILABLE','USED','CANCELLED') NOT NULL DEFAULT 'AVAILABLE'");
        }

        $redemptionColumns = array_column($pdo->query('SHOW COLUMNS FROM store_redemptions')->fetchAll(), 'Field');
        foreach ([
            'prize_name' => 'VARCHAR(120) NULL',
            'prize_type' => 'VARCHAR(20) NULL',
            'discount_percent' => 'DECIMAL(5,2) NULL',
        ] as $column => $definition) {
            if (!in_array($column, $redemptionColumns, true)) {
                $pdo->exec("ALTER TABLE store_redemptions ADD COLUMN $column $definition");
            }
        }

        // Keep historical surprise redemptions recognizable after renaming item 4.
        $pdo->prepare(
            "UPDATE store_redemptions SET prize_name='Detalle sorpresa',prize_type='BENEFIT'
             WHERE item_id=? AND prize_name IS NULL"
        )->execute([self::WHEEL_ITEM_ID]);

        $seed = $pdo->prepare(
            "INSERT IGNORE INTO store_items
             (id,name,description,kind,points_cost,discount_percent,stock,status)
             VALUES (?,?,?,?,?,?,?, 'ACTIVE')"
        );

        $seed->execute([1, 'Cupón 5% OFF', 'Cupón beta para obtener 5% de descuento en una compra.', 'COUPON', 100, 5, null]);
        $seed->execute([2, 'Cupón 10% OFF', 'Cupón beta para obtener 10% de descuento en una compra.', 'COUPON', 220, 10, null]);
        $seed->execute([3, 'Cupón 15% OFF', 'Cupón beta premium para obtener 15% de descuento.', 'COUPON', 380, 15, null]);
        $seed->execute([self::WHEEL_ITEM_ID, 'Ruleta de premios', 'Premios sorpresa en cada giro.', 'WHEEL', self::WHEEL_COST, null, 25]);
        $pdo->prepare(
            "UPDATE store_items SET name='Ruleta de premios',description='Premios sorpresa en cada giro.',
             kind='WHEEL',discount_percent=NULL,status='ACTIVE' WHERE id=?"
        )->execute([self::WHEEL_ITEM_ID]);

        $this->syncCouponsWithRewards($pdo);
    }

    private function syncCouponsWithRewards(PDO $pdo): void
    {
        // Attach legacy seeded coupon rows to matching admin rewards once.
        $legacy = $pdo->query(
            "SELECT id, discount_percent FROM store_items
             WHERE kind='COUPON' AND reward_id IS NULL ORDER BY id"
        )->fetchAll();
        $match = $pdo->prepare(
            "SELECT id FROM rewards WHERE discount_percent=?
             AND NOT EXISTS (SELECT 1 FROM store_items WHERE reward_id=rewards.id)
             ORDER BY id LIMIT 1"
        );
        $attach = $pdo->prepare('UPDATE store_items SET reward_id=? WHERE id=? AND reward_id IS NULL');
        foreach ($legacy as $item) {
            $match->execute([$item['discount_percent']]);
            $rewardId = $match->fetchColumn();
            if ($rewardId !== false) $attach->execute([(int)$rewardId, (int)$item['id']]);
        }

        // Anything previously linked is refreshed or hidden according to the
        // admin status. Unlinked legacy coupons are hidden, never deleted.
        $pdo->exec(
            "UPDATE store_items si LEFT JOIN rewards r ON r.id=si.reward_id
             SET si.status=IF(r.status='ACTIVE','ACTIVE','INACTIVE')
             WHERE si.kind='COUPON' AND si.reward_id IS NOT NULL"
        );
        $pdo->exec(
            "UPDATE store_items SET status='INACTIVE'
             WHERE kind='COUPON' AND reward_id IS NULL"
        );

        $rewards = $pdo->query('SELECT * FROM rewards ORDER BY id')->fetchAll();
        $find = $pdo->prepare("SELECT id FROM store_items WHERE reward_id=? LIMIT 1");
        $update = $pdo->prepare(
            "UPDATE store_items SET name=?,description=?,kind='COUPON',points_cost=?,
             discount_percent=?,status=? WHERE id=?"
        );
        $insert = $pdo->prepare(
            "INSERT INTO store_items(name,description,kind,points_cost,discount_percent,stock,status,reward_id)
             VALUES(?,?,'COUPON',?,?,NULL,?,?)"
        );
        foreach ($rewards as $reward) {
            $name = trim((string)$reward['name']);
            $discount = (float)$reward['discount_percent'];
            $storeName = $name !== '' ? $name : rtrim(rtrim(number_format($discount, 2, '.', ''), '0'), '.').'% de descuento';
            $description = 'Cupón para obtener '.$discount.'% de descuento en una compra.';
            $status = $reward['status'] === 'ACTIVE' ? 'ACTIVE' : 'INACTIVE';
            $find->execute([(int)$reward['id']]);
            $itemId = $find->fetchColumn();
            if ($itemId !== false) {
                $update->execute([$storeName, $description, (int)$reward['points_required'], $discount, $status, (int)$itemId]);
            } else {
                $insert->execute([$storeName, $description, (int)$reward['points_required'], $discount, $status, (int)$reward['id']]);
            }
        }
    }

    public function activeItems(): array
    {
        return Database::connection()
            ->query("SELECT * FROM store_items WHERE status='ACTIVE' ORDER BY points_cost ASC, id ASC")
            ->fetchAll();
    }

    public function userRedemptions(int $userId): array
    {
        $s = Database::connection()->prepare(
            "SELECT sr.*,COALESCE(sr.prize_name,si.name) AS name,
                    COALESCE(sr.prize_type,si.kind) AS kind,
                    COALESCE(sr.discount_percent,si.discount_percent) AS discount_percent
             FROM store_redemptions sr
             JOIN store_items si ON si.id=sr.item_id
             WHERE sr.user_id=? AND sr.status<>'PENDING'
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
            $item = $this->lockActiveItem($pdo, $itemId);
            if (!$item || $item['kind'] === 'WHEEL') {
                throw new \RuntimeException('Esta recompensa no se puede canjear directamente.');
            }
            $this->assertInStock($item);
            $balance = $this->lockUserBalance($pdo, $userId);
            $cost = (int)$item['points_cost'];
            if ($balance < $cost) {
                throw new \RuntimeException('No tienes puntos suficientes para este canje.');
            }

            $code = $this->generateCode($pdo);
            $this->chargePoints($pdo, $userId, $cost, $item['name'], $code);
            if ($item['stock'] !== null) {
                $pdo->prepare('UPDATE store_items SET stock=stock-1 WHERE id=? AND stock>0')->execute([$itemId]);
            }
            $pdo->prepare(
                "INSERT INTO store_redemptions(user_id,item_id,code,points_spent,status,prize_name,prize_type,discount_percent)
                 VALUES(?,?,?,?,'AVAILABLE',?,?,?)"
            )->execute([$userId, $itemId, $code, $cost, $item['name'], $item['kind'], $item['discount_percent']]);

            $pdo->commit();
            return $code;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function spin(int $userId): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $item = $this->lockActiveItem($pdo, self::WHEEL_ITEM_ID);
            if (!$item || $item['kind'] !== 'WHEEL') {
                throw new \RuntimeException('La ruleta no está disponible.');
            }
            $this->assertInStock($item);
            $balance = $this->lockUserBalance($pdo, $userId);
            $pendingQuery = $pdo->prepare("SELECT id FROM store_redemptions WHERE user_id=? AND item_id=? AND status='PENDING' ORDER BY redeemed_at DESC LIMIT 1 FOR UPDATE");
            $pendingQuery->execute([$userId, self::WHEEL_ITEM_ID]);
            $pendingId = $pendingQuery->fetchColumn();
            $config = WheelConfig::current();
            $cost = (int)$config[$pendingId !== false ? 'respin_cost' : 'first_cost'];
            if ($balance < $cost) {
                throw new \RuntimeException('Necesitas '.$cost.' puntos para girar la ruleta.');
            }

            $prizes = WheelConfig::prizes($config);
            $roll = random_int(1, 10000);
            $threshold = 0;
            $prize = null;
            foreach ($prizes as $candidate) {
                $weight = (int)round((float)$candidate['probability'] * 100);
                if ($weight > 0 && $roll <= $threshold + $weight) {
                    $candidate['stop_angle'] = WheelConfig::displayStopAngle((string)$candidate['key'], $prizes);
                    $candidate['wheel_segments'] = WheelConfig::displaySegments($prizes);
                    $candidate['wheel_gradient'] = WheelConfig::gradient($prizes);
                    $prize = $candidate;
                    break;
                }
                $threshold += $weight;
            }
            if (!$prize) throw new \RuntimeException('La ruleta no tiene premios configurados.');

            $code = $this->generateCode($pdo);
            if ($pendingId !== false) {
                $pdo->prepare("DELETE FROM store_redemptions WHERE id=? AND status='PENDING'")->execute([(int)$pendingId]);
            }
            $this->chargePoints($pdo, $userId, $cost, 'Ruleta: '.$prize['name'], $code);
            if ($item['stock'] !== null) {
                $pdo->prepare('UPDATE store_items SET stock=stock-1 WHERE id=? AND stock>0')->execute([self::WHEEL_ITEM_ID]);
            }
            $pdo->prepare(
                "INSERT INTO store_redemptions(user_id,item_id,code,points_spent,status,prize_name,prize_type,discount_percent)
                 VALUES(?,?,?,?,'PENDING',?,?,?)"
            )->execute([$userId, self::WHEEL_ITEM_ID, $code, $cost, $prize['name'], $prize['type'], $prize['discount']]);

            $pdo->commit();
            unset($prize['key'], $prize['probability'], $prize['color']);
            return $prize + ['code' => $code, 'points_spent' => $cost];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function nextSpinCost(int $userId): int
    {
        $config = WheelConfig::current();
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM store_redemptions WHERE user_id=? AND item_id=? AND status='PENDING'");
        $stmt->execute([$userId, self::WHEEL_ITEM_ID]);
        return (int)$config[(int)$stmt->fetchColumn() > 0 ? 'respin_cost' : 'first_cost'];
    }

    public function acceptPendingSpin(int $userId, ?string $code = null): bool
    {
        $sql = "UPDATE store_redemptions SET status='AVAILABLE' WHERE user_id=? AND item_id=? AND status='PENDING'";
        $params = [$userId, self::WHEEL_ITEM_ID];
        if ($code !== null && $code !== '') { $sql .= ' AND code=?'; $params[] = $code; }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function acceptAbandonedSpin(int $userId, ?string $sessionCode): bool
    {
        $stmt = Database::connection()->prepare("SELECT code FROM store_redemptions WHERE user_id=? AND item_id=? AND status='PENDING' ORDER BY redeemed_at DESC LIMIT 1");
        $stmt->execute([$userId, self::WHEEL_ITEM_ID]);
        $code = $stmt->fetchColumn();
        return $code !== false && $code !== $sessionCode ? $this->acceptPendingSpin($userId, (string)$code) : false;
    }

    private function lockActiveItem(PDO $pdo, int $itemId): array|false
    {
        $stmt = $pdo->prepare("SELECT * FROM store_items WHERE id=? AND status='ACTIVE' LIMIT 1 FOR UPDATE");
        $stmt->execute([$itemId]);
        return $stmt->fetch();
    }

    private function assertInStock(array $item): void
    {
        if ($item['stock'] !== null && (int)$item['stock'] <= 0) {
            throw new \RuntimeException('Esta recompensa se quedó sin stock.');
        }
    }

    private function lockUserBalance(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare('SELECT points_balance FROM users WHERE id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$userId]);
        $balance = $stmt->fetchColumn();
        if ($balance === false) throw new \RuntimeException('Usuario no encontrado.');
        return (int)$balance;
    }

    private function chargePoints(PDO $pdo, int $userId, int $cost, string $description, string $code): void
    {
        $pdo->prepare('UPDATE users SET points_balance=points_balance-? WHERE id=?')->execute([$cost, $userId]);
        $pdo->prepare(
            "INSERT INTO point_movements(user_id,order_id,type,points,description)
             VALUES(?,NULL,'REDEEM',?,?)"
        )->execute([$userId, -$cost, 'Tienda de puntos: '.$description.' · Código '.$code]);
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
