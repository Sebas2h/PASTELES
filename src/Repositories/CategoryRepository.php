<?php
namespace App\Repositories; use App\Config\Database;
final class CategoryRepository {
    public function all(bool $active=false):array{$sql='SELECT * FROM categories'.($active?" WHERE status='ACTIVE'":'').' ORDER BY name';return Database::connection()->query($sql)->fetchAll();}
    public function create(string $n):void{$s=Database::connection()->prepare("INSERT INTO categories(name,status) VALUES(?,'ACTIVE')");$s->execute([$n]);}
    public function toggle(int $id):void{Database::connection()->prepare("UPDATE categories SET status=IF(status='ACTIVE','INACTIVE','ACTIVE') WHERE id=?")->execute([$id]);}
}
