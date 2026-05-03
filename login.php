<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require("config.php");

// If already logged in, redirect to correct page by role
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $role = $_SESSION['role'] ?? 'customer';
    if (in_array($role, ['admin', 'cashier', 'barista'])) {
        header('Location: staff_dashboard.php');
    } else {
        header('Location: menu.php');
    }
    exit;
}

$error = '';

// Guest login — no DB record, just a session flag
if (isset($_POST['guest_login'])) {
    $_SESSION['logged_in'] = true;
    $_SESSION['username']  = 'guest';
    $_SESSION['user_id']   = null;
    $_SESSION['name']      = 'Guest';
    $_SESSION['role']      = 'guest';
    $_SESSION['phone']     = '';
    header('Location: menu.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $mysqli->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['logged_in'] = true;
        $_SESSION['username']  = $user['username'];
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['name']      = $user['name'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['phone']     = $user['phone'];
        $stmt->close();

        // Redirect based on role
        if (in_array($user['role'], ['admin', 'cashier', 'barista'])) {
            header('Location: staff_dashboard.php');
        } else {
            header('Location: menu.php');
        }
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Kape Natin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh; display: flex; flex-direction: column;
        }

        /* ── NAV ── */
        header {
            background: linear-gradient(135deg, #3d1c0b 0%, #8b5e3c 100%);
            box-shadow: 0 4px 12px rgba(61,28,11,0.3);
            position: relative; z-index: 10;
        }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 18px 30px; }
        .logo { font-family: 'Quiapo Free';font-size: 32px; font-weight: bold; color: white;letter-spacing: 6px; }
        .logo a{text-decoration: none; color:white;}
        .nav-links { display: flex; gap: 16px; align-items: center; }
        .nav-links a {
            text-decoration: none; color: white; font-weight: 500;
            padding: 7px 14px; border-radius: 5px; transition: all 0.3s; font-size: 14px;
        }
        .nav-links a:hover { background: rgba(255,255,255,0.2); }

        /* ── TWO-COLUMN SPLIT ── */
        .split-layout {
            flex: 1;
            display: grid;
            grid-template-columns: 600px 1fr;
            min-height: 0; /* allows children to fill properly */
        }

        /* ── LEFT: FORM PANEL ── */
        .form-panel {
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 48px 44px;
            overflow-y: auto;
        }
        /* ── RIGHT: HERO PANEL ── */
        .hero-panel {
            background-image: url(bg.png);
            background-position: 50%;
            display: flex;
            align-items: center;
            justify-content: left;
            padding: 60px 60px 0 120px;
            position: relative;
            border-top-left-radius: 48px;
            border-bottom-left-radius: 48px;
            overflow: hidden;
        }
        /* decorative glow */
        .hero-panel::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse 70% 60% at 65% 50%, rgba(200,134,75,0.18) 0%, transparent 70%);
        }
        /* decorative large circle */
        .hero-panel::after {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            border-radius: 50%;
            border: 1px solid rgba(212,160,84,0.08);
            right: -100px; top: 50%;
            transform: translateY(-50%);
        }
        .hero-content {
            position: relative; z-index: 1;
            max-width: 480px;
        }
        .hero-tag {
            display: inline-block;
            background: rgba(212,160,84,0.15);
            border: 1px solid rgba(212,160,84,0.35);
            color: #d4a054; font-size: 11px; font-weight: 700;
            letter-spacing: 2px; text-transform: uppercase;
            padding: 5px 16px; border-radius: 50px; margin-bottom: 22px;
        }
        .hero-content h1 {
            font-family: 'Quiapo Free';
            font-size: 94px; font-weight: 800; color: white;
            line-height: 1.1; margin-bottom: 18px; letter-spacing: -0.5px;
        }
        .hero-content h1 span { color: #d4a054; }
        .hero-content p {
            font-size: 15px; color: rgba(245,237,227,0.7);
            line-height: 1.75; margin-bottom: 36px; max-width: 400px;
        }
        .hero-stats {
            display: flex; gap: 36px; flex-wrap: wrap;
        }
        .hero-stat strong {
            display: block; font-size: 26px; font-weight: 800; color: #d4a054; line-height: 1;
            margin-bottom: 4px;
        }
        .hero-stat span { font-size: 12px; color: rgba(245,237,227,0.55); text-transform: uppercase; letter-spacing: 0.5px; }

        .form-panel h1 { color: #3d1c0b; margin-bottom: 6px; font-size: 28px; }
        .auth-subtitle { color: #999; font-size: 14px; margin-bottom: 28px; }
        .error-message {
            background: #e74c3c; color: white; padding: 10px 14px; border-radius: 6px;
            margin-bottom: 18px; font-size: 14px;
        }
        label { display: block; margin-bottom: 7px; color: #333; font-weight: 600; font-size: 13px; }
        input[type="text"], input[type="password"] {
            width: 100%; padding: 11px 13px; border: 2px solid #e8e8e8; border-radius: 7px;
            font-size: 14px; margin-bottom: 18px; font-family: inherit; transition: border-color 0.2s;
        }
        input:focus { outline: none; border-color: #8b5e3c; }
        .btn-login {
            width: 100%; padding: 12px; background: linear-gradient(135deg, #3d1c0b, #8b5e3c);
            color: white; border: none; border-radius: 7px; font-size: 15px;
            font-weight: 600; cursor: pointer; transition: opacity 0.2s;
        }
        .btn-login:hover { opacity: 0.88; }
        .signup-link { margin-top: 18px; text-align: center; color: #888; font-size: 13px; }
        .signup-link a { color: #8b5e3c; text-decoration: none; font-weight: 600; }
        .signup-link a:hover { text-decoration: underline; }
        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 18px 0; color: #ccc; font-size: 12px;
        }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #ebebeb; }
        .btn-guest {
            width: 100%; padding: 11px; background: white; color: #8b5e3c;
            border: 2px solid #c8864b; border-radius: 7px; font-size: 14px;
            font-weight: 600; cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-guest:hover { background: #fdf5e8; border-color: #8b5e3c; }
        .guest-note { font-size: 11px; color: #bbb; text-align: center; margin-top: 8px; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #ccc; font-size: 12px; text-decoration: none; }
        .back-link a:hover { color: #8b5e3c; }

        
        /* ── RESPONSIVE ── */
        @media (max-width: 860px) {
            .split-layout { grid-template-columns: 1fr; }
            .hero-panel { display: none; }
            .form-panel { padding: 40px 28px; }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo"> <a href = "index.php">KAPENATIN</a></div>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="login.php">Login</a>
            </div>
        </nav>
    </header>

    <div class="split-layout">

        <!-- ── LEFT: LOGIN FORM ── -->
        <div class="form-panel">
            <h1>Welcome Back!</h1>
            <p class="auth-subtitle">Sign in to your account</p>

            <?php if ($error): ?>
                <div class="error-message">⚠ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" required placeholder="Enter your username">

                <label for="password">Password</label>
                <input type="password" name="password" id="password" required placeholder="Enter your password">

                <button type="submit" class="btn-login">Login</button>
            </form>

            <div class="signup-link">
                <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
            </div>

            <div class="divider">or</div>

            <form method="POST" action="login.php">
                <button type="submit" name="guest_login" class="btn-guest">
                    👤 Continue as Guest
                </button>
            </form>
            <p class="guest-note">No account needed — loyalty rewards not available for guests</p>

            <div class="back-link">
                <a href="index.php">← Back to Homepage</a>
            </div>
        </div>

        <!-- ── RIGHT: HERO CONTENT ── -->
        <div class="hero-panel">
            <div class="hero-content">
                <div class="hero-tag">✦ Crafted with Passion</div>
                <h1>Every Sip, a<br><span>Moment to Savour</span></h1>
                <p>From single-origin espresso to hand-crafted seasonal specials — Kape Natin is where community gathers, stories are shared, and coffee becomes an art.</p>
                <div class="hero-stats">
                    <div class="hero-stat"><strong>20+</strong><span>Menu Items</span></div>
                    <div class="hero-stat"><strong>4.9★</strong><span>Customer Rating</span></div>
                    <div class="hero-stat"><strong>7AM–10PM</strong><span>Open Daily</span></div>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
