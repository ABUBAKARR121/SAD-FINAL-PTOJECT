<?php
require_once 'config.php';
require_once 'mobile_money_config.php';

if (!isLoggedIn())
    redirect('login.php');

$userName = $_SESSION['user_name'];
$userId = $_SESSION['user_id'];
$error = '';
$success = '';
$receipt = null;
$paymentResult = null;

// Get active traders with stalls
$stmt = $pdo->prepare("
    SELECT t.id, t.trader_code, CONCAT(t.first_name, ' ', t.last_name) as trader_name,
           t.phone_number, s.id as stall_id, s.stall_number, s.daily_due_rate, 
           m.market_name, m.id as market_id
    FROM traders t 
    JOIN stalls s ON t.stall_id = s.id 
    JOIN markets m ON s.market_id = m.id 
    WHERE t.status = 'active' AND s.status = 'occupied'
    ORDER BY m.market_name, s.stall_number
");
$stmt->execute();
$traders = $stmt->fetchAll();

// Get today's collections count
$todayCount = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE payment_date = CURDATE() AND collector_id = ?");
$todayCount->execute([$userId]);
$myTodayCount = $todayCount->fetchColumn();

$todayTotal = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_date = CURDATE() AND collector_id = ?");
$todayTotal->execute([$userId]);
$myTodayTotal = $todayTotal->fetchColumn();

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trader_id = $_POST['trader_id'];
    $stall_id = $_POST['stall_id'];
    $amount_paid = floatval($_POST['amount_paid']);
    $due_amount = floatval($_POST['due_amount']);
    $payment_method = $_POST['payment_method'];
    $mobile_provider = $_POST['mobile_money_provider'] ?? null;
    $trader_phone = $_POST['trader_phone'] ?? null;

    // Generate receipt number
    $receipt_number = 'RCP-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    $transaction_ref = null;
    $payment_status = 'verified';
    $api_response = null;

    // Process Mobile Money Payment
    if ($payment_method === 'Mobile Money' && $mobile_provider) {
        // Get trader name for reference
        $traderStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM traders WHERE id = ?");
        $traderStmt->execute([$trader_id]);
        $traderName = $traderStmt->fetchColumn();

        if ($mobile_provider === 'Orange Money') {
            $paymentResult = processOrangeMoneyPayment($trader_phone, $amount_paid, $receipt_number, $traderName);
        } elseif ($mobile_provider === 'Africell Money') {
            $paymentResult = processAfricellMoneyPayment($trader_phone, $amount_paid, $receipt_number, $traderName);
        }

        if ($paymentResult && $paymentResult['success']) {
            $transaction_ref = $paymentResult['transaction_id'];
            $payment_status = 'verified';
            $api_response = json_encode($paymentResult);
        } else {
            $error = $paymentResult['message'] ?? 'Mobile payment failed. Please try again.';
        }
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            // Check for duplicate payment
            $checkDup = $pdo->prepare("SELECT id FROM payments WHERE trader_id = ? AND payment_date = CURDATE() AND status != 'disputed'");
            $checkDup->execute([$trader_id]);

            if ($checkDup->rowCount() > 0) {
                $error = 'Payment already collected for this trader today.';
                $pdo->rollBack();
            } else {
                // Insert payment
                $stmt = $pdo->prepare("
                    INSERT INTO payments (receipt_number, trader_id, stall_id, amount_paid, due_amount, 
                                          payment_method, mobile_money_provider, transaction_ref, 
                                          collector_id, payment_date, payment_time, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?)
                ");
                $stmt->execute([
                    $receipt_number,
                    $trader_id,
                    $stall_id,
                    $amount_paid,
                    $due_amount,
                    $payment_method,
                    $mobile_provider,
                    $transaction_ref,
                    $userId,
                    $payment_status
                ]);

                $pdo->commit();

                $success = true;
                $receipt = [
                    'number' => $receipt_number,
                    'amount' => $amount_paid,
                    'method' => $payment_method,
                    'provider' => $mobile_provider,
                    'transaction_ref' => $transaction_ref,
                    'date' => date('Y-m-d'),
                    'time' => date('H:i:s'),
                    'simulated' => $paymentResult['simulated'] ?? false
                ];
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error. Please try again.';
            error_log("Payment Error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collect Dues - Market Operations</title>
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
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            max-width: 600px;
            margin: 0 auto;
        }

        .stats-mini {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-mini {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-mini .num {
            font-size: 22px;
            font-weight: bold;
            color: #14532d;
        }

        .stat-mini .lbl {
            font-size: 11px;
            color: #64748b;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 12px;
            color: #475569;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        }

        .form-group input:read-only {
            background: #f8fafc;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 20px rgba(22, 163, 74, 0.3);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border-left: 4px solid #16a34a;
        }

        .alert-info {
            background: #eff6ff;
            color: #2563eb;
            border-left: 4px solid #2563eb;
        }

        .receipt-box {
            background: #f0fdf4;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            border: 2px dashed #16a34a;
            margin-top: 20px;
        }

        .receipt-box h3 {
            color: #16a34a;
            margin-bottom: 15px;
        }

        .receipt-number {
            font-size: 24px;
            font-weight: bold;
            color: #14532d;
            background: white;
            padding: 10px;
            border-radius: 8px;
            letter-spacing: 2px;
        }

        .receipt-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            text-align: left;
            margin: 15px 0;
        }

        .receipt-details div {
            padding: 4px 0;
            font-size: 13px;
        }

        .provider-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-orange {
            background: #fff7ed;
            color: #ea580c;
        }

        .badge-africell {
            background: #fef3c7;
            color: #d97706;
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 10px;
        }

        .loading-spinner.show {
            display: block;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #16a34a;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .btn-logout {
            background: #ef4444;
            color: white;
            padding: 6px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 11px;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>🏪 Market Ops</h2>
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="traders.php">👥 Traders</a>
        <a href="add-trader.php">➕ Add Trader</a>
        <a href="payments.php">💰 Payments</a>
        <a href="collect-payment.php" class="active">💳 Collect Dues</a>
        <a href="reports.php">📈 Reports</a>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1>💳 Collect Daily Dues</h1>
                <p style="color: #64748b; font-size: 12px;">Orange Money & Africell Money Accepted</p>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
                <span style="font-size:12px;color:#64748b;">
                    <?php echo htmlspecialchars($userName); ?>
                </span>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <div class="card">
            <!-- My Today Stats -->
            <div class="stats-mini">
                <div class="stat-mini">
                    <div class="num">
                        <?php echo $myTodayCount; ?>
                    </div>
                    <div class="lbl">My Today's Collections</div>
                </div>
                <div class="stat-mini">
                    <div class="num">SLL
                        <?php echo number_format($myTodayTotal); ?>
                    </div>
                    <div class="lbl">My Today's Total</div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success && $receipt): ?>
                <div class="alert alert-success">
                    <strong>✅ Payment Collected Successfully!</strong>
                    <?php if ($receipt['simulated']): ?>
                        <br><small>(Simulation Mode - Real API integration pending)</small>
                    <?php endif; ?>
                </div>

                <div class="receipt-box">
                    <h3>🧾 DIGITAL RECEIPT</h3>
                    <div class="receipt-number">
                        <?php echo $receipt['number']; ?>
                    </div>

                    <?php if ($receipt['provider']): ?>
                        <div style="margin:10px 0;">
                            <span
                                class="provider-badge <?php echo $receipt['provider'] == 'Orange Money' ? 'badge-orange' : 'badge-africell'; ?>">
                                <?php echo $receipt['provider']; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="receipt-details">
                        <div><strong>Amount:</strong></div>
                        <div>SLL
                            <?php echo number_format($receipt['amount']); ?>
                        </div>
                        <div><strong>Method:</strong></div>
                        <div>
                            <?php echo $receipt['method']; ?>
                        </div>
                        <div><strong>Date:</strong></div>
                        <div>
                            <?php echo $receipt['date']; ?>
                        </div>
                        <div><strong>Time:</strong></div>
                        <div>
                            <?php echo $receipt['time']; ?>
                        </div>
                        <?php if ($receipt['transaction_ref']): ?>
                            <div><strong>Transaction Ref:</strong></div>
                            <div><small>
                                    <?php echo $receipt['transaction_ref']; ?>
                                </small></div>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex;gap:10px;justify-content:center;">
                        <a href="collect-payment.php" class="btn-submit"
                            style="width:auto;padding:10px 25px;text-decoration:none;">Collect Another</a>
                        <a href="payments.php" class="btn-submit"
                            style="width:auto;padding:10px 25px;text-decoration:none;background:#475569;">View All
                            Payments</a>
                    </div>
                </div>
            <?php else: ?>

                <form method="POST" id="paymentForm" onsubmit="return validatePayment()">
                    <div class="form-group">
                        <label>Select Trader *</label>
                        <select name="trader_id" id="traderSelect" required onchange="updateTraderInfo()">
                            <option value="">-- Select Trader --</option>
                            <?php foreach ($traders as $trader): ?>
                                <option value="<?php echo $trader['id']; ?>" data-stall-id="<?php echo $trader['stall_id']; ?>"
                                    data-due="<?php echo $trader['daily_due_rate']; ?>"
                                    data-phone="<?php echo $trader['phone_number']; ?>"
                                    data-name="<?php echo $trader['trader_name']; ?>"
                                    data-market="<?php echo $trader['market_name']; ?>">
                                    <?php echo $trader['trader_code'] . ' - ' . $trader['trader_name'] . ' (' . $trader['market_name'] . ' - Stall ' . $trader['stall_number'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="stall_id" id="stallId">
                    <input type="hidden" name="trader_phone" id="traderPhone">

                    <div class="form-group">
                        <label>Due Amount (SLL)</label>
                        <input type="number" name="due_amount" id="dueAmount" readonly>
                    </div>

                    <div class="form-group">
                        <label>Amount Paid (SLL) *</label>
                        <input type="number" name="amount_paid" id="amountPaid" required min="0" step="100">
                    </div>

                    <div class="form-group">
                        <label>Payment Method *</label>
                        <select name="payment_method" id="paymentMethod" required onchange="toggleMobileProvider()">
                            <option value="Cash">💵 Cash</option>
                            <option value="Mobile Money">📱 Mobile Money</option>
                            <option value="Bank Transfer">🏦 Bank Transfer</option>
                        </select>
                    </div>

                    <div class="form-group" id="providerGroup" style="display:none;">
                        <label>Mobile Money Provider</label>
                        <select name="mobile_money_provider" id="mobileProvider" onchange="updateProviderInfo()">
                            <option value="">Select Provider</option>
                            <option value="Orange Money">🟠 Orange Money Sierra Leone</option>
                            <option value="Africell Money">🟡 Africell Money Sierra Leone</option>
                        </select>
                    </div>

                    <div class="form-group" id="phoneGroup" style="display:none;">
                        <label>Trader Phone Number (for Mobile Money)</label>
                        <input type="text" id="phoneDisplay" readonly style="background:#f8fafc;">
                        <small style="color:#64748b;">Phone number registered to trader's mobile money account</small>
                    </div>

                    <div class="loading-spinner" id="loadingSpinner">
                        <div class="spinner"></div>
                        <p style="margin-top:8px;color:#64748b;">Processing payment via <span id="providerName">Mobile
                                Money</span>...</p>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">💳 Collect Payment</button>
                </form>

            <?php endif; ?>
        </div>
    </div>

    <script>
        function updateTraderInfo() {
            var select = document.getElementById('traderSelect');
            var option = select.options[select.selectedIndex];

            document.getElementById('stallId').value = option.getAttribute('data-stall-id') || '';
            document.getElementById('dueAmount').value = option.getAttribute('data-due') || '';
            document.getElementById('amountPaid').value = option.getAttribute('data-due') || '';
            document.getElementById('traderPhone').value = option.getAttribute('data-phone') || '';
            document.getElementById('phoneDisplay').value = option.getAttribute('data-phone') || '';
        }

        function toggleMobileProvider() {
            var method = document.getElementById('paymentMethod').value;
            var providerGroup = document.getElementById('providerGroup');
            var phoneGroup = document.getElementById('phoneGroup');

            if (method === 'Mobile Money') {
                providerGroup.style.display = 'block';
                phoneGroup.style.display = 'block';
            } else {
                providerGroup.style.display = 'none';
                phoneGroup.style.display = 'none';
            }
        }

        function updateProviderInfo() {
            var provider = document.getElementById('mobileProvider').value;
            document.getElementById('providerName').textContent = provider || 'Mobile Money';
        }

        function validatePayment() {
            var amount = parseFloat(document.getElementById('amountPaid').value);
            var due = parseFloat(document.getElementById('dueAmount').value);
            var method = document.getElementById('paymentMethod').value;
            var provider = document.getElementById('mobileProvider').value;
            var trader = document.getElementById('traderSelect').value;

            if (!trader) {
                alert('Please select a trader.');
                return false;
            }

            if (isNaN(amount) || amount <= 0) {
                alert('Please enter a valid amount.');
                return false;
            }

            if (method === 'Mobile Money' && !provider) {
                alert('Please select a Mobile Money provider.');
                return false;
            }

            // Show loading spinner for mobile payments
            if (method === 'Mobile Money') {
                document.getElementById('loadingSpinner').classList.add('show');
                document.getElementById('submitBtn').disabled = true;
                document.getElementById('submitBtn').textContent = 'Processing Payment...';
            }

            return true;
        }
    </script>
</body>

</html>