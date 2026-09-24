<?php
namespace App\Controllers;

use App\Config\Database;
use App\Helpers\{Auth,Csrf,Flash,View};
use App\Middlewares\AuthMiddleware;
use App\Repositories\UserRepository;
use App\Services\EmailChangeService;

final class ProfileController
{
    public function show():void
    {
        AuthMiddleware::handle();$u=Auth::user();$pdo=Database::connection();
        $s=$pdo->prepare('SELECT pm.*,o.id order_no FROM point_movements pm LEFT JOIN orders o ON o.id=pm.order_id WHERE pm.user_id=? ORDER BY pm.created_at DESC LIMIT 50');$s->execute([$u['id']]);$mov=$s->fetchAll();
        $s=$pdo->prepare('SELECT o.*,os.name status_name FROM orders o JOIN order_statuses os ON os.id=o.status_id WHERE o.user_id=? ORDER BY o.created_at DESC LIMIT 20');$s->execute([$u['id']]);
        View::render('cliente/profile',['title'=>'Mi cuenta','movements'=>$mov,'orders'=>$s->fetchAll()]);
    }

    public function updateProfile():void
    {
        AuthMiddleware::handle();Csrf::validate();$u=Auth::user();
        $field=(string)($_POST['field']??'');
        $value=trim((string)($_POST['value']??''));
        $allowed=['first_name'=>'nombre','last_name'=>'apellido'];

        if(!isset($allowed[$field])){Flash::set('error','El dato solicitado no se puede modificar.');header('Location: /profile');exit;}
        if($value===''){Flash::set('error','El '.$allowed[$field].' no puede quedar vacío.');header('Location: /profile');exit;}
        if(mb_strlen($value)>80){Flash::set('error','El '.$allowed[$field].' no puede superar 80 caracteres.');header('Location: /profile');exit;}

        $repo=new UserRepository();
        if($field==='first_name'){$repo->updateFirstName((int)$u['id'],$value);}
        else{$repo->updateLastName((int)$u['id'],$value);}

        Flash::set('success','Tu '.$allowed[$field].' se actualizó correctamente.');header('Location: /profile');exit;
    }

    public function requestCurrentEmailCode():void
    {
        AuthMiddleware::handle();Csrf::validate();$u=Auth::user();$service=new EmailChangeService();
        try{$service->sendCurrentCode((int)$u['id'],(string)$u['email']);Flash::set('success','Enviamos un código de 6 dígitos a '.$service->maskedEmail((string)$u['email']).'.');header('Location: /profile/email/verify-current');}
        catch(\Throwable $e){Flash::set('error','No pudimos enviar el código: '.$e->getMessage());header('Location: /profile');}
        exit;
    }

    public function currentEmailCodeForm():void
    {
        AuthMiddleware::handle();$u=Auth::user();$service=new EmailChangeService();$state=$service->state();
        if(($state['user_id']??null)!==(int)$u['id']||empty($state['current_expires'])){Flash::set('error','Solicita primero un código para tu correo actual.');header('Location: /profile');exit;}
        View::render('cliente/email-change',['title'=>'Verificar correo actual','step'=>'current','maskedEmail'=>$service->maskedEmail((string)$u['email'])]);
    }

    public function verifyCurrentEmailCode():void
    {
        AuthMiddleware::handle();Csrf::validate();$u=Auth::user();$service=new EmailChangeService();$code=trim((string)($_POST['code']??''));
        if(!preg_match('/^\d{6}$/',$code)||!$service->verifyCurrent((int)$u['id'],$code)){Flash::set('error','Código incorrecto, vencido o con demasiados intentos.');header('Location: /profile/email/verify-current');exit;}
        Flash::set('success','Correo actual verificado. Ahora puedes indicar tu nuevo correo.');header('Location: /profile/email/new');exit;
    }

    public function newEmailForm():void
    {
        AuthMiddleware::handle();$u=Auth::user();$service=new EmailChangeService();
        if(!$service->currentVerified((int)$u['id'])){Flash::set('error','Primero debes verificar tu correo actual.');header('Location: /profile');exit;}
        View::render('cliente/email-change',['title'=>'Nuevo correo','step'=>'new']);
    }

    public function requestNewEmailCode():void
    {
        AuthMiddleware::handle();Csrf::validate();$u=Auth::user();$service=new EmailChangeService();$repo=new UserRepository();$email=strtolower(trim((string)($_POST['new_email']??'')));
        if(!$service->currentVerified((int)$u['id'])){Flash::set('error','La verificación del correo actual venció. Vuelve a comenzar.');header('Location: /profile');exit;}
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){Flash::set('error','Ingresa un correo electrónico válido.');header('Location: /profile/email/new');exit;}
        if(strcasecmp($email,(string)$u['email'])===0){Flash::set('error','El nuevo correo debe ser diferente al actual.');header('Location: /profile/email/new');exit;}
        if($repo->emailUsedByAnother($email,(int)$u['id'])){Flash::set('error','Ese correo ya está asociado a otra cuenta.');header('Location: /profile/email/new');exit;}
        try{$service->sendNewCode((int)$u['id'],$email);Flash::set('success','Enviamos un código al nuevo correo '.$service->maskedEmail($email).'.');header('Location: /profile/email/verify-new');}
        catch(\Throwable $e){Flash::set('error','No pudimos enviar el código al nuevo correo: '.$e->getMessage());header('Location: /profile/email/new');}
        exit;
    }

    public function newEmailCodeForm():void
    {
        AuthMiddleware::handle();$u=Auth::user();$service=new EmailChangeService();$state=$service->state();
        if(!$service->currentVerified((int)$u['id'])||empty($state['new_email'])){Flash::set('error','Primero debes indicar y verificar el nuevo correo.');header('Location: /profile');exit;}
        View::render('cliente/email-change',['title'=>'Verificar nuevo correo','step'=>'verify-new','maskedEmail'=>$service->maskedEmail((string)$state['new_email']),'newEmail'=>(string)$state['new_email']]);
    }

    public function verifyNewEmailCode():void
    {
        AuthMiddleware::handle();Csrf::validate();$u=Auth::user();$service=new EmailChangeService();$repo=new UserRepository();$code=trim((string)($_POST['code']??''));
        if(!preg_match('/^\d{6}$/',$code)){Flash::set('error','El código debe tener 6 dígitos.');header('Location: /profile/email/verify-new');exit;}
        $email=$service->verifyNew((int)$u['id'],$code);
        if($email===null){Flash::set('error','Código incorrecto, vencido o con demasiados intentos.');header('Location: /profile/email/verify-new');exit;}
        if($repo->emailUsedByAnother($email,(int)$u['id'])){$service->clear();Flash::set('error','Ese correo acaba de ser utilizado por otra cuenta. No se realizó el cambio.');header('Location: /profile');exit;}
        $repo->updateEmail((int)$u['id'],$email);$service->clear();
        Flash::set('success','Tu correo electrónico se cambió y verificó correctamente.');header('Location: /profile');exit;
    }
}
