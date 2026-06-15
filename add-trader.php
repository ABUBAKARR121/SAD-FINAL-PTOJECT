<?php
require_once 'config.php';
if (!isLoggedIn())
    redirect('login.php');

$error = '';
$success = '';

$stalls = $pdo->query("SELECT s.*, m.market_name FROM stalls s JOIN markets m ON s.market_id = m.id WHERE s.status='available'")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first = clean($_POST['first_name']);
    $last = clean($_POST['last_name']);
    $gender = $_POST['gender'];
    $phone = clean($_POST['phone_number']);
    $email = clean($_POST['email']);
    $address = clean($_POST['address']);
    $biz = clean($_POST['business_type']);
    $stall = $_POST['stall_id'] ?: null;

    if (empty($first) || empty($last) || empty($phone)) {
        $error = 'Please fill required fields.';
    } else {
        $year = date('Y');
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM traders WHERE YEAR(registration_date)=?");
        $cnt->execute([$year]);
        $code = 'TRD-' . $year . '-' . str_pad($cnt->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);

        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO traders (trader_code, first_name, last_name, gender, phone_number, email, address, business_type, stall_id, registration_date, status) VALUES (?,?,?,?,?,?,?,?,?,CURDATE(),'active')")->execute([$code, $first, $last, $gender, $phone, $email, $address, $biz, $stall]);
            if ($stall)
                $pdo->prepare("UPDATE stalls SET status='occupied' WHERE id=?")->execute([$stall]);
            $pdo->commit();
            $success = "Trader registered! Code: <strong>$code</strong>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Registration failed.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Trader</title>
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
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 4px;
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

        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            max-width: 650px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
            font-size: 12px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
        }

        .btn {
            padding: 12px 25px;
            background: #16a34a;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }

        .alert {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>🏪 Market Ops</h2>
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="traders.php">👥 Traders</a>
        <a href="add-trader.php" class="active">➕ Add Trader</a>
        <a href="payments.php">💰 Payments</a>
        <a href="collect-payment.php">💳 Collect Dues</a>
        <a href="reports.php">📈 Reports</a>
    </div>
    <div class="main-content">
        <a href="traders.php" style="color:#16a34a;text-decoration:none;">← Back</a>
        <div class="card" style="margin-top:15px;">
            <h2 style="margin-bottom:20px;">Register New Trader</h2>
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php else: ?>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group"><label>First Name *</label><input type="text" name="first_name" required>
                        </div>
                        <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Gender *</label><select name="gender" required>
                                <option value="">Select</option>
                                <option>Male</option>
                                <option>Female</option>
                            </select></div>
                        <div class="form-group"><label>Phone *</label><input type="text" name="phone_number" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Email</label><input type="email" name="email"></div>
                        <div class="form-group"><label>Business Type</label><input type="text" name="business_type"></div>
                    </div>
                    <div class="form-group"><label>Address</label><textarea name="address" rows="2"></textarea></div>
                    <div class="form-group"><label>Assign Stall</label><select name="stall_id">
                            <option value="">None</option>
                            <?php foreach ($stalls as $s): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo $s['market_name'] . ' - Stall ' . $s['stall_number']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select></div>
                    <button type="submit" class="btn">Register Trader</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>