<?php use App\Helpers\{Auth,View}; ?>

<section class="admin-welcome">
    <div class="admin-head">
        <div>
            <span class="eyebrow">PANEL ADMINISTRATIVO</span>
            <h1>Hola, <?=View::e($auth['username'])?></h1>
            <p>Consulta el movimiento de tu tienda y entra a cada sección desde sus tarjetas.</p>
        </div>
    </div>
</section>

<section class="dashboard-filter panel" aria-label="Filtrar resumen del panel">
    <div>
        <h2>Resumen de pedidos y ventas</h2>
        <p>Por defecto mostramos los datos de hoy.</p>
    </div>
    <form id="dashboardPeriodForm" method="get" action="/admin">
        <label for="dashboardPeriod">Período
            <select id="dashboardPeriod" name="period">
                <option value="daily" <?=$period==='daily'?'selected':''?>>Diario · hoy</option>
                <option value="weekly" <?=$period==='weekly'?'selected':''?>>Semanal · últimos 7 días</option>
                <option value="biweekly" <?=$period==='biweekly'?'selected':''?>>Quincenal · últimos 15 días</option>
                <option value="monthly" <?=$period==='monthly'?'selected':''?>>Mensual · últimos 30 días</option>
                <option value="range" <?=$period==='range'?'selected':''?>>Rango de fechas</option>
            </select>
        </label>
        <div id="dashboardRangeFields" class="dashboard-range-fields" <?=($period==='range'||$rangeError!=='')?'':'hidden'?>>
            <label>Desde
                <input type="date" id="dashboardDateFrom" name="date_from" value="<?=View::e($dateFromInput)?>" max="<?=View::e($today)?>" <?=($period==='range')?'required':''?>>
            </label>
            <label>Hasta
                <input type="date" id="dashboardDateTo" name="date_to" value="<?=View::e($dateToInput)?>" max="<?=View::e($today)?>" <?=($period==='range')?'required':''?>>
            </label>
            <button class="primary btn" type="submit">Aplicar rango</button>
        </div>
        <?php if ($rangeError !== ''): ?>
            <p class="errorbox" role="alert"><?=View::e($rangeError)?> Se muestran los datos de hoy.</p>
        <?php endif; ?>
    </form>
</section>

<section class="metrics dashboard-metrics" aria-label="Resumen de administración">
    <a class="metric-card" href="/admin/products" aria-label="Ver productos">
        <small>Productos</small>
        <strong><?=$metrics['products']?></strong>
        <span>Ver inventario →</span>
    </a>
    <?php if (Auth::can('users.manage')): ?>
        <a class="metric-card" href="/admin/users" aria-label="Ver compradores">
            <small>Compradores</small>
            <strong><?=$metrics['clients']?></strong>
            <span>Ver compradores →</span>
        </a>
    <?php else: ?>
        <div class="metric-card"><small>Compradores</small><strong><?=$metrics['clients']?></strong></div>
    <?php endif; ?>
    <a class="metric-card" href="/admin/orders" aria-label="Ver pedidos del período">
        <small>Pedidos · período</small>
        <strong><?=$metrics['orders']?></strong>
        <span><?=View::e($periodLabel)?> · Ver pedidos →</span>
    </a>
    <a class="metric-card" href="/admin/orders" aria-label="Ver pedidos pendientes del período">
        <small>Pendientes · período</small>
        <strong><?=$metrics['pending']?></strong>
        <span><?=View::e($periodLabel)?> · Ver pedidos →</span>
    </a>
    <a class="metric-card" href="/admin/reports" aria-label="Ver ventas del período">
        <small>Ventas · período</small>
        <strong>$<?=number_format($metrics['sales'],2)?></strong>
        <span><?=View::e($periodLabel)?> · Ver reportes →</span>
    </a>
    <a class="metric-card metric-card--rewards" href="/admin/rewards" aria-label="Ver recompensas">
        <small>Recompensas activas</small>
        <strong><?=count($rewards)?></strong>
        <?php if ($rewards): ?>
            <ul class="metric-reward-list">
                <?php foreach ($rewards as $reward): ?>
                    <li><?=View::e($reward['name'])?> <span>· <?=$reward['points_required']?> pts</span></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <span>No hay recompensas activas</span>
        <?php endif; ?>
        <span>Administrar recompensas →</span>
    </a>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('dashboardPeriodForm');
    const period = document.getElementById('dashboardPeriod');
    const rangeFields = document.getElementById('dashboardRangeFields');
    const from = document.getElementById('dashboardDateFrom');
    const to = document.getElementById('dashboardDateTo');
    if (!form || !period || !rangeFields || !from || !to) return;

    const shiftDate = (value, amount) => {
        const [year, month, day] = value.split('-').map(Number);
        const date = new Date(Date.UTC(year, month - 1, day + amount));
        return [date.getUTCFullYear(), String(date.getUTCMonth() + 1).padStart(2, '0'), String(date.getUTCDate()).padStart(2, '0')].join('-');
    };
    const syncRangeLimits = () => {
        from.max = to.value ? shiftDate(to.value, -1) : from.dataset.today;
        to.min = from.value ? shiftDate(from.value, 1) : '';
        from.setCustomValidity(from.value && to.value && from.value >= to.value ? 'Elige dos días distintos y coloca la fecha inicial primero.' : '');
        to.setCustomValidity(from.value && to.value && from.value >= to.value ? 'Elige dos días distintos y coloca la fecha inicial primero.' : '');
    };
    from.dataset.today = '<?=View::e($today)?>';
    period.addEventListener('change', () => {
        if (period.value === 'range') {
            rangeFields.hidden = false;
            from.required = true;
            to.required = true;
            from.focus();
            if (typeof from.showPicker === 'function') {
                try { from.showPicker(); } catch (_) { /* El calendario también se abre al pulsar la fecha. */ }
            }
        } else {
            rangeFields.hidden = true;
            from.required = false;
            to.required = false;
            form.requestSubmit();
        }
    });
    from.addEventListener('change', syncRangeLimits);
    to.addEventListener('change', syncRangeLimits);
    form.addEventListener('submit', event => {
        if (period.value === 'range') {
            syncRangeLimits();
            if (!from.value || !to.value || from.value >= to.value) event.preventDefault();
        }
    });
    syncRangeLimits();
});
</script>
