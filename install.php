<?php
// install.php - Run this ONCE to create database and admin user

$host = 'localhost';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS market_ops");
    $pdo->exec("USE market_ops");

    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20),
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin','inspector','supervisor') DEFAULT 'inspector',
        market_assigned VARCHAR(100),
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create markets table
    $pdo->exec("CREATE TABLE IF NOT EXISTS markets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        market_name VARCHAR(100) NOT NULL,
        location VARCHAR(200),
        total_stalls INT DEFAULT 0,
        daily_due_rate DECIMAL(10,2) DEFAULT 5000.00,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create stall_categories table
    $pdo->exec("CREATE TABLE IF NOT EXISTS stall_categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        category_name VARCHAR(50) NOT NULL,
        size_sqm DECIMAL(5,2),
        base_rate DECIMAL(10,2) DEFAULT 5000.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create stalls table
    $pdo->exec("CREATE TABLE IF NOT EXISTS stalls (
        id INT PRIMARY KEY AUTO_INCREMENT,
        market_id INT,
        category_id INT,
        stall_number VARCHAR(10) NOT NULL,
        section VARCHAR(10),
        daily_due_rate DECIMAL(10,2) DEFAULT 5000.00,
        status ENUM('available','occupied','maintenance') DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (market_id) REFERENCES markets(id),
        FOREIGN KEY (category_id) REFERENCES stall_categories(id)
    )");

    // Create traders table
    $pdo->exec("CREATE TABLE IF NOT EXISTS traders (
        id INT PRIMARY KEY AUTO_INCREMENT,
        trader_code VARCHAR(15) UNIQUE NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        gender ENUM('Male','Female') NOT NULL,
        phone_number VARCHAR(20) NOT NULL,
        email VARCHAR(100),
        address TEXT,
        business_type VARCHAR(100),
        stall_id INT,
        registration_date DATE,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (stall_id) REFERENCES stalls(id)
    )");

    // Create payments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        receipt_number VARCHAR(20) UNIQUE NOT NULL,
        trader_id INT,
        stall_id INT,
        amount_paid DECIMAL(10,2) NOT NULL,
        due_amount DECIMAL(10,2) NOT NULL,
        payment_method ENUM('Cash','Mobile Money','Bank Transfer') DEFAULT 'Cash',
        mobile_money_provider VARCHAR(50),
        transaction_ref VARCHAR(50),
        collector_id INT,
        payment_date DATE,
        payment_time TIME,
        status ENUM('verified','pending','disputed') DEFAULT 'verified',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (trader_id) REFERENCES traders(id),
        FOREIGN KEY (stall_id) REFERENCES stalls(id),
        FOREIGN KEY (collector_id) REFERENCES users(id)
    )");

    // Create password_resets table
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        token VARCHAR(255) NOT NULL,
        expiry DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Insert market data
    $markets = $pdo->query("SELECT COUNT(*) FROM markets")->fetchColumn();
    if ($markets == 0) {
        $pdo->exec("INSERT INTO markets (market_name, location, total_stalls, daily_due_rate) VALUES
            ('Kroo Town Road Market', 'Kroo Town Road, Central Freetown', 250, 5000.00),
            ('Dove Cot Market', 'Dove Cot, East Freetown', 180, 5000.00),
            ('King Jimmy Market', 'King Jimmy, Central Freetown', 150, 10000.00),
            ('Big Market', 'Wallace Johnson Street, Freetown', 300, 7500.00)
        ");

        $pdo->exec("INSERT INTO stall_categories (category_name, size_sqm, base_rate) VALUES
            ('Standard Open Stall', 4.00, 5000.00),
            ('Covered Stall', 6.00, 10000.00),
            ('Lock-up Shop', 12.00, 15000.00),
            ('Table Space', 2.00, 3000.00)
        ");

        $pdo->exec("INSERT INTO stalls (market_id, category_id, stall_number, section, daily_due_rate, status) VALUES
            (1, 1, 'A01', 'A', 5000.00, 'available'),
            (1, 1, 'A02', 'A', 5000.00, 'available'),
            (1, 2, 'B01', 'B', 10000.00, 'available'),
            (2, 1, 'A01', 'A', 5000.00, 'available'),
            (2, 2, 'B01', 'B', 10000.00, 'available'),
            (3, 1, 'A01', 'A', 10000.00, 'available'),
            (3, 3, 'C01', 'C', 20000.00, 'available'),
            (4, 1, 'A01', 'A', 7500.00, 'available'),
            (4, 2, 'B01', 'B', 15000.00, 'available')
        ");
    }

    // Create admin user with CORRECT password hash
    $adminExists = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $adminExists->execute(['admin']);

    if ($adminExists->fetchColumn() == 0) {
        // This hash is for password: admin123
        $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
        $inspectorHash = password_hash('admin123', PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, username, password, role, market_assigned) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['System Administrator', 'admin@marketops.sl', '+232-76-000-000', 'admin', $adminHash, 'admin', 'All Markets']);
        $stmt->execute(['Market Inspector', 'inspector@marketops.sl', '+232-77-111-111', 'inspector', $inspectorHash, 'inspector', 'Kroo Town Road Market']);
    }

    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Installation Complete</title>
        <style>
            body { font-family: Arial; background: #f0fdf4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
            .box { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
            h1 { color: #16a34a; }
            .btn { display: inline-block; padding: 15px 40px; background: #16a34a; color: white; text-decoration: none; border-radius: 8px; font-size: 16px; margin-top: 20px; }
            .cred { background: #f0fdf4; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .cred code { background: #dcfce7; padding: 3px 8px; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class='box'>
            <h1>✅ Installation Complete!</h1>
            <p>Database and tables created successfully.</p>
            <div class='cred'>
                <p><strong>Admin Login:</strong><br>
                Username: <code>admin</code><br>
                Password: <code>admin123</code></p>
                <p><strong>Inspector Login:</strong><br>
                Username: <code>inspector</code><br>
                Password: <code>admin123</code></p>
            </div>
            <a href='login.php' class='btn'>Go to Login</a>
        </div>
    </body>
    </html>";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>