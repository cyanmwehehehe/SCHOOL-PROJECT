<?php
require_once 'config/auth.php';
require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $conn = getOLTP();
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Cantech</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #2C1810 0%, #3D2314 40%, #FF6B35 100%);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Poppins', sans-serif;
            position: relative; overflow: hidden;
        }
        /* Decorative food emojis background */
        body::before {
            content: '';
            position: absolute;
            font-size: 2rem;
            opacity: 0.05;
            width: 100%; height: 100%;
            display: flex; align-items: center;
            justify-content: center;
            flex-wrap: wrap; gap: 3rem;
            letter-spacing: 2rem;
            pointer-events: none;
        }
        .login-wrapper {
            display: flex;
            width: 100%; max-width: 900px;
            min-height: 500px;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(0,0,0,0.4);
            margin: 1rem;
        }
        /* Left panel */
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #FF6B35, #FFB347);
            padding: 3rem 2.5rem;
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            text-align: center; color: white;
        }
        .login-left .big-icon { font-size: 5rem; margin-bottom: 1rem; }
        .login-left h2 {
            font-weight: 800; font-size: 1.8rem;
            margin-bottom: 0.5rem; line-height: 1.2;
        }
        .login-left p { opacity: 0.85; font-size: 0.9rem; line-height: 1.6; }
        .login-left .features {
            margin-top: 2rem; text-align: left; width: 100%;
        }
        .login-left .feature-item {
            display: flex; align-items: center; gap: 0.8rem;
            margin-bottom: 0.8rem; font-size: 0.85rem;
        }
        .login-left .feature-icon {
            width: 32px; height: 32px; border-radius: 8px;
            background: rgba(255,255,255,0.2);
            display: flex; align-items: center;
            justify-content: center; font-size: 1rem;
            flex-shrink: 0;
        }
        /* Right panel */
        .login-right {
            width: 380px; background: white;
            padding: 3rem 2.5rem;
            display: flex; flex-direction: column;
            justify-content: center;
        }
        .login-right .welcome {
            font-size: 0.85rem; color: #FF6B35;
            font-weight: 600; text-transform: uppercase;
            letter-spacing: 1px; margin-bottom: 0.3rem;
        }
        .login-right h3 {
            font-weight: 800; font-size: 1.6rem;
            color: #2C1810; margin-bottom: 0.3rem;
        }
        .login-right .subtitle {
            color: #8B6555; font-size: 0.82rem; margin-bottom: 2rem;
        }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label {
            display: block; font-size: 0.8rem;
            font-weight: 600; color: #2C1810;
            margin-bottom: 0.4rem;
        }
        .form-group input {
            width: 100%; padding: 0.75rem 1rem;
            border: 1.5px solid #FFE4D0;
            border-radius: 12px; font-family: 'Poppins', sans-serif;
            font-size: 0.875rem; transition: all 0.2s;
            outline: none; color: #2C1810;
        }
        .form-group input:focus {
            border-color: #FF6B35;
            box-shadow: 0 0 0 3px rgba(255,107,53,0.12);
        }
        .btn-login {
            width: 100%; padding: 0.85rem;
            background: linear-gradient(135deg, #FF6B35, #E85A25);
            color: white; border: none; border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: all 0.3s;
            box-shadow: 0 6px 20px rgba(255,107,53,0.35);
            margin-top: 0.5rem;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255,107,53,0.45);
        }
        .error-box {
            background: #FADBD8; color: #C0392B;
            border-radius: 10px; padding: 0.7rem 1rem;
            font-size: 0.82rem; font-weight: 500;
            margin-bottom: 1rem;
        }
        .demo-accounts {
            margin-top: 1.5rem; padding-top: 1rem;
            border-top: 1px solid #FFE4D0;
        }
        .demo-accounts small {
            color: #8B6555; font-size: 0.75rem; display: block;
            margin-bottom: 0.5rem; font-weight: 600;
        }
        .demo-badge {
            display: inline-block; padding: 0.2rem 0.7rem;
            border-radius: 20px; font-size: 0.72rem;
            font-weight: 600; margin-right: 0.3rem;
        }
        .badge-admin-demo   { background: #FFF3CD; color: #856404; }
        .badge-cashier-demo { background: #FFE4D0; color: #E85A25; }
        @media (max-width: 640px) {
            .login-left { display: none; }
            .login-right { width: 100%; }
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <!-- Left Panel -->
    <div class="login-left">
        <div class="big-icon"></div>
        <h2>CanTech</h2>
        <p>Your all-in-one canteen management system</p>
    </div>

    <!-- Right Panel -->
    <div class="login-right">
        <div class="welcome">Welcome back</div>
        <h3>Sign in</h3>
        <p class="subtitle">Enter your credentials to access the system</p>

        <?php if ($error): ?>
        <div class="error-box">⚠️ <?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username"
                       placeholder="Enter username" required
                       autocomplete="username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password"
                       placeholder="Enter password" required
                       autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login">
                Sign In →
            </button>
        </form>           
        </div>
    </div>
</div>
</body>
</html>