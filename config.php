<?php
// config.php - Database Configuration
session_start();

$host = 'localhost';
$dbname = 'market_ops';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed. Please create database 'market_ops' first.");
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function redirect($url)
{
    header("Location: $url");
    exit();
}

function clean($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}
?>