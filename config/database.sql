-- ============================================================
-- INVENTORY MANAGEMENT SYSTEM — Database Setup
-- Run this script once to initialize the database
-- ============================================================

CREATE DATABASE IF NOT EXISTS inventory_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventory_system;

-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','staff','cashier') NOT NULL DEFAULT 'cashier',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- ITEMS TABLE (Item Master)
-- ============================================================
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode VARCHAR(50) NOT NULL UNIQUE,
    item_id VARCHAR(20),
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    supplier VARCHAR(100),
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    reorder_level INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- STOCK IN TABLE (Purchase/Receiving)
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_in (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(30) NOT NULL,
    date DATE NOT NULL,
    barcode VARCHAR(50) NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(100),
    qty_in INT NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_cost DECIMAL(12,2) GENERATED ALWAYS AS (qty_in * unit_cost) STORED,
    supplier_notes VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barcode) REFERENCES items(barcode) ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- SALES TABLE (POS / Stock Out)
-- ============================================================
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(30) NOT NULL,
    date DATE NOT NULL,
    barcode VARCHAR(50) NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(100),
    qty_sold INT NOT NULL DEFAULT 0,
    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_sales DECIMAL(12,2) GENERATED ALWAYS AS (qty_sold * selling_price) STORED,
    total_cost DECIMAL(12,2) GENERATED ALWAYS AS (qty_sold * unit_cost) STORED,
    profit DECIMAL(12,2) GENERATED ALWAYS AS ((qty_sold * selling_price) - (qty_sold * unit_cost)) STORED,
    cashier_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barcode) REFERENCES items(barcode) ON UPDATE CASCADE,
    FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- SUPPLIERS TABLE (Optional)
-- ============================================================
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact VARCHAR(100),
    address TEXT,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SYSTEM SETTINGS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- DEFAULT DATA
-- ============================================================

-- Default users inserted via setup.php (run setup.php after importing this SQL)
-- setup.php generates correct bcrypt hashes on your actual server

-- Default settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('store_name', 'My Store'),
('currency_symbol', '₱'),
('low_stock_threshold', '10');

-- Sample Items from Excel
SET FOREIGN_KEY_CHECKS = 0;

INSERT IGNORE INTO items (barcode, item_id, item_name, category, unit, supplier, unit_cost, selling_price, reorder_level) VALUES
('8901001000001','ITM-001','PVC Pipe 1/2"','Hardware','pcs','BuildersPH',45.00,85.00,20),
('8901001000002','ITM-002','Cement (40kg)','Hardware','bag','BuildersPH',270.00,320.00,15),
('8901001000003','ITM-003','G.I. Wire #16','Hardware','kg','MetalTech',65.00,120.00,10),
('8901001000004','ITM-004','Paint Brush 2"','Hardware','pcs','ColorWorld',28.00,55.00,25),
('8901001000005','ITM-005','Electrical Tape','Hardware','roll','WirePro',18.00,35.00,30),
('8901002000001','ITM-006','Rice (Premium 25kg)','Grocery','sack','AgriSupply',1100.00,1350.00,5),
('8901002000002','ITM-007','Sardines (Canned)','Grocery','pcs','FoodDist',15.00,25.00,50),
('8901002000003','ITM-008','Cooking Oil (1L)','Grocery','bottle','FoodDist',72.00,95.00,20),
('8901002000004','ITM-009','Sugar (1kg)','Grocery','kg','AgriSupply',65.00,85.00,15),
('8901002000005','ITM-010','Instant Noodles','Grocery','pcs','FoodDist',8.00,14.00,100),
('8901003000001','ITM-011','Shampoo Sachet','Resort Supplies','pcs','HygieneHub',4.50,12.00,50),
('8901003000002','ITM-012','Bath Soap Bar','Resort Supplies','pcs','HygieneHub',18.00,35.00,30),
('8901003000003','ITM-013','Mineral Water 500mL','Resort Supplies','bottle','DrinkSupply',8.00,20.00,100),
('8901003000004','ITM-014','Tissue Paper','Resort Supplies','roll','HygieneHub',12.00,22.00,40),
('8901004000001','ITM-015','Soft Drink 1.5L','Beverages','bottle','BevDistPH',42.00,75.00,24),
('8901004000002','ITM-016','Beer (330mL)','Beverages','can','BevDistPH',35.00,65.00,48),
('8901004000003','ITM-017','Energy Drink','Beverages','can','BevDistPH',45.00,80.00,36),
('8901004000004','ITM-018','Bottled Water 500mL','Beverages','bottle','DrinkSupply',6.00,15.00,60);

-- Sample Stock In (created_by=NULL safe — FK checks off)
INSERT IGNORE INTO stock_in (reference_no, date, barcode, item_name, category, qty_in, unit_cost, supplier_notes, created_by) VALUES
('PO-20250301-001','2025-03-01','8901001000001','PVC Pipe 1/2"','Hardware',80,45.00,'BuildersPH',NULL),
('PO-20250301-002','2025-03-01','8901001000002','Cement (40kg)','Hardware',30,270.00,'BuildersPH',NULL),
('PO-20250302-001','2025-03-02','8901002000001','Rice (Premium 25kg)','Grocery',10,1100.00,'AgriSupply',NULL),
('PO-20250302-002','2025-03-02','8901002000005','Instant Noodles','Grocery',200,8.00,'FoodDist',NULL),
('PO-20250303-001','2025-03-03','8901003000001','Shampoo Sachet','Resort Supplies',150,4.50,'HygieneHub',NULL),
('PO-20250303-002','2025-03-03','8901003000003','Mineral Water 500mL','Resort Supplies',100,8.00,'DrinkSupply',NULL),
('PO-20250304-001','2025-03-04','8901004000001','Soft Drink 1.5L','Beverages',48,42.00,'BevDistPH',NULL),
('PO-20250304-002','2025-03-04','8901004000002','Beer (330mL)','Beverages',72,35.00,'BevDistPH',NULL),
('PO-20250305-001','2025-03-05','8901001000003','G.I. Wire #16','Hardware',25,65.00,'MetalTech',NULL),
('PO-20250305-002','2025-03-05','8901002000002','Sardines (Canned)','Grocery',100,15.00,'FoodDist',NULL),
('PO-20250306-001','2025-03-06','8901003000002','Bath Soap Bar','Resort Supplies',60,18.00,'HygieneHub',NULL),
('PO-20250306-002','2025-03-06','8901004000003','Energy Drink','Beverages',36,45.00,'BevDistPH',NULL),
('PO-20250307-001','2025-03-07','8901002000003','Cooking Oil (1L)','Grocery',40,72.00,'FoodDist',NULL),
('PO-20250307-002','2025-03-07','8901004000004','Bottled Water 500mL','Beverages',120,6.00,'DrinkSupply',NULL);

-- Sample Sales
INSERT IGNORE INTO sales (invoice_no, date, barcode, item_name, category, qty_sold, selling_price, unit_cost, cashier_id) VALUES
('INV-20250302-001','2025-03-02','8901001000001','PVC Pipe 1/2"','Hardware',15,85.00,45.00,NULL),
('INV-20250302-001','2025-03-02','8901002000005','Instant Noodles','Grocery',30,14.00,8.00,NULL),
('INV-20250303-002','2025-03-03','8901002000002','Sardines (Canned)','Grocery',25,25.00,15.00,NULL),
('INV-20250303-002','2025-03-03','8901003000003','Mineral Water 500mL','Resort Supplies',20,20.00,8.00,NULL),
('INV-20250304-003','2025-03-04','8901004000001','Soft Drink 1.5L','Beverages',12,75.00,42.00,NULL),
('INV-20250304-003','2025-03-04','8901004000002','Beer (330mL)','Beverages',24,65.00,35.00,NULL),
('INV-20250305-004','2025-03-05','8901003000001','Shampoo Sachet','Resort Supplies',40,12.00,4.50,NULL),
('INV-20250305-004','2025-03-05','8901002000001','Rice (Premium 25kg)','Grocery',2,1350.00,1100.00,NULL),
('INV-20250306-005','2025-03-06','8901001000002','Cement (40kg)','Hardware',5,320.00,270.00,NULL),
('INV-20250306-005','2025-03-06','8901002000003','Cooking Oil (1L)','Grocery',8,95.00,72.00,NULL),
('INV-20250307-006','2025-03-07','8901004000003','Energy Drink','Beverages',10,80.00,45.00,NULL),
('INV-20250307-006','2025-03-07','8901003000002','Bath Soap Bar','Resort Supplies',15,35.00,18.00,NULL);

SET FOREIGN_KEY_CHECKS = 1;
