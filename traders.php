<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$search = $_GET['search'] ?? '';
$where = '';
$params = [];

if ($search) {
    $where = "WHERE t.first_name LIKE ? OR t.last_name LIKE ? OR t.trader_code LIKE ?";
    $s = "%$search%";
    $params = [$s, $s, $s];
}

$stmt = $pdo->prepare("SELECT t.*, s.stall_number, m.market_name FROM traders t LEFT JOIN stalls s ON t.stall_id = s.id LEFT JOIN markets m ON s.market_id = m.id $where ORDER BY t.created_at DESC LIMIT 50");
$stmt->execute($params);
$traders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traders - Market Operations</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; }
        .sidebar { width: 250px; height: 100vh; background: linear-gradient(180deg, #14532d, #052e16); position: fixed; left: 0; top: 0; padding: 20px; color: white; }
        .sidebar h2 { text-align: center; margin-bottom: 25px; }
        .sidebar a { display: block; color: white; text-decoration: none; padding: 10px 15px; border-radius: 8px; margin-bottom: 4px; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.2); }
        .main-content { margin-left: 250px; padding: 25px; }
        .top-bar { background: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 1px 5px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 13px; }
        th { background: #f8fafc; font-weight: 600; }
        .badge { padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .badge-active { background: #dcfce7; color: #16a34a; }
        .badge-inactive { background: #fee2e2; color: #dc2626; }
        .btn { padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 12px; }
        .btn-primary { background: #16a34a; color: white; }
        .btn-logout { background: #ef4444; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 12px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>🏪 Market Ops</h2>
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="traders.php" class="active">👥 Traders</a>
        <a href="add-trader.php">➕ Add Trader</a>
        <a href="payments.php">💰 Payments</a>
        <a href="collect-payment.php">💳 Collect Dues</a>
        <a href="reports.php">📈 Reports</a>
    </div>
    <div class="main-content">
        <div class="top-bar">
            <h1>Trader Registry</h1>
            <div style="display:flex;gap:10px;align-items:center;">
                <a href="add-trader.php" class="btn btn-primary">+ New Trader</a>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
        <div class="card">
            <form method="GET" style="margin-bottom:15px;display:flex;gap:10px;">
                <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;padding:8px 12px;border:2px solid #ddd;border-radius:6px;">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
            <table>
                <thead><tr><th>Code</th><th>Name</th><th>Phone</th><th>Business</th><th>Market</th><th>Stall</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if(count($traders) > 0): ?>
                        <?php foreach($traders as $t): ?>
                            <tr>
                                <td><strong><?php echo $t['trader_code']; ?></strong></td>
                                <td><?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?></td>
                                <td><?php echo $t['phone_number']; ?></td>
                                <td><?php echo $t['business_type']; ?></td>
                                <td><?php echo $t['market_name'] ?? 'N/A'; ?></td>
                                <td><?php echo $t['stall_number'] ?? 'N/A'; ?></td>
                                <td><span class="badge badge-<?php echo $t['status']=='active'?'active':'inactive'; ?>"><?php echo ucfirst($t['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center;padding:30px;">No traders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>