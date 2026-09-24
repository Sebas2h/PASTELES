<?php
namespace App\Repositories; use App\Config\Database;
final class ProductRepository {
    public function all(bool $onlyActive=false):array{$sql='SELECT p.*,c.name category_name,(SELECT path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) image FROM products p JOIN categories c ON c.id=p.category_id'.($onlyActive?" WHERE p.status='ACTIVE'":'').' ORDER BY p.created_at DESC';return Database::connection()->query($sql)->fetchAll();}
    public function find(int $id):?array{$s=Database::connection()->prepare('SELECT p.*,c.name category_name,(SELECT path FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) image FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=?');$s->execute([$id]);return $s->fetch()?:null;}
    public function create(array $d):int{$s=Database::connection()->prepare('INSERT INTO products(category_id,name,description,price,stock,points_reward,status,is_featured) VALUES(?,?,?,?,?,?,?,?)');$s->execute([$d['category_id'],$d['name'],$d['description'],$d['price'],$d['stock'],$d['points_reward'],$d['status'],$d['is_featured']]);return (int)Database::connection()->lastInsertId();}
    public function update(int $id,array $d):void{$s=Database::connection()->prepare('UPDATE products SET category_id=?,name=?,description=?,price=?,stock=?,points_reward=?,status=?,is_featured=?,updated_at=NOW() WHERE id=?');$s->execute([$d['category_id'],$d['name'],$d['description'],$d['price'],$d['stock'],$d['points_reward'],$d['status'],$d['is_featured'],$id]);}
    public function delete(int $id):void{$s=Database::connection()->prepare('DELETE FROM products WHERE id=?');$s->execute([$id]);}
    public function saveImage(int $id,string $path):void{$pdo=Database::connection();$pdo->prepare('UPDATE product_images SET is_primary=0 WHERE product_id=?')->execute([$id]);$pdo->prepare('INSERT INTO product_images(product_id,path,is_primary) VALUES(?,?,1)')->execute([$id,$path]);}
}
