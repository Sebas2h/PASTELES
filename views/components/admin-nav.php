<?php
use App\Helpers\{Auth,View};

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$managementItems = [
    ['/admin/products','Productos','products.manage'],
    ['/admin/categories','Categorías','products.manage'],
    ['/admin/orders','Pedidos','orders.manage'],
    ['/admin/users','Usuarios','users.manage'],
    ['/admin/rewards','Recompensas','rewards.manage'],
    ['/admin/reports','Reportes','reports.view'],
    ['/admin/settings','Configuración','settings.manage'],
];
$activeSection = $currentPath === '/admin' ? '/admin' : ('/admin/'.(explode('/',trim($currentPath,'/'))[1] ?? ''));
$userRole = $auth['role'] ?? '';
?>
<section class="management-nav" aria-label="Secciones de gestión">
    <div>
        <span class="eyebrow">GESTIONAR</span>
        <p>Accesos disponibles para tu rol</p>
    </div>
    <nav>
        <?php foreach ($managementItems as [$url,$label,$permission]): ?>
            <?php
            if ($url === '/admin/users' && !in_array($userRole, ['ADMIN','MODERADOR'], true)) continue;
            if (!Auth::can($permission) || $activeSection === $url) continue;
            ?>
            <a href="<?=View::e($url)?>"><?=View::e($label)?></a>
        <?php endforeach; ?>
    </nav>
</section>
