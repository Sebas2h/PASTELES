<?php
namespace App\Controllers;
use App\Helpers\View; use App\Middlewares\AdminMiddleware; use App\Config\Database;
final class AdminReportController {
 public function index():void{AdminMiddleware::handle();$p=Database::connection();$daily=$p->query("SELECT DATE(created_at) day,COUNT(*) orders_count,SUM(total) sales FROM orders GROUP BY DATE(created_at) ORDER BY day DESC LIMIT 14")->fetchAll();$top=$p->query('SELECT product_name,SUM(quantity) units,SUM(subtotal) revenue FROM order_items GROUP BY product_name ORDER BY units DESC LIMIT 10')->fetchAll();View::render('admin/reports',['title'=>'Reportes','daily'=>$daily,'top'=>$top]);}
}
