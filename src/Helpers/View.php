<?php
namespace App\Helpers;
final class View {
    public static function render(string $view,array $data=[]):void {
        extract($data,EXTR_SKIP); $base=dirname(__DIR__,2).'/views/';
        $viewFile=$base.$view.'.php'; if(!is_file($viewFile)){http_response_code(500);exit('Vista no encontrada');}
        $auth=Auth::user(); $flashes=Flash::all(); $title=$data['title']??'Pastelería Dulce Naranja';
        $cartItems=[];$cartTotal=0.0;$cartCount=0;
        if($auth && !empty($_SESSION['cart'])){$cartProductRepo=new \App\Repositories\ProductRepository();foreach($_SESSION['cart'] as $id=>$quantity){$product=$cartProductRepo->find((int)$id);if(!$product)continue;$product['quantity']=(int)$quantity;$product['line_total']=(float)$product['price']*$product['quantity'];$cartItems[]=$product;$cartTotal+=$product['line_total'];$cartCount+=$product['quantity'];}}
        require $base.'components/header.php';
        if(str_starts_with($view,'admin/') && $view!=='admin/dashboard') require $base.'components/admin-nav.php';
        require $viewFile; require $base.'components/footer.php';
    }
    public static function e(mixed $v):string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
}
