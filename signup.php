<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require("config.php");

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: menu.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $name     = $_POST['name'];
    $email    = $_POST['email'];

    if (empty($username) || empty($password) || empty($name) || empty($email)) {
        $error = 'All fields are required!';
    } else {
        $check = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->get_result()->num_rows > 0 ? $error = 'Username already exists!' : null;
        $check->close();

        if (!$error) {
            $stmt = $mysqli->prepare("INSERT INTO users (username, password, name, email) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $password, $name, $email);
            $stmt->execute() ? $success = 'Registered! You can now login.' : $error = 'Error: ' . $stmt->error;
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sign Up - Kape Natin</title>
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
        .logo { font-family: 'Quiapo Free'; font-size: 32px; font-weight: bold; color: white; letter-spacing: 6px;}
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
            min-height: 0;
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
        
        .form-panel h1 { color: #3d1c0b; margin-bottom: 6px; font-size: 28px; }
        /* ── RIGHT: HERO PANEL ── */
        .hero-panel {
            background-image: url(bg.png);
            background-position: 50%;
            display: flex;
            align-items: center;
            justify-content: left;
            padding: 60px 60px 0 120px;
            border-top-left-radius: 48px;
            border-bottom-left-radius: 48px;
            position: relative;
            overflow: hidden;
        }
        .hero-panel::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse 70% 60% at 65% 50%, rgba(200,134,75,0.18) 0%, transparent 70%);
        }
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

        .auth-subtitle { color: #999; font-size: 14px; margin-bottom: 28px; }
        .error-message {
            background: #e74c3c; color: white; padding: 10px 14px; border-radius: 6px;
            margin-bottom: 18px; font-size: 14px;
        }
        .success-message {
            background: #27ae60; color: white; padding: 10px 14px; border-radius: 6px;
            margin-bottom: 18px; font-size: 14px;
        }
        label { display: block; margin-bottom: 7px; color: #333; font-weight: 600; font-size: 13px; }
        input[type="text"], input[type="password"], input[type="email"] {
            width: 100%; padding: 11px 13px; border: 2px solid #e8e8e8; border-radius: 7px;
            font-size: 14px; margin-bottom: 18px; font-family: inherit; transition: border-color 0.2s;
        }
        input:focus { outline: none; border-color: #8b5e3c; }
        .btn-register {
            width: 100%; padding: 12px; background: linear-gradient(135deg, #3d1c0b, #8b5e3c);
            color: white; border: none; border-radius: 7px; font-size: 15px;
            font-weight: 600; cursor: pointer; transition: opacity 0.2s; margin-bottom: 10px;
        }
        .btn-register:hover { opacity: 0.88; }
        .btn-clear {
            width: 100%; padding: 12px; background: white; color: #aaa;
            border: 2px solid #e8e8e8; border-radius: 7px; font-size: 14px;
            font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .btn-clear:hover { border-color: #ccc; color: #888; }
        .login-link { margin-top: 18px; text-align: center; color: #888; font-size: 13px; }
        .login-link a { color: #8b5e3c; text-decoration: none; font-weight: 600; }
        .login-link a:hover { text-decoration: underline; }
        .back-link { text-align: center; margin-top: 14px; }
        .back-link a { color: #ccc; font-size: 12px; text-decoration: none; }
        .back-link a:hover { color: #8b5e3c; }

        /* ── PERKS LIST ── */
        .perks {
            margin: 22px 0 0;
            display: flex; flex-direction: column; gap: 10px;
        }
        .perk {
            display: flex; align-items: flex-start; gap: 10px;
            background: #fdfaf7; border: 1px solid #f0e8df;
            border-radius: 8px; padding: 10px 14px;
        }
        .perk-icon { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
        .perk-text strong { display: block; font-size: 13px; color: #3d1c0b; margin-bottom: 1px; }
        .perk-text span { font-size: 12px; color: #aaa; }

        
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

        <!-- ── LEFT: SIGNUP FORM ── -->
        <div class="form-panel">
            <h1>Create an Account</h1>
            <p class="auth-subtitle">Join Kape Natin and start earning rewards</p>

            <?php if ($error): ?>
                <div class="error-message">⚠ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success-message">✓ <?php echo htmlspecialchars($success); ?> <a href="login.php" style="color:white;font-weight:700;text-decoration:underline;">Login now →</a></div>
            <?php endif; ?>

            <form method="POST" action="signup.php">
                <label>Full Name</label>
                <input type="text" name="name" required placeholder="Enter your full name"
                    value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">

                <label>Email</label>
                <input type="email" name="email" required placeholder="Enter your email"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

                <label>Username</label>
                <input type="text" name="username" required placeholder="Choose a username"
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">

                <label>Password</label>
                <input type="password" name="password" required placeholder="Choose a password">

                <button type="submit" class="btn-register">Create Account</button>
                <button type="reset" class="btn-clear">Clear Form</button>
            </form>

            <div class="login-link">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>

            <div class="perks">
                <div class="perk">
                    <span class="perk-icon">⭐</span>
                    <div class="perk-text">
                        <strong>Loyalty Rewards</strong>
                        <span>Earn 1 point per ₱1 spent, redeem for discounts</span>
                    </div>
                </div>
                <div class="perk">
                    <span class="perk-icon">📋</span>
                    <div class="perk-text">
                        <strong>Order History</strong>
                        <span>Track all your past orders anytime</span>
                    </div>
                </div>
                <div class="perk">
                    <span class="perk-icon">⚡</span>
                    <div class="perk-text">
                        <strong>Faster Checkout</strong>
                        <span>Your name and details pre-filled at checkout</span>
                    </div>
                </div>
            </div>

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
