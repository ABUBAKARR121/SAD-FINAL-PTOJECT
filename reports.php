<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$userName = $_SESSION['user_name'];
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$market_filter = $_GET['market'] ?? '';
$export = $_GET['export'] ?? '';

$markets = $pdo->query("SELECT * FROM markets WHERE status='active'")->fetchAll();

$where = "WHERE p.payment_date BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if ($market_filter) {
    $where .= " AND m.id = ?";
    $params[] = $market_filter;
}

// Overall Summary
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(p.amount_paid), 0) as grand_total,
           COUNT(p.id) as total_txns,
           COUNT(DISTINCT p.trader_id) as unique_traders,
           COUNT(DISTINCT DATE(p.payment_date)) as active_days,
           ROUND(AVG(p.amount_paid), 2) as avg_payment
    FROM payments p JOIN stalls s ON p.stall_id = s.id JOIN markets m ON s.market_id = m.id $where
");
$stmt->execute($params);
$overall = $stmt->fetch();

// Daily Data
$stmt = $pdo->prepare("
    SELECT DATE(p.payment_date) as date, COUNT(*) as txns, COUNT(DISTINCT p.trader_id) as traders,
           SUM(p.amount_paid) as total,
           SUM(CASE WHEN p.payment_method='Cash' THEN p.amount_paid ELSE 0 END) as cash,
           SUM(CASE WHEN p.payment_method='Mobile Money' THEN p.amount_paid ELSE 0 END) as mobile,
           SUM(CASE WHEN p.payment_method='Bank Transfer' THEN p.amount_paid ELSE 0 END) as bank
    FROM payments p JOIN stalls s ON p.stall_id = s.id JOIN markets m ON s.market_id = m.id $where
    GROUP BY DATE(p.payment_date) ORDER BY date DESC
");
$stmt->execute($params);
$daily = $stmt->fetchAll();

// Market Summary
$stmt = $pdo->prepare("
    SELECT m.market_name, COUNT(DISTINCT p.trader_id) as traders, COUNT(p.id) as txns,
           COALESCE(SUM(p.amount_paid), 0) as total, ROUND(AVG(p.amount_paid), 2) as avg
    FROM markets m LEFT JOIN stalls s ON m.id = s.market_id
    LEFT JOIN payments p ON s.id = p.stall_id AND p.payment_date BETWEEN ? AND ?
    GROUP BY m.id ORDER BY total DESC
");
$stmt->execute([$date_from, $date_to]);
$marketSummary = $stmt->fetchAll();

// Payment Methods
$stmt = $pdo->prepare("
    SELECT payment_method, mobile_money_provider, COUNT(*) as count, SUM(amount_paid) as total
    FROM payments p JOIN stalls s ON p.stall_id = s.id JOIN markets m ON s.market_id = m.id $where
    GROUP BY payment_method, mobile_money_provider
");
$stmt->execute($params);
$methods = $stmt->fetchAll();

// Trader Compliance
$stmt = $pdo->prepare("
    SELECT t.trader_code, CONCAT(t.first_name, ' ', t.last_name) as name,
           m.market_name, s.stall_number, COUNT(p.id) as days,
           SUM(p.amount_paid) as total, ROUND(AVG(p.amount_paid), 2) as avg
    FROM traders t JOIN stalls s ON t.stall_id = s.id JOIN markets m ON s.market_id = m.id
    LEFT JOIN payments p ON t.id = p.trader_id AND p.payment_date BETWEEN ? AND ?
    GROUP BY t.id ORDER BY total DESC LIMIT 50
");
$stmt->execute([$date_from, $date_to]);
$traders = $stmt->fetchAll();

// Trend Data
$stmt = $pdo->prepare("
    SELECT DATE(p.payment_date) as date, SUM(p.amount_paid) as total
    FROM payments p JOIN stalls s ON p.stall_id = s.id JOIN markets m ON s.market_id = m.id $where
    GROUP BY DATE(p.payment_date) ORDER BY date ASC
");
$stmt->execute($params);
$trend = $stmt->fetchAll();

$maxTrend = 1;
foreach ($trend as $t) { if ($t['total'] > $maxTrend) $maxTrend = $t['total']; }

// Handle CSV Export
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="market_report_'.date('Y-m-d').'.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    
    fputcsv($out, ['FREETOWN MARKET OPERATIONS REPORT']);
    fputcsv($out, ['Period: '.date('d M Y', strtotime($date_from)).' - '.date('d M Y', strtotime($date_to))]);
    fputcsv($out, ['']);
    fputcsv($out, ['SUMMARY']);
    fputcsv($out, ['Total Revenue', number_format($overall['grand_total'])]);
    fputcsv($out, ['Total Transactions', $overall['total_txns']]);
    fputcsv($out, ['Unique Traders', $overall['unique_traders']]);
    fputcsv($out, ['']);
    fputcsv($out, ['DAILY BREAKDOWN']);
    fputcsv($out, ['Date', 'Transactions', 'Traders', 'Cash', 'Mobile Money', 'Bank', 'Total']);
    foreach ($daily as $d) fputcsv($out, [$d['date'], $d['txns'], $d['traders'], $d['cash'], $d['mobile'], $d['bank'], $d['total']]);
    fputcsv($out, ['']);
    fputcsv($out, ['MARKET SUMMARY']);
    fputcsv($out, ['Market', 'Traders', 'Transactions', 'Total', 'Average']);
    foreach ($marketSummary as $m) fputcsv($out, [$m['market_name'], $m['traders'], $m['txns'], $m['total'], $m['avg']]);
    fputcsv($out, ['']);
    fputcsv($out, ['TRADER COMPLIANCE']);
    fputcsv($out, ['Code', 'Name', 'Market', 'Stall', 'Days Paid', 'Total Paid', 'Avg']);
    foreach ($traders as $t) fputcsv($out, [$t['trader_code'], $t['name'], $t['market_name'], $t['stall_number'], $t['days'], $t['total'], $t['avg']]);
    
    fclose($out);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Market Operations</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; }
        .sidebar { width: 250px; height: 100vh; background: linear-gradient(180deg, #14532d, #052e16); position: fixed; left: 0; top: 0; padding: 20px; color: white; }
        .sidebar h2 { text-align: center; margin-bottom: 25px; font-size: 17px; }
        .sidebar a { display: block; color: white; text-decoration: none; padding: 10px 15px; border-radius: 8px; margin-bottom: 4px; font-size: 13px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.2); }
        .main-content { margin-left: 250px; padding: 25px; }
        .top-bar { background: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .summary-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .summary-item { background: white; padding: 18px; border-radius: 10px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .summary-item .val { font-size: 22px; font-weight: bold; color: #14532d; }
        .summary-item .lbl { font-size: 11px; color: #64748b; text-transform: uppercase; }
        .filters { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 15px; }
        .filters input, .filters select { padding: 8px 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 13px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #eee; font-size: 12px; }
        th { background: #f8fafc; font-weight: 600; font-size: 11px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bar-cont { background: #e2e8f0; border-radius: 8px; height: 16px; overflow: hidden; }
        .bar { height: 100%; background: linear-gradient(90deg, #16a34a, #15803d); border-radius: 8px; display: flex; align-items: center; padding-left: 6px; color: white; font-size: 9px; font-weight: 600; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; text-decoration: none; }
        .btn-primary { background: #16a34a; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-logout { background: #ef4444; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 11px; }
        .chart-cont { width: 100%; height: 200px; display: flex; align-items: flex-end; gap: 3px; padding: 5px 0; }
        .chart-bar { flex: 1; background: linear-gradient(180deg, #16a34a, #15803d); border-radius: 3px 3px 0 0; position: relative; min-height: 3px; }
        .chart-val { position: absolute; top: -16px; left: 50%; transform: translateX(-50%); font-size: 8px; font-weight: bold; white-space: nowrap; }
        .chart-lbl { text-align: center; font-size: 9px; color: #64748b; margin-top: 3px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>🏪 Market Ops</h2>
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="traders.php">👥 Traders</a>
        <a href="add-trader.php">➕ Add Trader</a>
        <a href="payments.php">💰 Payments</a>
        <a href="collect-payment.php">💳 Collect Dues</a>
        <a href="reports.php" class="active">📈 Reports</a>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <h1>📈 Reports & Analytics</h1>
            <div style="display:flex;gap:10px;align-items:center;">
                <a href="reports.php?export=csv&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&market=<?php echo $market_filter; ?>" class="btn btn-success">📊 Download Excel</a>
                <button onclick="window.print()" class="btn btn-primary">🖨️ Print PDF</button>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
        
        <div class="card">
            <form method="GET" class="filters">
                <div><label style="font-size:11px;font-weight:600;">From</label><input type="date" name="date_from" value="<?php echo $date_from; ?>"></div>
                <div><label style="font-size:11px;font-weight:600;">To</label><input type="date" name="date_to" value="<?php echo $date_to; ?>"></div>
                <div><label style="font-size:11px;font-weight:600;">Market</label><select name="market"><option value="">All</option><?php foreach($markets as $m): ?><option value="<?php echo $m['id']; ?>" <?php echo $market_filter==$m['id']?'selected':''; ?>><?php echo $m['market_name']; ?></option><?php endforeach; ?></select></div>
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="reports.php" class="btn" style="background:#e2e8f0;">Reset</a>
            </form>
        </div>
        
        <div class="summary-row">
            <div class="summary-item"><div class="val">SLL <?php echo number_format($overall['grand_total']); ?></div><div class="lbl">Total Revenue</div></div>
            <div class="summary-item"><div class="val"><?php echo number_format($overall['total_txns']); ?></div><div class="lbl">Transactions</div></div>
            <div class="summary-item"><div class="val"><?php echo number_format($overall['unique_traders']); ?></div><div class="lbl">Unique Traders</div></div>
            <div class="summary-item"><div class="val"><?php echo number_format($overall['active_days']); ?></div><div class="lbl">Active Days</div></div>
        </div>
        
        <div class="card" style="margin-bottom:20px;">
            <h3 style="margin-bottom:15px;">📈 Daily Collection Trend</h3>
            <div class="chart-cont">
                <?php foreach($trend as $t): $h = ($t['total'] / $maxTrend) * 180; ?>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;">
                        <div class="chart-bar" style="height:<?php echo max($h,3); ?>px;"><span class="chart-val"><?php echo $t['total']>0 ? number_format($t['total']/1000,1).'K' : ''; ?></span></div>
                        <div class="chart-lbl"><?php echo date('d/m', strtotime($t['date'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="grid-2">
            <div class="card">
                <h3 style="margin-bottom:15px;">🏪 Market Performance</h3>
                <table>
                    <thead><tr><th>Market</th><th class="text-center">Traders</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        <?php foreach($marketSummary as $m): ?>
                            <tr><td><?php echo $m['market_name']; ?></td><td class="text-center"><?php echo $m['traders']; ?></td><td class="text-right"><strong>SLL <?php echo number_format($m['total']); ?></strong></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card">
                <h3 style="margin-bottom:15px;">💳 Payment Methods</h3>
                <table>
                    <thead><tr><th>Method</th><th class="text-center">Count</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        <?php 
                        $totalPM = 1;
                        foreach($methods as $pm) $totalPM += $pm['total'];
                        foreach($methods as $pm): $pct = round(($pm['total']/$totalPM)*100, 1); ?>
                            <tr>
                                <td><?php echo $pm['payment_method']; ?> <?php echo $pm['mobile_money_provider'] ? '('.$pm['mobile_money_provider'].')' : ''; ?></td>
                                <td class="text-center"><?php echo $pm['count']; ?></td>
                                <td class="text-right">SLL <?php echo number_format($pm['total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <h3 style="margin-bottom:15px;">👥 Trader Compliance</h3>
            <table>
                <thead><tr><th>Code</th><th>Name</th><th>Market</th><th>Stall</th><th class="text-center">Days</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    <?php foreach($traders as $t): ?>
                        <tr><td><strong><?php echo $t['trader_code']; ?></strong></td><td><?php echo $t['name']; ?></td><td><?php echo $t['market_name']; ?></td><td><?php echo $t['stall_number']; ?></td><td class="text-center"><?php echo $t['days']; ?></td><td class="text-right"><strong>SLL <?php echo number_format($t['total']); ?></strong></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>