<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = trim($_POST['username']);
    $pass = $_POST['password'];

    if (empty($input) || empty($pass)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$input, $input]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            redirect('dashboard.php');
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Freetown Market Operations</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f3d1e, #1a5c30, #0f3d1e);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-box {
            background: white;
            border-radius: 20px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.4);
            width: 420px;
            padding: 40px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 40px;
        }

        .login-header h1 {
            color: #14532d;
            font-size: 22px;
            margin-bottom: 5px;
        }

        .login-header p {
            color: #777;
            font-size: 13px;
        }

        .form-group {
            margin-bottom: 18px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #444;
            font-weight: 600;
            font-size: 13px;
        }

        .form-group input {
            width: 100%;
            padding: 13px 45px 13px 14px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 37px;
            cursor: pointer;
            font-size: 18px;
            user-select: none;
            opacity: 0.6;
        }

        .toggle-password:hover {
            opacity: 1;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(22, 163, 74, 0.3);
        }

        .error-msg {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 13px;
            border-left: 4px solid #dc2626;
        }

        .links {
            text-align: center;
            margin-top: 18px;
        }

        .links a {
            color: #16a34a;
            text-decoration: none;
            font-size: 13px;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .divider {
            margin: 10px 0;
            color: #ccc;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <div class="login-header">
            <div class="icon">🏪</div>
            <h1>Market Operations</h1>
            <p>Freetown Trader Registry System</p>
        </div>

        <div id="errorMsg" class="error-msg" style="display: <?php echo $error ? 'block' : 'none'; ?>;">
            <?php echo $error; ?>
        </div>

        <form method="POST" action="" id="loginForm" onsubmit="return validateLogin()">
            <div class="form-group">
                <label>Username or Email</label>
                <input type="text" name="username" id="username" placeholder="Enter username or email" required
                    autocomplete="off">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" id="password" placeholder="Enter password" required>
                <span class="toggle-password" onclick="togglePassword()">👁</span>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">Sign In</button>
        </form>

        <div class="links">
            <a href="forgot-password.php">Forgot Password?</a>
            <div class="divider">— or —</div>
            <a href="register.php">Create New Account</a>
        </div>
    </div>

    <script>
        function togglePassword() {
            var pw = document.getElementById('password');
            var icon = document.querySelector('.toggle-password');
            if (pw.type === 'password') {
                pw.type = 'text';
                icon.textContent = '🙈';
            } else {
                pw.type = 'password';
                icon.textContent = '👁';
            }
        }

        function validateLogin() {
            var username = document.getElementById('username').value.trim();
            var password = document.getElementById('password').value;
            var errorDiv = document.getElementById('errorMsg');

            if (username === '' || password === '') {
                errorDiv.textContent = 'Please fill in all fields.';
                errorDiv.style.display = 'block';
                return false;
            }

            // Show loading state
            var btn = document.getElementById('loginBtn');
            btn.textContent = 'Signing in...';
            btn.disabled = true;
            btn.style.opacity = '0.7';

            return true;
        }
    </script>
</body>

</html>