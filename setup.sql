CREATE DATABASE market_ops;
USE market_ops;
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'inspector', 'supervisor') DEFAULT 'inspector',
    market_assigned VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE markets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    market_name VARCHAR(100) NOT NULL,
    location VARCHAR(200),
    total_stalls INT DEFAULT 0,
    daily_due_rate DECIMAL(10, 2) DEFAULT 5000.00,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE stall_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(50) NOT NULL,
    size_sqm DECIMAL(5, 2),
    base_rate DECIMAL(10, 2) DEFAULT 5000.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE stalls (
    id INT PRIMARY KEY AUTO_INCREMENT,
    market_id INT,
    category_id INT,
    stall_number VARCHAR(10) NOT NULL,
    section VARCHAR(10),
    daily_due_rate DECIMAL(10, 2) DEFAULT 5000.00,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (market_id) REFERENCES markets(id),
    FOREIGN KEY (category_id) REFERENCES stall_categories(id)
);
CREATE TABLE traders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    trader_code VARCHAR(15) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    business_type VARCHAR(100),
    stall_id INT,
    registration_date DATE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stall_id) REFERENCES stalls(id)
);
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    receipt_number VARCHAR(20) UNIQUE NOT NULL,
    trader_id INT,
    stall_id INT,
    amount_paid DECIMAL(10, 2) NOT NULL,
    due_amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('Cash', 'Mobile Money', 'Bank Transfer') DEFAULT 'Cash',
    mobile_money_provider VARCHAR(50),
    transaction_ref VARCHAR(50),
    collector_id INT,
    payment_date DATE,
    payment_time TIME,
    status ENUM('verified', 'pending', 'disputed') DEFAULT 'verified',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trader_id) REFERENCES traders(id),
    FOREIGN KEY (stall_id) REFERENCES stalls(id),
    FOREIGN KEY (collector_id) REFERENCES users(id)
);
CREATE TABLE password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    token VARCHAR(255) NOT NULL,
    expiry DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
INSERT INTO markets (
        market_name,
        location,
        total_stalls,
        daily_due_rate
    )
VALUES (
        'Kroo Town Road Market',
        'Kroo Town Road, Central Freetown',
        250,
        5000.00
    ),
    (
        'Dove Cot Market',
        'Dove Cot, East Freetown',
        180,
        5000.00
    ),
    (
        'King Jimmy Market',
        'King Jimmy, Central Freetown',
        150,
        10000.00
    ),
    (
        'Big Market',
        'Wallace Johnson Street, Freetown',
        300,
        7500.00
    );
INSERT INTO stall_categories (category_name, size_sqm, base_rate)
VALUES ('Standard Open Stall', 4.00, 5000.00),
    ('Covered Stall', 6.00, 10000.00),
    ('Lock-up Shop', 12.00, 15000.00),
    ('Table Space', 2.00, 3000.00);
INSERT INTO stalls (
        market_id,
        category_id,
        stall_number,
        section,
        daily_due_rate,
        status
    )
VALUES (1, 1, 'A01', 'A', 5000.00, 'available'),
    (1, 1, 'A02', 'A', 5000.00, 'available'),
    (1, 2, 'B01', 'B', 10000.00, 'available'),
    (2, 1, 'A01', 'A', 5000.00, 'available'),
    (2, 2, 'B01', 'B', 10000.00, 'available'),
    (3, 1, 'A01', 'A', 10000.00, 'available'),
    (3, 3, 'C01', 'C', 20000.00, 'available'),
    (4, 1, 'A01', 'A', 7500.00, 'available'),
    (4, 2, 'B01', 'B', 15000.00, 'available');
-- PASTE THE OUTPUT FROM hash_generator.php BELOW THIS LINE
-- ============================================================
-- Admin User (Password: AdminFreetown@2026)
INSERT INTO users (
        full_name,
        email,
        phone,
        username,
        password,
        role,
        market_assigned
    )
VALUES (
        'System Administrator',
        'admin@marketops.freetown',
        '+232-76-000-000',
        'admin@marketops.freetown',
        'PASTE_ADMIN_HASH_HERE',
        'admin',
        'All Markets'
    );
-- Inspector User (Password: AdminFreetown@2026)
INSERT INTO users (
        full_name,
        email,
        phone,
        username,
        password,
        role,
        market_assigned
    )
VALUES (
        'Alieu Kamara',
        'inspector@marketops.freetown',
        '+232-77-111-111',
        'inspector@marketops.freetown',
        'PASTE_INSPECTOR_HASH_HERE',
        'inspector',
        'Kroo Town Road Market'
    );