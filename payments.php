<?php
require_once 'config.php';
if (!isLoggedIn())
    redirect('login.php');

$userName = $_SESSION['user_name'];
$date_filter = $_GET['date'] ?? date('Y-m-d');
$market_filter = $_GET['market_id'] ?? '';

$markets = $pdo->query("SELECT * FROM markets WHERE status='active'")->fetchAll();

// Build query
$where = "WHERE p.payment_date = ?";
$params = [$date_filter];

if ($market_filter) {
    $where .= " AND m.id = ?";
    $params[] = $market_filter;
}

$stmt = $pdo->prepare("
    SELECT p.*, CONCAT(t.first_name, ' ', t.last_name) as trader_name, t.trader_code,
           s.stall_number, m.market_name, u.full_name as collector_name,
           p.mobile_money_provider, p.transaction_ref, p.status as payment_status
    FROM payments p 
    JOIN traders t ON p.trader_id = t.id 
    JOIN stalls s ON p.stall_id = s.id 
    JOIN markets m ON s.market_id = m.id 
    LEFT JOIN users u ON p.collector_id = u.id 
    $where 
    ORDER BY p.payment_time DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll();

$totalCollected = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_date = ?");
$totalCollected->execute([$date_filter]);
$total = $totalCollected->fetchColumn();

$cashTotal = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_date = ? AND payment_method = 'Cash'");
$cashTotal->execute([$date_filter]);
$cashAmount = $cashTotal->fetchColumn();

$mobileTotal = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_date = ? AND payment_method = 'Mobile Money'");
$mobileTotal->execute([$date_filter]);
$mobileAmount = $mobileTotal->fetchColumn();

$bankTotal = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_date = ? AND payment_method = 'Bank Transfer'");
$bankTotal->execute([$date_filter]);
$bankAmount = $bankTotal->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Market Operations</title>
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
            margin-bottom: 25px;
            font-size: 17px;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 4px;
            font-size: 13px;
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
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .summary-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-item {
            background: white;
            padding: 18px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .summary-item .amount {
            font-size: 22px;
            font-weight: bold;
        }

        .summary-item .label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .cash-amount {
            color: #16a34a;
        }

        .mobile-amount {
            color: #f59e0b;
        }

        .bank-amount {
            color: #3b82f6;
        }

        .total-amount {
            color: #14532d;
        }

        .filters {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .filters input,
        .filters select {
            padding: 8px 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 12px;
        }

        th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
        }

        .badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
        }

        .badge-cash {
            background: #dcfce7;
            color: #16a34a;
        }

        .badge-orange {
            background: #fff7ed;
            color: #ea580c;
        }

        .badge-africell {
            background: #fef3c7;
            color: #d97706;
        }

        .badge-bank {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-verified {
            background: #dcfce7;
            color: #16a34a;
        }

        .badge-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
        }

        .btn-primary {
            background: #16a34a;
            color: white;
        }

        .btn-logout {
            background: #ef4444;
            color: white;
            padding: 6px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 11px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }

            .main-content {
                margin-left: 200px;
                padding: 15px;
            }

            .summary-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>🏪 Market Ops</h2>
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="traders.php">👥 Traders</a>
        <a href="add-trader.php">➕ Add Trader</a>
        <a href="payments.php" class="active">💰 Payments</a>
        <a href="collect-payment.php">💳 Collect Dues</a>
        <a href="reports.php">📈 Reports</a>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1>💰 Payment History</h1>
                <p style="color: #64748b; font-size: 12px;">Real-time payment tracking</p>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
                <span style="font-size:12px;color:#64748b;">
                    <?php echo htmlspecialchars($userName); ?>
                </span>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-row">
            <div class="summary-item">
                <div class="amount total-amount">SLL
                    <?php echo number_format($total); ?>
                </div>
                <div class="label">Total Collected</div>
            </div>
            <div class="summary-item">
                <div class="amount cash-amount">SLL
                    <?php echo number_format($cashAmount); ?>
                </div>
                <div class="label">Cash Payments</div>
            </div>
            <div class="summary-item">
                <div class="amount mobile-amount">SLL
                    <?php echo number_format($mobileAmount); ?>
                </div>
                <div class="label">Mobile Money</div>
            </div>
            <div class="summary-item">
                <div class="amount bank-amount">SLL
                    <?php echo number_format($bankAmount); ?>
                </div>
                <div class="label">Bank Transfers</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <form method="GET" class="filters">
                <div>
                    <label style="font-size:11px;font-weight:600;display:block;">Date</label>
                    <input type="date" name="date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;display:block;">Market</label>
                    <select name="market_id" onchange="this.form.submit()">
                        <option value="">All Markets</option>
                        <?php foreach ($markets as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo $market_filter == $m['id'] ? 'selected' : ''; ?>>
                                <?php echo $m['market_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="payments.php" class="btn" style="background:#e2e8f0;color:#475569;">Reset</a>
            </form>
        </div>

        <!-- Payments Table -->
        <div class="card">
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Time</th>
                            <th>Trader</th>
                            <th>Market</th>
                            <th>Stall</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Provider</th>
                            <th>Transaction Ref</th>
                            <th>Status</th>
                            <th>Collector</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($payments) > 0): ?>
                            <?php foreach ($payments as $payment):
                                $methodBadge = 'badge-cash';
                                if ($payment['payment_method'] == 'Mobile Money' && $payment['mobile_money_provider'] == 'Orange Money')
                                    $methodBadge = 'badge-orange';
                                elseif ($payment['payment_method'] == 'Mobile Money' && $payment['mobile_money_provider'] == 'Africell Money')
                                    $methodBadge = 'badge-africell';
                                elseif ($payment['payment_method'] == 'Bank Transfer')
                                    $methodBadge = 'badge-bank';

                                $statusBadge = $payment['payment_status'] == 'verified' ? 'badge-verified' : 'badge-pending';
                                ?>
                                <tr>
                                    <td><strong>
                                            <?php echo $payment['receipt_number']; ?>
                                        </strong></td>
                                    <td>
                                        <?php echo date('H:i:s', strtotime($payment['payment_time'])); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($payment['trader_name']); ?><br><small
                                            style="color:#94a3b8;">
                                            <?php echo $payment['trader_code']; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($payment['market_name']); ?>
                                    </td>
                                    <td>
                                        <?php echo $payment['stall_number']; ?>
                                    </td>
                                    <td><strong>SLL
                                            <?php echo number_format($payment['amount_paid']); ?>
                                        </strong></td>
                                    <td><span class="badge <?php echo $methodBadge; ?>">
                                            <?php echo $payment['payment_method']; ?>
                                        </span></td>
                                    <td>
                                        <?php echo $payment['mobile_money_provider'] ?: '-'; ?>
                                    </td>
                                    <td><small>
                                            <?php echo $payment['transaction_ref'] ?: '-'; ?>
                                        </small></td>
                                    <td><span class="badge <?php echo $statusBadge; ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span></td>
                                    <td>
                                        <?php echo htmlspecialchars($payment['collector_name'] ?: 'System'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="no-data">
                                    <p>No payments recorded for this date.</p>
                                    <a href="collect-payment.php" class="btn btn-primary"
                                        style="margin-top:10px;display:inline-block;">Collect New Payment</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>