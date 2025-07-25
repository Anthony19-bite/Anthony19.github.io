-- Crear la base de datos de productos para TechStore
CREATE DATABASE IF NOT EXISTS techstore;
USE techstore;

-- Tabla de categorías
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de productos
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    category_id INT,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    description TEXT,
    brand VARCHAR(100),
    model VARCHAR(100),
    image_url VARCHAR(500),
    sku VARCHAR(100) UNIQUE,
    status ENUM('active', 'inactive', 'discontinued') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_name (name),
    INDEX idx_category (category_id),
    INDEX idx_brand (brand),
    INDEX idx_stock (stock),
    INDEX idx_price (price)
);

-- Tabla de movimientos de inventario
CREATE TABLE inventory_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    previous_stock INT NOT NULL,
    new_stock INT NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id),
    INDEX idx_date (created_at)
);

-- Insertar categorías iniciales
INSERT INTO categories (name, description) VALUES
('Laptops', 'Computadoras portátiles y notebooks'),
('Smartphones', 'Teléfonos inteligentes y móviles'),
('Tablets', 'Tabletas y dispositivos táctiles'),
('Accesorios', 'Accesorios para dispositivos electrónicos'),
('Audio', 'Equipos de audio y sonido'),
('Gaming', 'Consolas y accesorios para videojuegos');

-- Insertar productos de ejemplo
INSERT INTO products (name, category_id, price, stock, description, brand, model, sku) VALUES
('MacBook Pro 14', 1, 45999.00, 15, 'Laptop profesional con chip M2 Pro, pantalla Liquid Retina XDR de 14 pulgadas y rendimiento excepcional para creativos y profesionales.', 'Apple', 'MacBook Pro 14" M2 Pro', 'MBP-14-M2-001'),
('iPhone 15 Pro', 2, 28999.00, 8, 'Smartphone premium con chip A17 Pro, cámara pro de 48MP, pantalla Super Retina XDR y construcción en titanio.', 'Apple', 'iPhone 15 Pro 128GB', 'IPH-15-PRO-128'),
('Samsung Galaxy S24 Ultra', 2, 31999.00, 12, 'Smartphone insignia con S Pen integrado, cámara de 200MP, pantalla Dynamic AMOLED 2X y funciones IA avanzadas.', 'Samsung', 'Galaxy S24 Ultra 256GB', 'SGS-24-ULT-256'),
('AirPods Pro', 5, 5999.00, 25, 'Auriculares inalámbricos con cancelación activa de ruido, audio espacial y resistencia al agua IPX4.', 'Apple', 'AirPods Pro (2ª generación)', 'APP-2GEN-001'),
('PlayStation 5', 6, 12999.00, 3, 'Consola de videojuegos de próxima generación con unidad SSD ultra rápida, audio 3D y gráficos 4K.', 'Sony', 'PlayStation 5 Standard Edition', 'PS5-STD-001');

-- Vistas útiles para reportes
CREATE VIEW product_details AS
SELECT 
    p.id,
    p.name,
    c.name as category_name,
    p.price,
    p.stock,
    p.description,
    p.brand,
    p.model,
    p.sku,
    p.status,
    p.created_at,
    p.updated_at,
    CASE 
        WHEN p.stock <= 5 THEN 'Bajo'
        WHEN p.stock <= 20 THEN 'Medio'
        ELSE 'Alto'
    END as stock_level
FROM products p
LEFT JOIN categories c ON p.category_id = c.id;

-- Vista de productos con bajo stock
CREATE VIEW low_stock_products AS
SELECT * FROM product_details
WHERE stock <= 5 AND status = 'active';

-- Procedimiento almacenado para actualizar stock
DELIMITER //
CREATE PROCEDURE UpdateStock(
    IN p_product_id INT,
    IN p_quantity INT,
    IN p_movement_type ENUM('in', 'out', 'adjustment'),
    IN p_reason VARCHAR(255)
)
BEGIN
    DECLARE v_current_stock INT;
    DECLARE v_new_stock INT;
    
    -- Obtener stock actual
    SELECT stock INTO v_current_stock FROM products WHERE id = p_product_id;
    
    -- Calcular nuevo stock
    IF p_movement_type = 'in' THEN
        SET v_new_stock = v_current_stock + p_quantity;
    ELSEIF p_movement_type = 'out' THEN
        SET v_new_stock = v_current_stock - p_quantity;
    ELSE -- adjustment
        SET v_new_stock = p_quantity;
    END IF;
    
    -- Validar que el stock no sea negativo
    IF v_new_stock < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock insuficiente';
    END IF;
    
    -- Actualizar stock del producto
    UPDATE products SET stock = v_new_stock WHERE id = p_product_id;
    
    -- Registrar el movimiento
    INSERT INTO inventory_movements (product_id, movement_type, quantity, previous_stock, new_stock, reason)
    VALUES (p_product_id, p_movement_type, p_quantity, v_current_stock, v_new_stock, p_reason);
END //
DELIMITER ;

-- Trigger para generar SKU automáticamente si no se proporciona
DELIMITER //
CREATE TRIGGER generate_sku_before_insert 
BEFORE INSERT ON products
FOR EACH ROW
BEGIN
    IF NEW.sku IS NULL OR NEW.sku = '' THEN
        SET NEW.sku = CONCAT(
            UPPER(LEFT(NEW.brand, 3)),
            '-',
            LPAD(NEW.id, 6, '0')
        );
    END IF;
END //
DELIMITER ;