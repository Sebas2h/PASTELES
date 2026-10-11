<?php
namespace App\Controllers;

use App\Config\Database;
use App\Helpers\View;
use App\Middlewares\AdminMiddleware;
use DateTimeImmutable;

final class AdminController
{
    public function dashboard(): void
    {
        AdminMiddleware::handle();

        $today = new DateTimeImmutable('today', new \DateTimeZone('America/Guayaquil'));
        $period = (string)($_GET['period'] ?? 'daily');
        $allowedPeriods = ['daily', 'weekly', 'biweekly', 'monthly', 'range'];
        if (!in_array($period, $allowedPeriods, true)) $period = 'daily';

        $rangeError = '';
        $dateFromInput = '';
        $dateToInput = '';
        if ($period === 'range') {
            $dateFromInput = trim((string)($_GET['date_from'] ?? ''));
            $dateToInput = trim((string)($_GET['date_to'] ?? ''));
            $rangeStart = $this->parseDate($dateFromInput);
            $rangeEnd = $this->parseDate($dateToInput);

            if (!$rangeStart || !$rangeEnd) {
                $rangeError = 'Selecciona las dos fechas del rango.';
            } elseif ($rangeStart >= $rangeEnd) {
                $rangeError = 'El rango debe tener dos fechas distintas y la fecha inicial debe ser anterior.';
            } elseif ($rangeStart > $today || $rangeEnd > $today) {
                $rangeError = 'Las fechas no pueden ser posteriores a hoy.';
            }

        }

        $dataPeriod = $rangeError !== '' ? 'daily' : $period;
        $start = match ($dataPeriod) {
            'weekly' => $today->modify('-6 days'),
            'biweekly' => $today->modify('-14 days'),
            'monthly' => $today->modify('-29 days'),
            'range' => $rangeStart,
            default => $today,
        };
        $end = $dataPeriod === 'range' ? $rangeEnd : $today;
        $startDate = $start->format('Y-m-d');
        $endExclusive = $end->modify('+1 day')->format('Y-m-d');
        $periodLabel = match ($dataPeriod) {
            'weekly' => 'Últimos 7 días',
            'biweekly' => 'Últimos 15 días',
            'monthly' => 'Últimos 30 días',
            'range' => $start->format('d/m/Y').' – '.$end->format('d/m/Y'),
            default => 'Hoy',
        };

        $pdo = Database::connection();
        $countProducts = $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
        $countClients = $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='COMPRADOR'")->fetchColumn();

        $ordersQuery = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE created_at >= ? AND created_at < ?');
        $ordersQuery->execute([$startDate, $endExclusive]);
        $pendingQuery = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN order_statuses s ON s.id=o.status_id WHERE s.name='PENDIENTE' AND o.created_at >= ? AND o.created_at < ?");
        $pendingQuery->execute([$startDate, $endExclusive]);
        $salesQuery = $pdo->prepare('SELECT COALESCE(SUM(total),0) FROM orders WHERE created_at >= ? AND created_at < ?');
        $salesQuery->execute([$startDate, $endExclusive]);

        $metrics = [
            'products' => (int)$countProducts,
            'clients' => (int)$countClients,
            'orders' => (int)$ordersQuery->fetchColumn(),
            'pending' => (int)$pendingQuery->fetchColumn(),
            'sales' => (float)$salesQuery->fetchColumn(),
        ];
        $rewards = $pdo->query("SELECT name,points_required,discount_percent FROM rewards WHERE status='ACTIVE' ORDER BY points_required")->fetchAll();

        View::render('admin/dashboard', [
            'title' => 'Panel administrativo',
            'metrics' => $metrics,
            'rewards' => $rewards,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'rangeError' => $rangeError,
            'dateFromInput' => $dateFromInput,
            'dateToInput' => $dateToInput,
            'today' => $today->format('Y-m-d'),
        ]);
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
