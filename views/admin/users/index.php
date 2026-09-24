<?php
use App\Helpers\{View,Csrf};

$labels = ['ACTIVE'=>'Activo','INACTIVE'=>'Inactivo','BANNED'=>'Baneado'];
$isAdmin = ($auth['role'] ?? '') === 'ADMIN';
$isModerator = ($auth['role'] ?? '') === 'MODERADOR';
?>
<div class="admin-head">
    <div>
        <span class="eyebrow">ACCESOS</span>
        <h1>Usuarios</h1>
        <p class="muted">
            <?= $isAdmin
                ? 'Gestiona el rol y el estado de las cuentas. El rol ADMIN está protegido y no puede asignarse desde aquí.'
                : 'Como moderador puedes gestionar únicamente el estado de las cuentas.' ?>
        </p>
    </div>
</div>

<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Nombre</th>
            <th>Usuario</th>
            <th>Correo</th>
            <?php if ($isAdmin): ?><th>Rol</th><?php endif; ?>
            <th>Estado</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><b><?=View::e($u['first_name'].' '.$u['last_name'])?></b></td>
                <td>@<?=View::e($u['username'])?></td>
                <td><?=View::e($u['email'])?></td>

                <?php if ($isAdmin): ?>
                    <td>
                        <?php if ($u['role'] === 'ADMIN'): ?>
                            <span class="status">ADMIN · protegido</span>
                        <?php elseif ((int)$u['id'] === (int)$auth['id']): ?>
                            <span class="status"><?=View::e($u['role'])?></span>
                        <?php else: ?>
                            <form method="post" action="/admin/users/<?=$u['id']?>/role" class="actions">
                                <?=Csrf::field()?>
                                <select name="role" aria-label="Rol de <?=View::e($u['username'])?>">
                                    <?php foreach (['VENDEDOR','MODERADOR','COMPRADOR'] as $role): ?>
                                        <option value="<?=$role?>" <?=$u['role']===$role?'selected':''?>><?=$role?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="secondary">Solicitar cambio</button>
                            </form>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>

                <td>
                    <?php if ($isModerator && $u['role'] === 'ADMIN'): ?>
                        <span class="status"><?=View::e($labels[$u['status']] ?? $u['status'])?> · protegido</span>
                    <?php else: ?>
                        <form method="post" action="/admin/users/<?=$u['id']?>/status" class="actions">
                            <?=Csrf::field()?>
                            <select name="status" aria-label="Estado de <?=View::e($u['username'])?>">
                                <?php foreach ($labels as $value=>$label): ?>
                                    <option value="<?=$value?>" <?=$u['status']===$value?'selected':''?>><?=$label?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="secondary">Guardar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
