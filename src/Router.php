<?php
namespace App;
final class Router {
    private array $routes=[];
    public function add(string $method,string $path,array $handler):void{$this->routes[]=[strtoupper($method),$path,$handler];}
    public function dispatch(string $method,string $uri):void{
        $path=parse_url($uri,PHP_URL_PATH) ?: '/';
        foreach($this->routes as [$m,$pattern,$handler]){
            if($m!==strtoupper($method)) continue;
            $regex=preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#','(?P<$1>[^/]+)',$pattern); $regex='#^'.$regex.'$#';
            if(preg_match($regex,$path,$matches)){
                $params=array_filter($matches,'is_string',ARRAY_FILTER_USE_KEY); [$class,$fn]=$handler; (new $class())->$fn(...array_values($params)); return;
            }
        }
        http_response_code(404); echo '<h1>404</h1><p>Página no encontrada.</p>';
    }
}
