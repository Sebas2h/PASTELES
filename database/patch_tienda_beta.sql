-- Parche opcional para bases existentes.
-- NO es obligatorio si visitas /tienda: la versión beta crea estas tablas automáticamente.
CREATE TABLE IF NOT EXISTS store_items (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 description VARCHAR(255) NOT NULL,
 kind ENUM('COUPON','BENEFIT') NOT NULL DEFAULT 'COUPON',
 points_cost INT NOT NULL,
 discount_percent DECIMAL(5,2) NULL,
 stock INT NULL,
 status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_store_items_status_points(status,points_cost)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS store_redemptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 item_id INT UNSIGNED NOT NULL,
 code VARCHAR(32) NOT NULL,
 points_spent INT NOT NULL,
 status ENUM('AVAILABLE','USED','CANCELLED') NOT NULL DEFAULT 'AVAILABLE',
 redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 used_at TIMESTAMP NULL DEFAULT NULL,
 CONSTRAINT fk_store_redemption_user FOREIGN KEY(user_id) REFERENCES users(id),
 CONSTRAINT fk_store_redemption_item FOREIGN KEY(item_id) REFERENCES store_items(id),
 UNIQUE KEY uq_store_redemption_code(code),
 INDEX idx_store_redemption_user(user_id,status)
) ENGINE=InnoDB;

INSERT IGNORE INTO store_items(id,name,description,kind,points_cost,discount_percent,stock,status) VALUES
(1,'Cupón 5% OFF','Cupón beta para obtener 5% de descuento en una compra.','COUPON',100,5,NULL,'ACTIVE'),
(2,'Cupón 10% OFF','Cupón beta para obtener 10% de descuento en una compra.','COUPON',220,10,NULL,'ACTIVE'),
(3,'Cupón 15% OFF','Cupón beta premium para obtener 15% de descuento.','COUPON',380,15,NULL,'ACTIVE'),
(4,'Detalle sorpresa','Beneficio beta reservado para una sorpresa especial de Dulce Naranja.','BENEFIT',500,NULL,25,'ACTIVE');
