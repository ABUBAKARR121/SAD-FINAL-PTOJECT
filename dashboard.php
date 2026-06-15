<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userRole = $_SESSION['user_role'];

// Get counts
$totalTraders = $pdo->query("SELECT COUNT(*) FROM traders WHERE status='active'")->fetchColumn();
$totalStalls = $pdo->query("SELECT COUNT(*) FROM stalls")->fetchColumn();
$todayCollections = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_date = CURDATE()")->fetchColumn();
$todayPayments = $pdo->query("SELECT COUNT(*) FROM payments WHERE payment_date = CURDATE()")->fetchColumn();
$totalMarkets = $pdo->query("SELECT COUNT(*) FROM markets WHERE status='active'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Market Operations</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f1f5f9;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            background: linear-gradient(180deg, #14532d, #052e16);
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px;
            color: white;
        }

        .sidebar h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 18px;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: background 0.3s;
            font-size: 14px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255, 255, 255, 0.2);
        }

        .main-content {
            margin-left: 250px;
            padding: 25px;
        }

        .top-bar {
            background: white;
            padding: 18px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.05);
        }

        .top-bar h1 {
            font-size: 22px;
            color: #1e293b;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            background: #16a34a;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
        }

        .btn-logout {
            background: #ef4444;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card .icon {
            font-size: 30px;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 26px;
            font-weight: bold;
            color: #14532d;
        }

        .stat-card .label {
            color: #64748b;
            font-size: 12px;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .actions {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.05);
        }

        .actions h3 {
            margin-bottom: 18px;
            color: #1e293b;
        }

        .btn-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .btn-action {
            padding: 22px;
            border-radius: 10px;
            text-align: center;
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s;
            font-size: 14px;
        }

        .btn-action:hover {
            transform: translateY(-3px);
        }

        .btn-collect {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .btn-traders {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .btn-reports {
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>🏪 Market Ops</h2>
        <a href="dashboard.php" class="active">📊 Dashboard</a>
        <a href="traders.php">👥 Traders</a>
        <a href="add-trader.php">➕ Add Trader</a>
        <a href="payments.php">💰 Payments</a>
        <a href="collect-payment.php">💳 Collect Dues</a>
        <a href="reports.php">📈 Reports</a>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1>Dashboard</h1>
                <p style="color: #64748b; font-size: 13px;">Welcome back,
                    <?php echo htmlspecialchars($userName); ?>
                </p>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($userName, 0, 1)); ?>
                </div>
                <div>
                    <strong style="font-size: 13px;">
                        <?php echo htmlspecialchars($userName); ?>
                    </strong><br>
                    <small style="color: #64748b;">
                        <?php echo ucfirst($userRole); ?>
                    </small>
                </div>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">👥</div>
                <div class="value">
                    <?php echo number_format($totalTraders); ?>
                </div>
                <div class="label">Active Traders</div>
            </div>
            <div class="stat-card">
                <div class="icon">🏪</div>
                <div class="value">
                    <?php echo number_format($totalStalls); ?>
                </div>
                <div class="label">Total Stalls</div>
            </div>
            <div class="stat-card">
                <div class="icon">💰</div>
                <div class="value">SLL
                    <?php echo number_format($todayCollections); ?>
                </div>
                <div class="label">Today's Collections</div>
            </div>
            <div class="stat-card">
                <div class="icon">✅</div>
                <div class="value">
                    <?php echo number_format($todayPayments); ?>
                </div>
                <div class="label">Today's Payments</div>
            </div>
        </div>

        <div class="actions">
            <h3>Quick Actions</h3>
            <div class="btn-row">
                <a href="collect-payment.php" class="btn-action btn-collect">💳<br>Collect Daily Dues</a>
                <a href="traders.php" class="btn-action btn-traders">👥<br>Manage Traders</a>
                <a href="reports.php" class="btn-action btn-reports">📈<br>View Reports</a>
            </div>
        </div>
    </div>
</body>

</html>