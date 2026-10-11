<?php
namespace App\Controllers;

use App\Config\Database;
use App\Helpers\{Csrf,Flash,View,WheelConfig};
use App\Middlewares\AdminMiddleware;

final class AdminRewardController
{
    public function index(): void
    {
        AdminMiddleware::handle();
        View::render('admin/rewards', [
            'title' => 'Recompensas',
            'rewards' => Database::connection()->query('SELECT * FROM rewards ORDER BY points_required')->fetchAll(),
            'wheelConfig' => WheelConfig::current(),
        ]);
    }

    public function store(): void
    {
        AdminMiddleware::handle();
        Csrf::validate();
        $stmt = Database::connection()->prepare("INSERT INTO rewards(name,points_required,discount_percent,status) VALUES(?,?,?,'ACTIVE')");
        $stmt->execute([trim($_POST['name'] ?? ''), max(1, (int)($_POST['points_required'] ?? 1)), min(100, max(0, (float)($_POST['discount_percent'] ?? 0)))]);
        Flash::set('success', 'Recompensa creada.');
        header('Location: /admin/rewards');
        exit;
    }

    public function saveWheel(): void
    {
        AdminMiddleware::handle();
        Csrf::validate();
        try {
            WheelConfig::save(
                max(1, (int)($_POST['first_cost'] ?? 150)),
                max(1, (int)($_POST['respin_cost'] ?? 10)),
                $_POST['probabilities'] ?? []
            );
            Flash::set('success', 'Configuración de la ruleta guardada.');
        } catch (\InvalidArgumentException $e) {
            Flash::set('error', $e->getMessage());
        }
        header('Location: /admin/rewards');
        exit;
    }

    public function toggle(string $id): void
    {
        AdminMiddleware::handle();
        Csrf::validate();
        Database::connection()->prepare("UPDATE rewards SET status=IF(status='ACTIVE','INACTIVE','ACTIVE') WHERE id=?")->execute([(int)$id]);
        header('Location: /admin/rewards');
        exit;
    }
}
