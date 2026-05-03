CREATE DATABASE IF NOT EXISTS kapenatin_db;
USE kapenatin_db;

-- ============================================================
-- LOOKUP TABLES (sizes, milk types, sugar levels, add-ons)
-- ============================================================

CREATE TABLE IF NOT EXISTS sizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    price_modifier DECIMAL(10,2) DEFAULT 0.00,
    display_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS milk_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price_modifier DECIMAL(10,2) DEFAULT 0.00,
    display_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS sugar_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    display_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS addons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) DEFAULT 0.00,
    display_order INT DEFAULT 0
);

-- ============================================================
-- USERS  (staff + customers, all in one table)
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin','cashier','barista','customer') DEFAULT 'customer',
    loyalty_points INT DEFAULT 0,
    total_spent DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- MENU
-- ============================================================

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    display_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    category_id INT,
    description TEXT,
    image VARCHAR(500) DEFAULT NULL COMMENT 'Relative path e.g. imgs/products/COF001.jpg',
    is_available TINYINT(1) DEFAULT 1,
    has_sizes  TINYINT(1) DEFAULT 1,
    has_milk   TINYINT(1) DEFAULT 1,
    has_sugar  TINYINT(1) DEFAULT 1,
    has_addons TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ============================================================
-- CART
-- ============================================================

CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    product_code VARCHAR(50) NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    product_price DECIMAL(10,2) NOT NULL,
    size_name VARCHAR(50) DEFAULT '',
    milk_name VARCHAR(100) DEFAULT '',
    sugar_name VARCHAR(50) DEFAULT '',
    addons TEXT DEFAULT '',
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    notes TEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- ORDERS
-- ============================================================

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) DEFAULT '',
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'Cash',
    loyalty_points_earned INT DEFAULT 0,
    loyalty_points_used INT DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Confirmed',
    notes TEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL,
    product_code VARCHAR(50) NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    product_price DECIMAL(10,2) NOT NULL,
    size_name VARCHAR(50) DEFAULT '',
    milk_name VARCHAR(100) DEFAULT '',
    sugar_name VARCHAR(50) DEFAULT '',
    addons TEXT DEFAULT '',
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    notes TEXT DEFAULT '',
    FOREIGN KEY (order_number) REFERENCES orders(order_number) ON DELETE CASCADE
);

-- ============================================================
-- SEED: SIZES
-- ============================================================
INSERT INTO sizes (name, price_modifier, display_order) VALUES
('Small',  0.00,  1),
('Medium', 20.00, 2),
('Large',  40.00, 3),
('XL',     60.00, 4);

-- ============================================================
-- SEED: MILK TYPES
-- ============================================================
INSERT INTO milk_types (name, price_modifier, display_order) VALUES
('Whole Milk',    0.00, 1),
('Skim Milk',     0.00, 2),
('Oat Milk',     25.00, 3),
('Almond Milk',  25.00, 4),
('Soy Milk',     20.00, 5),
('Coconut Milk', 25.00, 6),
('No Milk',       0.00, 7);

-- ============================================================
-- SEED: SUGAR LEVELS
-- ============================================================
INSERT INTO sugar_levels (name, display_order) VALUES
('No Sugar',    1),
('Less Sweet',  2),
('Half Sweet',  3),
('Regular',     4),
('Extra Sweet', 5);

-- ============================================================
-- SEED: ADD-ONS
-- ============================================================
INSERT INTO addons (name, price, display_order) VALUES
('Extra Shot',        20.00,  1),
('Vanilla Syrup',     20.00,  2),
('Caramel Syrup',     20.00,  3),
('Hazelnut Syrup',    20.00,  4),
('Brown Sugar Syrup', 20.00,  5),
('Whipped Cream',     15.00,  6),
('Caramel Drizzle',   15.00,  7),
('Chocolate Drizzle', 15.00,  8),
('Cinnamon Powder',   10.00,  9),
('Boba Pearls',       30.00, 10),
('Cheese Foam',       35.00, 11);

-- ============================================================
-- SEED: STAFF ACCOUNTS  (password for all = "password")
-- ============================================================
INSERT INTO users (username, password, name, email, role) VALUES
('admin',   'password', 'Admin User',  'admin@kapenatin.ph',   'admin'),
('cashier', 'password', 'Cashier One', 'cashier@kapenatin.ph', 'cashier'),
('barista', 'password', 'Barista One', 'barista@kapenatin.ph', 'barista');

-- ============================================================
-- SEED: SAMPLE CUSTOMERS
-- ============================================================
INSERT INTO users (username, password, name, email, phone, role, loyalty_points, total_spent) VALUES
('maria', 'password', 'Maria Santos',   'maria@email.com', '09171234567', 'customer', 250,  1500.00),
('juan',  'password', 'Juan Dela Cruz', 'juan@email.com',  '09281234567', 'customer', 100,   600.00),
('ana',   'password', 'Ana Reyes',      'ana@email.com',   '09391234567', 'customer', 500,  3000.00);

-- ============================================================
-- SEED: CATEGORIES
-- ============================================================
INSERT INTO categories (name, display_order) VALUES
('Coffee',     1),
('Non-Coffee', 2),
('Frappe',     3),
('Pastries',   4),
('Sandwiches', 5);

-- ============================================================
-- SEED: PRODUCTS
-- ============================================================
INSERT INTO products (code, name, price, category_id, description) VALUES
('COF001', 'Americano',               100.00, 1, 'Espresso with hot water'),
('COF002', 'Cafe Latte',              130.00, 1, 'Espresso with steamed milk'),
('COF003', 'Cappuccino',              130.00, 1, 'Espresso with foam'),
('COF004', 'Mocha',                   150.00, 1, 'Espresso with chocolate'),
('COF005', 'Caramel Macchiato',       160.00, 1, 'Espresso with caramel'),
('COF006', 'Cold Brew',               160.00, 1, '12-hour steeped coffee'),
('NON001', 'Matcha Latte',            150.00, 2, 'Green tea with milk'),
('NON002', 'Taro Latte',              150.00, 2, 'Taro with steamed milk'),
('NON003', 'Hot Chocolate',           130.00, 2, 'Rich Belgian chocolate'),
('NON004', 'Strawberry Milk',         140.00, 2, 'Fresh strawberry with milk'),
('FRP001', 'Mocha Frappe',            170.00, 3, 'Blended mocha with ice'),
('FRP002', 'Caramel Frappe',          170.00, 3, 'Blended caramel with ice'),
('FRP003', 'Matcha Frappe',           170.00, 3, 'Blended matcha with ice'),
('FRP004', 'Oreo Frappe',             175.00, 3, 'Crushed oreo blended'),
('PAS001', 'Butter Croissant',         75.00, 4, 'Flaky buttery croissant'),
('PAS002', 'Cinnamon Roll',            95.00, 4, 'Soft roll with cream cheese'),
('PAS003', 'Chocolate Brownie',        85.00, 4, 'Dense fudgy brownie'),
('SAN001', 'Egg and Cheese Sandwich', 130.00, 5, 'Eggs with melted cheese'),
('SAN002', 'Avocado Toast',           160.00, 5, 'Smashed avocado on sourdough'),
('SAN003', 'Club Sandwich',           180.00, 5, 'Triple decker with chicken');
