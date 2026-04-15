-- Janet's Quality Catering Inventory System Database
-- Complete Schema with SMS Verification and Inventory Tracking

-- 1. Create Database
CREATE DATABASE IF NOT EXISTS cateringinventory;
USE cateringinventory;

-- 2. Users Table (For Login and Roles)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    contact_number VARCHAR(20),
    address TEXT,
    profile_photo VARCHAR(255),
    role ENUM('ADMIN', 'OWNER') DEFAULT 'ADMIN',
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. SMS Verification Table
CREATE TABLE IF NOT EXISTS sms_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    verification_code VARCHAR(10) NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone_number),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Categories Table (For Inventory Classification)
CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) UNIQUE NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Inventory Table (Single Table - All Items with Category Reference)
CREATE TABLE IF NOT EXISTS inventory (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    beginning_qty INT DEFAULT 0,
    previous_qty INT DEFAULT 0,
    extra_qty INT DEFAULT 0,
    ending_qty INT DEFAULT 0,
    per_pax_usage DECIMAL(10,4) DEFAULT 1.0000 COMMENT 'How many units used per guest/pax',
    unit VARCHAR(50) DEFAULT 'pcs',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Philippine Regions Table
CREATE TABLE IF NOT EXISTS ph_regions (
    region_code VARCHAR(20) PRIMARY KEY,
    region_name VARCHAR(100) NOT NULL,
    psgc_code VARCHAR(20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Philippine Provinces Table
CREATE TABLE IF NOT EXISTS ph_provinces (
    province_code VARCHAR(20) PRIMARY KEY,
    province_name VARCHAR(100) NOT NULL,
    region_code VARCHAR(20),
    psgc_code VARCHAR(20),
    FOREIGN KEY (region_code) REFERENCES ph_regions(region_code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Philippine Cities/Municipalities Table
CREATE TABLE IF NOT EXISTS ph_cities (
    city_code VARCHAR(20) PRIMARY KEY,
    city_name VARCHAR(100) NOT NULL,
    province_code VARCHAR(20),
    psgc_code VARCHAR(20),
    is_city BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (province_code) REFERENCES ph_provinces(province_code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Philippine Barangays Table
CREATE TABLE IF NOT EXISTS ph_barangays (
    barangay_code VARCHAR(20) PRIMARY KEY,
    barangay_name VARCHAR(100) NOT NULL,
    city_code VARCHAR(20),
    psgc_code VARCHAR(20),
    FOREIGN KEY (city_code) REFERENCES ph_cities(city_code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Events Table (Booking System with PH Address)
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME DEFAULT '12:00:00',
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    contact VARCHAR(20),
    
    -- Philippine Standard Address
    region_code VARCHAR(20),
    province_code VARCHAR(20),
    city_code VARCHAR(20),
    barangay_code VARCHAR(20),
    street_address TEXT,
    
    -- Legacy fields for backward compatibility
    customer_address VARCHAR(255),
    province VARCHAR(100),
    city VARCHAR(100),
    barangay VARCHAR(100),
    
    venue_address TEXT,
    pax INT DEFAULT 50,
    backdrop VARCHAR(100),
    notes TEXT,
    status ENUM('Pending', 'Confirmed', 'Completed', 'Cancelled') DEFAULT 'Pending',
    inventory_deducted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_event_date (event_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Event Inventory Usage Table (Track what items are used per event)
CREATE TABLE IF NOT EXISTS event_inventory_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity_used INT DEFAULT 0,
    pax_count INT DEFAULT 0,
    usage_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory(item_id) ON DELETE CASCADE,
    INDEX idx_event (event_id),
    INDEX idx_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Reports Table
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    created_by VARCHAR(100),
    status ENUM('Generated', 'Draft', 'Archived') DEFAULT 'Generated',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Activity Log Table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- INITIAL DATA
-- ---------------------------------------------------

-- Default Accounts (Password: Use password_hash in PHP for security)
-- Default passwords: admin123 and owner123
INSERT INTO users (username, password, email, first_name, last_name, contact_number, address, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@catering.com', 'Admin', 'User', '09171234567', 'Manila, Philippines', 'ADMIN'),
('owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'owner@catering.com', 'Janet', 'Santos', '09181234567', 'Bulacan, Philippines', 'OWNER');

-- Default Categories
INSERT INTO categories (category_name, description) VALUES 
('Silverware', 'Spoons, forks, knives and other metal utensils'),
('Dinnerware', 'Plates, bowls, and serving dishes'),
('Glassware', 'Drinking glasses, goblets, and glass serving items'),
('Linens', 'Tablecloths, napkins, and fabric items'),
('Equipment', 'Chafing dishes, serving equipment, and tools');

-- Sample Inventory Items with per_pax_usage

-- Silverware (category_id: 1)
INSERT INTO inventory (category_id, item_name, beginning_qty, previous_qty, extra_qty, ending_qty, per_pax_usage, unit) VALUES 
(1, 'Spoon', 100, 95, 10, 105, 1.0000, 'pcs'),
(1, 'Fork', 100, 98, 5, 103, 1.0000, 'pcs'),
(1, 'Teaspoon', 50, 48, 0, 48, 1.0000, 'pcs'),
(1, 'Dinner Knife', 80, 75, 10, 85, 1.0000, 'pcs'),
(1, 'Serving Spoon', 30, 28, 5, 33, 0.1000, 'pcs');

-- Dinnerware (category_id: 2)
INSERT INTO inventory (category_id, item_name, beginning_qty, previous_qty, extra_qty, ending_qty, per_pax_usage, unit) VALUES 
(2, 'Dinner Plate', 150, 145, 20, 165, 1.0000, 'pcs'),
(2, 'Soup Bowl', 100, 95, 10, 105, 1.0000, 'pcs'),
(2, 'Salad Plate', 100, 98, 5, 103, 1.0000, 'pcs'),
(2, 'Dessert Plate', 80, 78, 0, 78, 1.0000, 'pcs');

-- Glassware (category_id: 3)
INSERT INTO inventory (category_id, item_name, beginning_qty, previous_qty, extra_qty, ending_qty, per_pax_usage, unit) VALUES 
(3, 'Wine Glass', 60, 55, 10, 65, 1.0000, 'pcs'),
(3, 'Water Goblet', 120, 115, 15, 130, 1.0000, 'pcs'),
(3, 'Juice Glass', 100, 95, 10, 105, 1.0000, 'pcs'),
(3, 'Champagne Flute', 40, 38, 5, 43, 1.0000, 'pcs');

-- Linens (category_id: 4)
INSERT INTO inventory (category_id, item_name, beginning_qty, previous_qty, extra_qty, ending_qty, per_pax_usage, unit) VALUES 
(4, 'Table Cloth (White)', 40, 38, 5, 43, 0.1000, 'pcs'),
(4, 'Table Cloth (Pink)', 30, 28, 5, 33, 0.1000, 'pcs'),
(4, 'Table Napkin (White)', 200, 190, 20, 210, 1.0000, 'pcs'),
(4, 'Table Napkin (Pink)', 150, 145, 10, 155, 1.0000, 'pcs'),
(4, 'Seat Cover', 150, 145, 10, 155, 1.0000, 'pcs');

-- Equipment (category_id: 5)
INSERT INTO inventory (category_id, item_name, beginning_qty, previous_qty, extra_qty, ending_qty, per_pax_usage, unit) VALUES 
(5, 'Chafing Dish', 20, 18, 2, 20, 0.0500, 'pcs'),
(5, 'Food Warmer', 15, 14, 1, 15, 0.0300, 'pcs'),
(5, 'Serving Tray', 30, 28, 2, 30, 0.1000, 'pcs');

-- Sample Philippine Regions
INSERT INTO ph_regions (region_code, region_name, psgc_code) VALUES 
('01', 'Region I (Ilocos Region)', '010000000'),
('02', 'Region II (Cagayan Valley)', '020000000'),
('03', 'Region III (Central Luzon)', '030000000'),
('04A', 'Region IV-A (CALABARZON)', '040000000'),
('NCR', 'National Capital Region', '130000000');

-- Sample Provinces
INSERT INTO ph_provinces (province_code, province_name, region_code, psgc_code) VALUES 
('0314', 'Bulacan', '03', '031400000'),
('0349', 'Pampanga', '03', '034900000'),
('1374', 'Metro Manila', 'NCR', '137400000'),
('0410', 'Batangas', '04A', '041000000'),
('0434', 'Cavite', '04A', '043400000'),
('0456', 'Laguna', '04A', '045600000'),
('0458', 'Rizal', '04A', '045800000');

-- Sample Cities/Municipalities
INSERT INTO ph_cities (city_code, city_name, province_code, psgc_code, is_city) VALUES 
('031410', 'Guiguinto', '0314', '031410000', FALSE),
('031411', 'Malolos City', '0314', '031411000', TRUE),
('031412', 'Meycauayan City', '0314', '031412000', TRUE),
('137401', 'City of Manila', '1374', '137401000', TRUE),
('137402', 'Quezon City', '1374', '137402000', TRUE),
('137403', 'Makati City', '1374', '137403000', TRUE),
('137404', 'Pasig City', '1374', '137404000', TRUE),
('137405', 'Taguig City', '1374', '137405000', TRUE);

-- Sample Barangays
INSERT INTO ph_barangays (barangay_code, barangay_name, city_code, psgc_code) VALUES 
('03141001', 'Poblacion', '031410', '031410001'),
('03141002', 'Tabang', '031410', '031410002'),
('03141003', 'Tabe', '031410', '031410003'),
('13740101', 'Binondo', '137401', '137401001'),
('13740102', 'Ermita', '137401', '137401002'),
('13740201', 'Batasan Hills', '137402', '137402001'),
('13740202', 'Commonwealth', '137402', '137402002'),
('13740301', 'Poblacion', '137403', '137403001'),
('13740302', 'Bel-Air', '137403', '137403002');

-- Sample Events
INSERT INTO events (event_name, event_date, fullname, email, contact, province, city, barangay, venue_address, pax, backdrop, status) VALUES 
('Cruz Wedding Reception', '2026-05-20', 'Juan Cruz', 'juan@email.com', '09171234567', 'Bulacan', 'Guiguinto', 'Poblacion', 'Blue Gardens Event Place, Guiguinto Bulacan', 150, 'photo1.jpg', 'Confirmed'),
('Santillan 18th Birthday', '2026-06-15', 'Maria Santillan', 'maria@email.com', '09181234567', 'Metro Manila', 'Quezon City', 'Batasan Hills', 'Casa Milagros, Batasan Hills QC', 100, 'photo5.jpg', 'Pending'),
('TechCorp Annual Seminar', '2026-07-02', 'Robert Fox', 'robert@techcorp.com', '09191234567', 'Metro Manila', 'Makati', 'Poblacion', 'Grand Hotel Makati', 75, 'photo10.jpg', 'Confirmed');

-- Sample Reports
INSERT INTO reports (title, content, created_by, status) VALUES 
('Monthly Inventory Audit - April', 'All silverware accounted for. 2 glasses broken during Cruz event. Recommended to purchase additional wine glasses.', 'owner', 'Generated'),
('Quarterly Sales Summary Q1 2026', 'Total of 15 events handled for Q1 2026. Revenue exceeded target by 12%. Top performing category: Wedding packages.', 'owner', 'Generated');
