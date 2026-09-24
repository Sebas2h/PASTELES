<?php
namespace App\Controllers;

use App\Helpers\{Auth,Csrf,Flash,View};
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

        View::render('cliente/store', [
            'title' => 'Tienda de puntos | Dulce Naranja',
            'items' => $repo->activeItems(),
            'redemptions' => $repo->userRedemptions((int)$user['id']),
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
}
