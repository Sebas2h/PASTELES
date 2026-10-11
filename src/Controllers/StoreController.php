<?php
namespace App\Controllers;

use App\Helpers\{Auth,Csrf,Flash,View,WheelConfig};
use App\Middlewares\AuthMiddleware;
use App\Repositories\StoreRepository;

final class StoreController
{
    public function index(): void
    {
        AuthMiddleware::handle();
        $repo = new StoreRepository();
        $repo->ensureSchema();
        $user = Auth::user();
        $userId = (int)$user['id'];
        $wheelConfig = WheelConfig::current();
        $wheelPrizes = WheelConfig::prizes($wheelConfig);
        $spinResult = $this->consumeSpinResult();
        $wheelSegments = $spinResult['wheel_segments'] ?? WheelConfig::displaySegments($wheelPrizes);
        $wheelCost = $repo->nextSpinCost($userId);
        View::render('cliente/store', [
            'title' => 'Tienda de puntos | Dulce Naranja',
            'items' => $repo->activeItems(),
            'redemptions' => $repo->userRedemptions((int)$user['id']),
            'spinResult' => $spinResult,
            'wheelCost' => $wheelCost,
            'wheelPrizes' => $wheelPrizes,
            'wheelSegments' => $wheelSegments,
            'wheelGradient' => $spinResult['wheel_gradient'] ?? WheelConfig::gradient($wheelPrizes),
        ]);
    }

    public function redeem(string $id): void
    {
        AuthMiddleware::handle();
        Csrf::validate();

        $repo = new StoreRepository();
        $repo->ensureSchema();

        try {
            $code = $repo->redeem((int)Auth::user()['id'], (int)$id);
            Flash::set('success', 'Canje realizado. Tu código es '.$code.'.');
        } catch (\Throwable $e) {
            Flash::set('error', $e->getMessage());
        }

        header('Location: /tienda');
        exit;
    }

    public function spin(): void
    {
        AuthMiddleware::handle();
        Csrf::validate();
        $wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

        try {
            $repo = new StoreRepository();
            $repo->ensureSchema();
            $userId = (int)Auth::user()['id'];
            $result = $repo->spin($userId);
            $_SESSION['_wheel_pending_code'] = $result['code'];
            $_SESSION['_store_wheel_result'] = $result;
            if ($wantsJson) {
                $pdo = \App\Config\Database::connection();
                $wheelSegments = $result['wheel_segments'];
                $wheelGradient = $result['wheel_gradient'];
                unset($result['wheel_segments'], $result['wheel_gradient']);
                $balance = $pdo->prepare('SELECT points_balance FROM users WHERE id=?');
                $balance->execute([$userId]);
                $stock = $pdo->query('SELECT stock FROM store_items WHERE id=4')->fetchColumn();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => true,
                    'result' => $result,
                    'balance' => (int)$balance->fetchColumn(),
                    'next_cost' => $repo->nextSpinCost($userId),
                    'stock' => $stock === null ? null : (int)$stock,
                    'wheel' => ['gradient' => $wheelGradient, 'segments' => $wheelSegments],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                exit;
            }
        } catch (\Throwable $e) {
            if ($wantsJson) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
            Flash::set('error', $e->getMessage());
        }

        header('Location: /tienda');
        exit;
    }

    public function acceptSpin(): void
    {
        AuthMiddleware::handle();
        Csrf::validate();
        $wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        try {
            $repo = new StoreRepository();
            $repo->ensureSchema();
            $accepted = $repo->acceptPendingSpin((int)Auth::user()['id'], trim((string)($_POST['code'] ?? '')));
            unset($_SESSION['_wheel_pending_code'], $_SESSION['_store_wheel_result']);
            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'accepted' => $accepted], JSON_THROW_ON_ERROR);
                exit;
            }
        } catch (\Throwable $e) {
            if ($wantsJson) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        header('Location: /tienda');
        exit;
    }

    private function consumeSpinResult(): ?array
    {
        $result = $_SESSION['_store_wheel_result'] ?? null;
        return is_array($result) ? $result : null;
    }
}
