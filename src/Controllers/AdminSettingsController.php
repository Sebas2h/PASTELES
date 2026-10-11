<?php
namespace App\Controllers;
use App\Helpers\{View,Csrf,Flash,Settings}; use App\Middlewares\AdminMiddleware;
final class AdminSettingsController {
    public function index():void { AdminMiddleware::handle();View::render('admin/settings',['title'=>'Configuración','pointsPerUsd'=>Settings::pointsPerUsd()]); }
    public function save():void { AdminMiddleware::handle();Csrf::validate();$rate=max(0,min(100,(float)($_POST['points_per_usd']??10)));Settings::set('points_per_usd',(string)$rate);Flash::set('success','Configuración actualizada.');header('Location: /admin/settings'); }
}
