<?php
namespace App\Middlewares;

use App\Helpers\Auth;

final class AdminMiddleware
{
    public static function handle(): void
    {
        AuthMiddleware::handle();
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $user = Auth::user();

        // Gestión de usuarios queda reservada exclusivamente a ADMIN y MODERADOR.
        if (str_starts_with($path, '/admin/users') && !in_array($user['role'] ?? '', ['ADMIN','MODERADOR'], true)) {
            http_response_code(403);
            exit('Acceso denegado: solo ADMIN y MODERADOR pueden gestionar usuarios.');
        }

        $permission = match (true) {
            str_starts_with($path, '/admin/products'),
            str_starts_with($path, '/admin/categories') => 'products.manage',
            str_starts_with($path, '/admin/orders') => 'orders.manage',
            str_starts_with($path, '/admin/rewards') => 'rewards.manage',
            str_starts_with($path, '/admin/users') => 'users.manage',
            str_starts_with($path, '/admin/settings') => 'settings.manage',
            str_starts_with($path, '/admin/reports') => 'reports.view',
            default => 'dashboard.view',
        };

        if (!Auth::can($permission)) {
            http_response_code(403);
            exit('Acceso denegado: tu rol no tiene este permiso.');
        }
    }
}
