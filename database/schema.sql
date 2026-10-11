CREATE DATABASE IF NOT EXISTS pasteleria_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pasteleria_db;

CREATE TABLE IF NOT EXISTS roles (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(50) NOT NULL UNIQUE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO roles(name) VALUES ('ADMIN'),('VENDEDOR'),('MODERADOR'),('COMPRADOR');

CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 role_id INT UNSIGNED NOT NULL,
 first_name VARCHAR(80) NOT NULL,
 second_name VARCHAR(80) NULL,
 last_name VARCHAR(80) NOT NULL,
 second_last_name VARCHAR(80) NOT NULL,
 username VARCHAR(60) NOT NULL UNIQUE,
 email VARCHAR(160) NOT NULL UNIQUE,
 google_sub VARCHAR(255) NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 points_balance INT NOT NULL DEFAULT 0,
 status ENUM('ACTIVE','INACTIVE','BLOCKED','BANNED') NOT NULL DEFAULT 'ACTIVE',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_users_role FOREIGN KEY(role_id) REFERENCES roles(id),
 INDEX idx_users_role(role_id), INDEX idx_users_status(status)
) ENGINE=InnoDB;
UPDATE users SET status='INACTIVE' WHERE status='BLOCKED';
ALTER TABLE users MODIFY status ENUM('ACTIVE','INACTIVE','BANNED') NOT NULL DEFAULT 'ACTIVE';
ALTER TABLE users ADD COLUMN IF NOT EXISTS google_sub VARCHAR(255) NULL UNIQUE AFTER email;

CREATE TABLE IF NOT EXISTS system_settings (
 setting_key VARCHAR(100) PRIMARY KEY,
 setting_value TEXT NOT NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
UPDATE users u JOIN roles old_role ON old_role.id=u.role_id JOIN roles new_role ON new_role.name='ADMIN' SET u.role_id=new_role.id WHERE old_role.name='ADMINISTRADOR';
UPDATE users u JOIN roles old_role ON old_role.id=u.role_id JOIN roles new_role ON new_role.name='COMPRADOR' SET u.role_id=new_role.id WHERE old_role.name='CLIENTE';
UPDATE users u JOIN roles old_role ON old_role.id=u.role_id JOIN roles new_role ON new_role.name='VENDEDOR' SET u.role_id=new_role.id WHERE old_role.name='TRABAJADOR';
DELETE FROM roles WHERE name IN ('ADMINISTRADOR','CLIENTE');
DELETE FROM roles WHERE name='TRABAJADOR';
INSERT INTO system_settings(setting_key,setting_value) VALUES ('setup_completed','0'),('points_per_usd','10') ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
INSERT IGNORE INTO system_settings(setting_key,setting_value) VALUES
 ('wheel_first_spin_cost','150'),('wheel_respin_cost','10'),('wheel_prize_probabilities','{"muffin":62.5,"five":30,"ten":5,"twenty":2.5}');

CREATE TABLE IF NOT EXISTS permissions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL UNIQUE,
 description VARCHAR(180) NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS role_permissions (
 role_id INT UNSIGNED NOT NULL,
 permission_id INT UNSIGNED NOT NULL,
 PRIMARY KEY(role_id,permission_id),
 CONSTRAINT fk_role_permissions_role FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE,
 CONSTRAINT fk_role_permissions_permission FOREIGN KEY(permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
INSERT IGNORE INTO permissions(name,description) VALUES
('dashboard.view','Ver panel de gestión'),('products.manage','Gestionar productos y categorías'),
('orders.manage','Gestionar pedidos'),('rewards.manage','Gestionar recompensas'),
('users.manage','Gestionar usuarios y roles'),('settings.manage','Gestionar configuración'),
('reports.view','Ver reportes');
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='ADMIN';
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.name IN ('dashboard.view','products.manage','orders.manage') WHERE r.name='VENDEDOR';
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.name IN ('dashboard.view','users.manage') WHERE r.name='MODERADOR';

CREATE TABLE IF NOT EXISTS categories (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE,
 status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO categories(name) VALUES ('Muffins');

CREATE TABLE IF NOT EXISTS products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 category_id INT UNSIGNED NOT NULL,
 name VARCHAR(140) NOT NULL,
 description TEXT NOT NULL,
 price DECIMAL(10,2) NOT NULL CHECK(price>=0),
 stock INT NOT NULL DEFAULT 0 CHECK(stock>=0),
 points_reward INT NOT NULL DEFAULT 0 CHECK(points_reward>=0),
 status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
 is_featured TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_products_category FOREIGN KEY(category_id) REFERENCES categories(id),
 INDEX idx_products_category(category_id), INDEX idx_products_status(status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_images (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id BIGINT UNSIGNED NOT NULL,
 path VARCHAR(255) NOT NULL,
 is_primary TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_images_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
 INDEX idx_images_product(product_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_statuses (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;
INSERT IGNORE INTO order_statuses(name) VALUES ('PENDIENTE'),('CONFIRMADO'),('PREPARANDO'),('LISTO'),('LISTO PARA RETIRAR'),('ENVIADO'),('ENTREGADO'),('RECIBIDO'),('CANCELADO');

CREATE TABLE IF NOT EXISTS orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 status_id INT UNSIGNED NOT NULL,
 delivery_method ENUM('RETIRO','DOMICILIO') NOT NULL DEFAULT 'RETIRO',
 subtotal DECIMAL(10,2) NOT NULL,
 discount DECIMAL(10,2) NOT NULL DEFAULT 0,
 total DECIMAL(10,2) NOT NULL,
 points_earned INT NOT NULL DEFAULT 0,
 points_spent INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_orders_user FOREIGN KEY(user_id) REFERENCES users(id),
 CONSTRAINT fk_orders_status FOREIGN KEY(status_id) REFERENCES order_statuses(id),
 INDEX idx_orders_user(user_id),INDEX idx_orders_status(status_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NULL,
 product_name VARCHAR(140) NOT NULL,
 unit_price DECIMAL(10,2) NOT NULL,
 quantity INT NOT NULL CHECK(quantity>0),
 subtotal DECIMAL(10,2) NOT NULL,
 CONSTRAINT fk_items_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_items_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
 INDEX idx_items_order(order_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS point_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NULL,
 type ENUM('EARN','REDEEM','ADJUSTMENT','PROMOTION') NOT NULL,
 points INT NOT NULL,
 description VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_points_user FOREIGN KEY(user_id) REFERENCES users(id),
 CONSTRAINT fk_points_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL,
 INDEX idx_points_user(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rewards (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 points_required INT NOT NULL CHECK(points_required>0),
 discount_percent DECIMAL(5,2) NOT NULL CHECK(discount_percent>=0 AND discount_percent<=100),
 status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO rewards(id,name,points_required,discount_percent,status) VALUES (1,'5% de descuento',100,5,'ACTIVE'),(2,'10% de descuento',250,10,'ACTIVE'),(3,'20% de descuento',500,20,'ACTIVE');

CREATE TABLE IF NOT EXISTS reward_redemptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 reward_id INT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 points_spent INT NOT NULL,
 discount_amount DECIMAL(10,2) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_redemption_user FOREIGN KEY(user_id) REFERENCES users(id),
 CONSTRAINT fk_redemption_reward FOREIGN KEY(reward_id) REFERENCES rewards(id),
 CONSTRAINT fk_redemption_order FOREIGN KEY(order_id) REFERENCES orders(id),
 UNIQUE KEY uq_redemption_order(order_id)
) ENGINE=InnoDB;

-- ============================================================
-- TIENDA DE PUNTOS · BETA
-- Los canjes quedan separados de las recompensas aplicadas al checkout
-- para poder evolucionar la tienda sin romper pedidos anteriores.
-- ============================================================
CREATE TABLE IF NOT EXISTS store_items (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 description VARCHAR(255) NOT NULL,
 kind ENUM('COUPON','BENEFIT','WHEEL') NOT NULL DEFAULT 'COUPON',
 points_cost INT NOT NULL,
 discount_percent DECIMAL(5,2) NULL,
 stock INT NULL,
 status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
 reward_id INT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_store_items_reward(reward_id),
 INDEX idx_store_items_status_points(status,points_cost)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS store_redemptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 item_id INT UNSIGNED NOT NULL,
 code VARCHAR(32) NOT NULL,
 points_spent INT NOT NULL,
 status ENUM('PENDING','AVAILABLE','USED','CANCELLED') NOT NULL DEFAULT 'AVAILABLE',
 prize_name VARCHAR(120) NULL,
 prize_type VARCHAR(20) NULL,
 discount_percent DECIMAL(5,2) NULL,
 redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 used_at TIMESTAMP NULL DEFAULT NULL,
 CONSTRAINT fk_store_redemption_user FOREIGN KEY(user_id) REFERENCES users(id),
 CONSTRAINT fk_store_redemption_item FOREIGN KEY(item_id) REFERENCES store_items(id),
 UNIQUE KEY uq_store_redemption_code(code),
 INDEX idx_store_redemption_user(user_id,status)
) ENGINE=InnoDB;

INSERT IGNORE INTO store_items(id,name,description,kind,points_cost,discount_percent,stock,status) VALUES
(1,'Cupón 5% OFF','Cupón beta para obtener 5% de descuento en una compra.','COUPON',100,5,NULL,'ACTIVE'),
(2,'Cupón 10% OFF','Cupón beta para obtener 10% de descuento en una compra.','COUPON',250,10,NULL,'ACTIVE'),
(3,'Cupón 20% OFF','Cupón beta para obtener 20% de descuento en una compra.','COUPON',500,20,NULL,'ACTIVE'),
(4,'Ruleta de premios','Premios sorpresa en cada giro.','WHEEL',150,NULL,25,'ACTIVE');
