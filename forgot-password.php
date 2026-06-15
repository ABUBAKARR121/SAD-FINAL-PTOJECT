<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expiry) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expiry]);

            $link = "http://" . $_SERVER['HTTP_HOST'] . "/market-ops/reset-password.php?token=" . $token;
            $success = "Reset link: <a href='$link'>$link</a><br><small>Check your email in production.</small>";
        } else {
            $success = "If email exists, a reset link has been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
        <h1>🔑 Forgot Password</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div><?php else: ?>
            <form method="POST">
                <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                <button type="submit" class="btn">Send Reset Link</button>
            </form>
        <?php endif; ?>
        <div class="links"><a href="login.php">Back to Login</a></div>
    </div>
</body>

</html>