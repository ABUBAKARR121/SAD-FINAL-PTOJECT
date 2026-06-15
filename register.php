<?php
require_once 'config.php';

if (isLoggedIn())
    redirect('dashboard.php');

$error = '';
$success = '';

$markets = $pdo->query("SELECT * FROM markets WHERE status='active'")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = clean($_POST['full_name']);
    $email = clean($_POST['email']);
    $phone = clean($_POST['phone']);
    $username = clean($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = clean($_POST['role']);
    $market = clean($_POST['market_assigned']);

    if (empty($full_name) || empty($email) || empty($username) || empty($password)) {
        $error = 'Please fill all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);

        if ($check->rowCount() > 0) {
            $error = 'Username or email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, username, password, role, market_assigned) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $email, $phone, $username, $hash, $role, $market]);
            $success = 'Account created! <a href="login.php">Click here to login</a>.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Market Operations</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f3d1e, #1a5c30);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .reg-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 500px;
            padding: 35px;
        }

        .reg-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .reg-header .icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 28px;
        }

        .reg-header h1 {
            color: #14532d;
            font-size: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            margin-bottom: 4px;
            color: #444;
            font-weight: 600;
            font-size: 12px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 13px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #16a34a;
        }

        .btn-submit {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
        }

        .alert {
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border-left: 3px solid #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border-left: 3px solid #16a34a;
        }

        .links {
            text-align: center;
            margin-top: 15px;
        }

        .links a {
            color: #16a34a;
            text-decoration: none;
            font-size: 13px;
        }
    </style>
</head>

<body>
    <div class="reg-box">
        <div class="reg-header">
            <div class="icon">👤</div>
            <h1>Create Account</h1>
            <p style="color: #777; font-size: 12px;">Join Market Operations System</p>
        </div>

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
                <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" required></div>
                <div class="form-row">
                    <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
                    <div class="form-group"><label>Phone</label><input type="text" name="phone"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Username *</label><input type="text" name="username" required></div>
                    <div class="form-group"><label>Role</label><select name="role">
                            <option value="inspector">Inspector</option>
                            <option value="supervisor">Supervisor</option>
                        </select></div>
                </div>
                <div class="form-group"><label>Market</label><select name="market_assigned">
                        <option value="">Select</option>
                        <?php foreach ($markets as $m): ?>
                            <option value="<?php echo $m['market_name']; ?>">
                                <?php echo $m['market_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-row">
                    <div class="form-group"><label>Password *</label><input type="password" name="password" required></div>
                    <div class="form-group"><label>Confirm *</label><input type="password" name="confirm_password" required>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Create Account</button>
            </form>

        <?php endif; ?>
        <div class="links">Already have account? <a href="login.php">Sign In</a></div>
    </div>
</body>

</html>