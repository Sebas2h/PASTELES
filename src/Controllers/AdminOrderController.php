<?php
namespace App\Controllers;
use App\Helpers\{View,Csrf,Flash}; use App\Middlewares\AdminMiddleware; use App\Config\Database;
final class AdminOrderController {
    public function index():void { AdminMiddleware::handle(); $pdo=Database::connection();$orders=$pdo->query('SELECT o.*,os.name status_name,u.username,u.first_name,u.last_name FROM orders o JOIN order_statuses os ON os.id=o.status_id JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC')->fetchAll();$statuses=$pdo->query('SELECT * FROM order_statuses ORDER BY id')->fetchAll();View::render('admin/orders',['title'=>'Pedidos','orders'=>$orders,'statuses'=>$statuses]); }
    public function status(string $id):void { AdminMiddleware::handle();Csrf::validate();$status=(int)($_POST['status_id']??0);$s=Database::connection()->prepare('UPDATE orders SET status_id=?,updated_at=NOW() WHERE id=?');$s->execute([$status,(int)$id]);Flash::set('success','Estado del pedido actualizado.');header('Location: /admin/orders'); }
}
