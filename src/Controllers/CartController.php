<?php
namespace App\Controllers;

use App\Config\Database;
use App\Helpers\{Auth,Csrf,Flash,OrderWorkflow,Settings,View};
use App\Middlewares\AuthMiddleware;
use App\Repositories\ProductRepository;

final class CartController
{
    public function show(): void
    {
        $items = $this->items();
        $orders = [];
        if (Auth::check()) {
            $pdo = Database::connection();
            OrderWorkflow::ensureSchema($pdo);
            $stmt = $pdo->prepare('SELECT o.*,os.name status_name FROM orders o JOIN order_statuses os ON os.id=o.status_id WHERE o.user_id=? ORDER BY o.created_at DESC LIMIT 50');
            $stmt->execute([(int)Auth::user()['id']]);
            $orders = $stmt->fetchAll();
            $details = $pdo->prepare('SELECT product_name,unit_price,quantity,subtotal FROM order_items WHERE order_id=? ORDER BY id');
            foreach ($orders as &$order) {
                $details->execute([(int)$order['id']]);
                $order['items'] = $details->fetchAll();
                $order['items_count'] = array_sum(array_column($order['items'], 'quantity'));
                $order['customer_status'] = OrderWorkflow::customerStatus($order);
            }
            unset($order);
        }

        $buckets = ['pending' => [], 'completed' => [], 'cancelled' => []];
        foreach ($orders as $order) {
            if ($order['customer_status'] === 'RECIBIDO') $buckets['completed'][] = $order;
            elseif ($order['customer_status'] === 'CANCELADO') $buckets['cancelled'][] = $order;
            else $buckets['pending'][] = $order;
        }

        View::render('cliente/cart', [
            'title' => 'Mi carrito y compras',
            'items' => $items,
            'totals' => $this->totals($items),
            'orderBuckets' => $buckets,
        ]);
    }

    public function add(): void
    {
        Csrf::validate();
        $id = (int)($_POST['product_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $product = (new ProductRepository())->find($id);
        if (!$product || $product['status'] !== 'ACTIVE') {
            Flash::set('error', 'Producto no disponible.');
            header('Location: /catalog');
            exit;
        }
        $_SESSION['cart'][$id] = min((int)$product['stock'], ($_SESSION['cart'][$id] ?? 0) + $quantity);
        Flash::set('success', 'Producto agregado al carrito.');
        header('Location: '.($_SERVER['HTTP_REFERER'] ?? '/cart'));
    }

    public function update(): void
    {
        Csrf::validate();
        foreach ($_POST['qty'] ?? [] as $id => $quantity) {
            $quantity = (int)$quantity;
            if ($quantity <= 0) unset($_SESSION['cart'][(int)$id]);
            else $_SESSION['cart'][(int)$id] = $quantity;
        }
        Flash::set('success', 'Carrito actualizado.');
        header('Location: /cart');
    }

    public function checkout(): void
    {
        AuthMiddleware::handle();
        Csrf::validate();
        $items = $this->items();
        if (!$items) {
            Flash::set('error', 'Tu carrito está vacío.');
            header('Location: /cart');
            exit;
        }

        $pdo = Database::connection();
        OrderWorkflow::ensureSchema($pdo);
        $pdo->beginTransaction();
        try {
            $user = Auth::user();
            $userId = (int)$user['id'];
            $balanceQuery = $pdo->prepare('SELECT points_balance FROM users WHERE id=? FOR UPDATE');
            $balanceQuery->execute([$userId]);
            $pointsBalance = (int)$balanceQuery->fetchColumn();
            $subtotal = 0.0;
            foreach ($items as $item) {
                if ($item['quantity'] > $item['stock']) throw new \RuntimeException('Stock insuficiente para '.$item['name']);
                $subtotal += (float)$item['price'] * $item['quantity'];
            }

            $mode = (string)($_POST['reward_mode'] ?? 'none');
            $discount = 0.0;
            $pointsSpent = 0;
            $rewardId = 0;
            $promoRedemptionId = null;

            if ($mode === 'points') {
                $rewardId = (int)($_POST['reward_id'] ?? 0);
                $rewardQuery = $pdo->prepare("SELECT * FROM rewards WHERE id=? AND status='ACTIVE' FOR UPDATE");
                $rewardQuery->execute([$rewardId]);
                $reward = $rewardQuery->fetch();
                if (!$reward) throw new \RuntimeException('Selecciona una recompensa activa.');
                if ($pointsBalance < (int)$reward['points_required']) {
                    throw new \RuntimeException('No tienes puntos suficientes para esa recompensa.');
                }
                $pointsSpent = (int)$reward['points_required'];
                $discount = round($subtotal * ((float)$reward['discount_percent'] / 100), 2);
            } elseif ($mode === 'promo') {
                $promoCode = strtoupper(trim((string)($_POST['promo_code'] ?? '')));
                if (!preg_match('/^DN-[A-F0-9]{10}$/', $promoCode)) {
                    throw new \RuntimeException('Completa los 10 caracteres del código promocional.');
                }
                $promoQuery = $pdo->prepare(
                    "SELECT sr.id,sr.discount_percent,si.kind FROM store_redemptions sr
                     JOIN store_items si ON si.id=sr.item_id
                     WHERE sr.code=? AND sr.user_id=? AND sr.status='AVAILABLE' FOR UPDATE"
                );
                $promoQuery->execute([$promoCode, $userId]);
                $promo = $promoQuery->fetch();
                if (!$promo || $promo['kind'] !== 'COUPON' || (float)$promo['discount_percent'] <= 0) {
                    throw new \RuntimeException('El código no está disponible en tu billetera o ya fue usado.');
                }
                $promoRedemptionId = (int)$promo['id'];
                $discount = round($subtotal * ((float)$promo['discount_percent'] / 100), 2);
            }

            $deliveryMethod = ($_POST['delivery_method'] ?? 'RETIRO') === 'DOMICILIO' ? 'DOMICILIO' : 'RETIRO';
            $total = max(0, $subtotal - $discount);
            $statusId = (int)$pdo->query("SELECT id FROM order_statuses WHERE name='PENDIENTE' LIMIT 1")->fetchColumn();
            $insertOrder = $pdo->prepare('INSERT INTO orders(user_id,status_id,delivery_method,subtotal,discount,total,points_earned,points_spent) VALUES(?,?,?,?,?,?,?,?)');
            $pointsEarned = (int)floor($subtotal * Settings::pointsPerUsd());
            $insertOrder->execute([$userId, $statusId, $deliveryMethod, $subtotal, $discount, $total, $pointsEarned, $pointsSpent]);
            $orderId = (int)$pdo->lastInsertId();

            $insertItem = $pdo->prepare('INSERT INTO order_items(order_id,product_id,product_name,unit_price,quantity,subtotal) VALUES(?,?,?,?,?,?)');
            $reduceStock = $pdo->prepare('UPDATE products SET stock=stock-? WHERE id=? AND stock>=?');
            foreach ($items as $item) {
                $lineTotal = (float)$item['price'] * $item['quantity'];
                $insertItem->execute([$orderId, $item['id'], $item['name'], $item['price'], $item['quantity'], $lineTotal]);
                $reduceStock->execute([$item['quantity'], $item['id'], $item['quantity']]);
                if ($reduceStock->rowCount() !== 1) throw new \RuntimeException('El stock cambió durante la compra.');
            }

            if ($pointsSpent > 0) {
                $chargePoints = $pdo->prepare('UPDATE users SET points_balance=points_balance-? WHERE id=? AND points_balance>=?');
                $chargePoints->execute([$pointsSpent, $userId, $pointsSpent]);
                if ($chargePoints->rowCount() !== 1) throw new \RuntimeException('No tienes puntos suficientes para esa recompensa.');
                $pdo->prepare("INSERT INTO point_movements(user_id,order_id,type,points,description) VALUES(?,?,'REDEEM',?,?)")
                    ->execute([$userId, $orderId, -$pointsSpent, 'Canje de recompensa']);
                $pdo->prepare('INSERT INTO reward_redemptions(user_id,reward_id,order_id,points_spent,discount_amount) VALUES(?,?,?,?,?)')
                    ->execute([$userId, $rewardId, $orderId, $pointsSpent, $discount]);
            }

            if ($promoRedemptionId !== null) {
                $consumePromo = $pdo->prepare("UPDATE store_redemptions SET status='USED',used_at=NOW() WHERE id=? AND status='AVAILABLE'");
                $consumePromo->execute([$promoRedemptionId]);
                if ($consumePromo->rowCount() !== 1) throw new \RuntimeException('El código promocional ya fue usado.');
            }

            if ($pointsEarned > 0) {
                $pdo->prepare('UPDATE users SET points_balance=points_balance+? WHERE id=?')->execute([$pointsEarned, $userId]);
                $pdo->prepare("INSERT INTO point_movements(user_id,order_id,type,points,description) VALUES(?,?,'EARN',?,?)")
                    ->execute([$userId, $orderId, $pointsEarned, 'Puntos por compra']);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            Flash::set('success', "Pedido #$orderId creado. Ganaste $pointsEarned puntos.");
            header('Location: /cart');
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Flash::set('error', $error->getMessage());
            header('Location: /cart');
        }
    }

    private function items(): array
    {
        $repo = new ProductRepository();
        $items = [];
        foreach ($_SESSION['cart'] ?? [] as $id => $quantity) {
            if ($product = $repo->find((int)$id)) {
                $product['quantity'] = (int)$quantity;
                $items[] = $product;
            }
        }
        return $items;
    }

    private function totals(array $items): array
    {
        $subtotal = 0.0;
        foreach ($items as $item) $subtotal += (float)$item['price'] * $item['quantity'];
        $rewards = [];
        if (Auth::check()) {
            $user = Auth::user();
            $stmt = Database::connection()->prepare("SELECT * FROM rewards WHERE status='ACTIVE' AND points_required<=? ORDER BY points_required");
            $stmt->execute([$user['points_balance']]);
            $rewards = $stmt->fetchAll();
        }
        return ['subtotal' => $subtotal, 'rewards' => $rewards];
    }
}
