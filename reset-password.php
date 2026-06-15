<?php
require_once 'config.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$valid = false;
$userId = null;

if ($token) {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expiry > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    if ($reset) {
        $valid = true;
        $userId = $reset['user_id'];
    } else {
        $error = 'Invalid or expired link.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (strlen($pass) < 6)
        $error = 'Password must be at least 6 characters.';
    elseif ($pass !== $confirm)
        $error = 'Passwords do not match.';
    else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")->execute([$token]);
        $success = 'Password reset! <a href="login.php">Login here</a>.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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
        }

        .box {
            background: white;
            border-radius: 16px;
            padding: 35px;
            width: 400px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
        }

        .box h1 {
            text-align: center;
            color: #14532d;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
        }

        .btn {
            width: 100%;
            padding: 13px;
            background: #16a34a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
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

        .links {
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>

<body>
    <div class="box">
        <h1>🔒 Reset Password</h1>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php elseif ($valid): ?>
            <form method="POST">
                <div class="form-group"><label>New Password</label><input type="password" name="password" required></div>
                <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password"
                        required></div>
                <button type="submit" class="btn">Reset Password</button>
            </form>
        <?php endif; ?>
        <div class="links"><a href="login.php">Back to Login</a></div>
    </div>
</body>

</html>