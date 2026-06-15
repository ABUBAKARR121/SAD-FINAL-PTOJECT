<?php
// hash_generator.php
// Run this file FIRST to generate correct password hashes
// Then copy the output into setup.sql

$admin_password = 'AdminFreetown@2026';
$inspector_password = 'AdminFreetown@2026';

$admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);
$inspector_hash = password_hash($inspector_password, PASSWORD_DEFAULT);

echo "<h2>Password Hashes Generated Successfully</h2>";
echo "<hr>";
echo "<h3>Copy these SQL statements into your setup.sql file:</h3>";
echo "<pre style='background:#f5f5f5;padding:15px;border-radius:5px;'>";

echo "-- Admin User (Password: AdminFreetown@2026)
INSERT INTO users (full_name, email, phone, username, password, role, market_assigned) VALUES
('System Administrator', 'admin@marketops.freetown', '+232-76-000-000', 'admin@marketops.freetown', '$admin_hash', 'admin', 'All Markets');

-- Inspector User (Password: AdminFreetown@2026)
INSERT INTO users (full_name, email, phone, username, password, role, market_assigned) VALUES
('Alieu Kamara', 'inspector@marketops.freetown', '+232-77-111-111', 'inspector@marketops.freetown', '$inspector_hash', 'inspector', 'Kroo Town Road Market');
";

echo "</pre>";

// Verify hash works
echo "<hr>";
echo "<h3>Hash Verification Test:</h3>";
$verify = password_verify($admin_password, $admin_hash);
echo "Password 'AdminFreetown@2026' verification: " . ($verify ? "✅ SUCCESS" : "❌ FAILED");
?>