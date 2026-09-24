<?php
namespace App\Controllers;

use App\Helpers\{View,Csrf,Flash,Auth};
use App\Middlewares\AdminMiddleware;
use App\Config\Database;
use App\Services\RoleChangeService;

final class AdminUserController
{
    public function index(): void
    {
        AdminMiddleware::handle();
        $users = Database::connection()->query(
            'SELECT u.id,u.first_name,u.last_name,u.username,u.email,u.status,r.name role,u.created_at
             FROM users u
             JOIN roles r ON r.id=u.role_id
             ORDER BY u.created_at DESC'
        )->fetchAll();

        View::render('admin/users/index', [
            'title' => 'Usuarios',
            'users' => $users,
        ]);
    }

    public function status(string $id): void
    {
        AdminMiddleware::handle();
        Csrf::validate();

        $actor = Auth::user();
        $id = (int)$id;
        $status = (string)($_POST['status'] ?? '');

        if (!in_array($status, ['ACTIVE','INACTIVE','BANNED'], true)) {
            Flash::set('error', 'Estado inválido.');
            header('Location: /admin/users');
            exit;
        }

        if ($id === (int)$actor['id'] && $status !== 'ACTIVE') {
            Flash::set('error', 'No puedes desactivar o banear tu propia cuenta.');
            header('Location: /admin/users');
            exit;
        }

        // Un moderador administra estados, pero nunca puede suspender al administrador.
        if (($actor['role'] ?? '') === 'MODERADOR') {
            $check = Database::connection()->prepare(
                'SELECT r.name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? LIMIT 1'
            );
            $check->execute([$id]);
            if ($check->fetchColumn() === 'ADMIN') {
                Flash::set('error', 'El moderador no puede modificar el estado de una cuenta ADMIN.');
                header('Location: /admin/users');
                exit;
            }
        }

        Database::connection()->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status, $id]);
        Flash::set('success', 'Estado actualizado.');
        header('Location: /admin/users');
        exit;
    }

    public function requestRole(string $id): void
    {
        AdminMiddleware::handle();
        $this->requireAdminForRoles();
        Csrf::validate();

        try {
            $masked = (new RoleChangeService())->request(
                (int)Auth::user()['id'],
                (int)$id,
                (string)($_POST['role'] ?? '')
            );
            $_SESSION['_role_change_masked'] = $masked;
            header('Location: /admin/users/role/verify');
        } catch (\Throwable $e) {
            Flash::set('error', 'No se pudo enviar el código: '.$e->getMessage());
            header('Location: /admin/users');
        }
        exit;
    }

    public function verifyRoleForm(): void
    {
        AdminMiddleware::handle();
        $this->requireAdminForRoles();

        $service = new RoleChangeService();
        if (!$service->pending()) {
            header('Location: /admin/users');
            exit;
        }

        View::render('admin/users/verify-role', [
            'title' => 'Confirmar cambio de rol',
            'maskedEmail' => $_SESSION['_role_change_masked'] ?? '',
        ]);
    }

    public function verifyRole(): void
    {
        AdminMiddleware::handle();
        $this->requireAdminForRoles();
        Csrf::validate();

        if ((new RoleChangeService())->verify(
            (int)Auth::user()['id'],
            trim((string)($_POST['code'] ?? ''))
        )) {
            unset($_SESSION['_role_change_masked']);
            Flash::set('success', 'Rol actualizado correctamente.');
            header('Location: /admin/users');
        } else {
            Flash::set('error', 'Código incorrecto, vencido o con demasiados intentos.');
            header('Location: /admin/users/role/verify');
        }
        exit;
    }

    private function requireAdminForRoles(): void
    {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('Acceso denegado: solo el ADMIN puede gestionar roles.');
        }
    }
}
