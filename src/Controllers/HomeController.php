<?php
namespace App\Controllers;

use App\Helpers\{View,Settings,Auth};
use App\Repositories\{ProductRepository,CategoryRepository};
use App\Middlewares\AuthMiddleware;

final class HomeController {
    /**
     * Portada pública de la pastelería.
     * Solo se muestra cuando no existe una sesión iniciada.
     */
    public function home(): void {
        if (Auth::check()) {
            header('Location: /dashboard');
            exit;
        }

        View::render('cliente/home', ['title' => 'Dulce Naranja | Pastelería']);
    }

    /** Mantiene compatibilidad con enlaces antiguos a /catalog. */
    public function catalog(): void {
        header('Location: /catalogo', true, 302);
        exit;
    }

    /** Interfaz independiente del catálogo de pasteles. */
    public function catalogo(): void {
        AuthMiddleware::handle();

        $products = (new ProductRepository())->all(true);
        $categories = (new CategoryRepository())->all(true);
        $rate = Settings::pointsPerUsd();

        foreach ($products as &$item) {
            $item['display_points'] = (int) floor((float) $item['price'] * $rate);
        }
        unset($item);

        View::render('cliente/catalogo', [
            'title' => 'Catálogo | Dulce Naranja',
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function product(string $id): void {
        AuthMiddleware::handle();
        $p = (new ProductRepository())->find((int) $id);
        if (!$p) {
            http_response_code(404);
            exit('Producto no encontrado');
        }
        $p['display_points'] = (int) floor((float) $p['price'] * Settings::pointsPerUsd());
        View::render('cliente/product', ['title' => $p['name'], 'product' => $p]);
    }

    /**
     * Punto único de entrada después del login.
     * Ya no existe un segundo dashboard de cliente.
     *
     * - Usuarios con acceso a gestión: panel administrativo.
     * - Compradores: catálogo.
     */
    public function dashboard(): void {
        AuthMiddleware::handle();

        if (Auth::canAccessManagement()) {
            header('Location: /admin');
            exit;
        }

        header('Location: /catalogo');
        exit;
    }
}
