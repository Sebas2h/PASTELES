<?php use App\Helpers\{View,Csrf}; $probabilities=$wheelConfig['probabilities']; ?>
<div class="admin-head"><div><span class="eyebrow">FIDELIZACIÓN</span><h1>Recompensas</h1></div><a href="/admin">← Panel</a></div>
<section class="panel wheel-admin-panel">
    <div><span class="eyebrow">RULETA</span><h2>Configuración de premios y giros</h2><p class="muted">Los costos y las probabilidades guardados se aplican globalmente a todos los compradores. La tienda ajusta visualmente los sectores según esta configuración, sin mostrar porcentajes.</p></div>
    <form method="post" action="/admin/rewards/ruleta" class="wheel-admin-form">
        <?=Csrf::field()?>
        <label>Primer giro (puntos)<input type="number" name="first_cost" min="1" value="<?=$wheelConfig['first_cost']?>" required></label>
        <label>Giros siguientes (puntos)<input type="number" name="respin_cost" min="1" value="<?=$wheelConfig['respin_cost']?>" required></label>
        <label>1 muffin gratis (%)<input type="number" name="probabilities[muffin]" min="0" max="100" step="0.01" value="<?=number_format($probabilities['muffin'],2,'.','')?>" required></label>
        <label>Cupón 5% OFF (%)<input type="number" name="probabilities[five]" min="0" max="100" step="0.01" value="<?=number_format($probabilities['five'],2,'.','')?>" required></label>
        <label>Cupón 10% OFF (%)<input type="number" name="probabilities[ten]" min="0" max="100" step="0.01" value="<?=number_format($probabilities['ten'],2,'.','')?>" required></label>
        <label class="wheel-admin-jackpot">Cupón 20% OFF · Premio mayor (%)<input type="number" name="probabilities[twenty]" min="0" max="100" step="0.01" value="<?=number_format($probabilities['twenty'],2,'.','')?>" required></label>
        <small class="wheel-admin-sum">La suma debe ser exactamente 100%.</small>
        <button class="primary">Guardar configuración</button>
    </form>
</section>
<div class="two-col">
    <form class="panel" method="post" action="/admin/rewards"><?=Csrf::field()?><h2>Nueva recompensa</h2><label>Nombre<input name="name" placeholder="10% de descuento" required></label><label>Puntos requeridos<input type="number" name="points_required" min="1" required></label><label>Porcentaje de descuento<input type="number" step="0.01" min="0" max="100" name="discount_percent" required></label><button class="primary">Guardar</button></form>
    <section class="panel"><h2>Recompensas</h2><?php foreach($rewards as $r):?><div class="rewardrow"><div><b><?=View::e($r['name'])?></b><small><?=$r['points_required']?> pts · <?=$r['discount_percent']?>%</small></div><span class="status"><?=View::e($r['status'])?></span><form method="post" action="/admin/rewards/<?=$r['id']?>/toggle"><?=Csrf::field()?><button class="linkbtn">Cambiar</button></form></div><?php endforeach;?></section>
</div>
