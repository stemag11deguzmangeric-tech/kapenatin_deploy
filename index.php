<?php
require("config.php");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Kape Natin Coffee Co.</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5ede3; min-height: 100vh; }
        @font-face {
        font-family: 'Quiapo Free';
        src: url('Quiapo_Free.ttf');
        }
        /* HEADER */
        header {
            background: linear-gradient(135deg, #3d1c0b 0%, #8b5e3c 100%);
            box-shadow: 0 4px 12px rgba(61,28,11,0.3);
            width: 100%;
        }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 20px 30px; }
        .logo { font-family: 'Quiapo Free'; font-size: 32px; font-weight: bold; color: white; letter-spacing: 6px; }
        .logo a{text-decoration: none; color:white;}
        .nav-links { display: flex; gap: 20px; align-items: center; }
        .nav-links a {
            text-decoration: none; color: white; font-weight: 500;
            padding: 8px 16px; border-radius: 5px; transition: all 0.3s;
        }
        .nav-links a:hover { background: rgba(255,255,255,0.2); }

        .hero {
            background-image: url(bg.png);
            padding: 80px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse 60% 70% at 60% 50%, rgba(200,134,75,0.15) 0%, transparent 70%);
        }
        .hero::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 40px; 
            background: linear-gradient(to bottom, transparent, white);
            pointer-events: none; 
        }
        .hero-content { position: relative; z-index: 1; max-width: 700px; margin: 0 auto; }
        .hero-tag {
            display: inline-block;
            background: rgba(212,160,84,0.2); border: 1px solid rgba(212,160,84,0.4);
            color: #d4a054; font-size: 12px; font-weight: 600;
            letter-spacing: 2px; text-transform: uppercase;
            padding: 6px 18px; border-radius: 50px; margin-bottom: 24px;
        }
        .hero h1 {
            font-family: 'Quiapo Free'; font-size: 72px; font-weight: bold; color: white;
            line-height: 1.15; margin-bottom: 5px;
        }
        .hero h1 span { color: #d4a054; }
        .hero p {
            font-size: 18px; color: rgba(245,237,227,0.8);
            line-height: 1.7; margin-bottom: 36px; max-width: 520px; margin-left: auto; margin-right: auto;
        }
        .hero-buttons { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
        .btn-hero-primary {
            background: linear-gradient(135deg, #c8864b, #d4a054);
            color: white; padding: 14px 36px; border-radius: 50px;
            text-decoration: none; font-size: 16px; font-weight: 600;
            box-shadow: 0 6px 20px rgba(200,134,75,0.4);
            transition: all 0.3s; display: inline-block;
        }
        .btn-hero-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(200,134,75,0.5); }
        .btn-hero-secondary {
            background: transparent; color: white;
            padding: 14px 32px; border-radius: 50px;
            border: 2px solid rgba(255,255,255,0.4);
            text-decoration: none; font-size: 16px; font-weight: 600;
            transition: all 0.3s; display: inline-block;
        }
        .btn-hero-secondary:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.7); }
        .hero-bottom { display: flex; gap: 40px; justify-content: center; margin-top: 50px; flex-wrap: wrap; }
        .hero-stat { color: rgba(245,237,227,0.7); text-align: center; }
        .hero-stat strong { display: block; font-size: 28px; font-weight: bold; color: #d4a054; }
        .hero-stat span { font-size: 13px; }

        /* FEATURES STRIP */
        .features {
            background: white; padding: 50px 30px;
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 30px; max-width: 1100px; margin: 0 auto;
        }
        .feature { text-align: center; padding: 20px; }
        .feature-icon { font-size: 40px; margin-bottom: 14px; }
        .feature h3 { font-size: 17px; font-weight: 600; color: #3d1c0b; margin-bottom: 8px; }
        .feature p { font-size: 14px; color: #888; line-height: 1.6; }

        /* SECTION */
        .section { padding: 60px 30px; }
        .section-inner { max-width: 1100px; margin: 0 auto; }
        .section-title { text-align: center; margin-bottom: 40px; }
        .section-title h2 { font-family:'Quiapo Free'; font-size: 48px; font-weight: light; color: #3d1c0b; margin-bottom: 8px; }
        .section-title p { color: #888; font-size: 15px; }

        /* MENU PREVIEW */
        .menu-preview-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;
        }
        .menu-preview-card {
            background: white; border-radius: 10px;
            padding: 20px; text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .menu-preview-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(139,94,60,0.15); }
        .menu-preview-icon { font-size: 42px; margin-bottom: 12px; }
        .menu-preview-card h3 { font-size: 16px; font-weight: 600; color: #333; margin-bottom: 6px; }
        .menu-preview-card p { font-size: 13px; color: #888; margin-bottom: 12px; }
        .menu-preview-price { font-size: 20px; font-weight: bold; color: #c8864b; }

        /* LOYALTY SECTION */
        .loyalty-section {
            background: linear-gradient(135deg, #3d1c0b, #5c2d0e);
            padding: 60px 30px; text-align: center;
        }
        .loyalty-section h2 { font-family: 'Quiapo Free'; font-size: 52px; font-weight: light; color: white; margin-bottom: 12px; }
        .loyalty-section p { font-size: 16px; color: rgba(245,237,227,0.75); margin-bottom: 30px; max-width: 500px; margin-left: auto; margin-right: auto; }
        .loyalty-steps {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px; max-width: 800px; margin: 0 auto 36px;
        }
        .loyalty-step {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,84,0.2);
            border-radius: 10px; padding: 24px;
        }
        .loyalty-step-num {
            width: 40px; height: 40px; background: #c8864b; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; color: white; font-size: 18px; margin: 0 auto 12px;
        }
        .loyalty-step h4 { font-size: 15px; font-weight: 600; color: #d4a054; margin-bottom: 6px; }
        .loyalty-step p { font-size: 13px; color: rgba(245,237,227,0.65); }

        /* FOOTER */
        footer {
            background: #3d1c0b; color: rgba(245,237,227,0.7);
            padding: 40px 30px 24px;
        }
        .footer-inner {
            max-width: 1100px; margin: 0 auto;
            display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 40px; margin-bottom: 30px;
        }
        .footer-brand .logo { font-size: 22px; font-weight: bold; color: #d4a054; display: block; margin-bottom: 12px; }
        .footer-brand p { font-size: 13px; line-height: 1.7; max-width: 250px; }
        footer h4 { font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #d4a054; margin-bottom: 14px; }
        footer a { display: block; color: rgba(245,237,227,0.6); text-decoration: none; font-size: 14px; margin-bottom: 8px; transition: color 0.2s; }
        footer a:hover { color: #d4a054; }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.08); padding-top: 20px; text-align: center; font-size: 12px; max-width: 1100px; margin: 0 auto; }

        @media (max-width: 768px) {
            .hero h1 { font-size: 34px; }
            .footer-inner { grid-template-columns: 1fr; gap: 24px; }
        }
    </style>
</head>
<body>

<header>
    <nav>
        <div class="logo"> <a href = "index.php">KAPENATIN</a></div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="menu.php">Menu</a>
            <a href="login.php">Login</a>
            <a href="signup.php">Sign Up</a>
        </div>
    </nav>
</header>

<!-- HERO -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-tag">✦ Crafted with Passion</div>
        <h1>Every Sip, a<br><span>Moment to Savour</span></h1>
        <p>From single-origin espresso to hand-crafted seasonal specials — Kape Natin is where community gathers, stories are shared, and coffee becomes an art.</p>
        <div class="hero-buttons">
            <a href="menu.php" class="btn-hero-primary">☕ Order Now</a>
            <a href="login.php" class="btn-hero-secondary">My Account →</a>
        </div>
        <div class="hero-bottom">
            <div class="hero-stat"><strong>20+</strong><span>Menu Items</span></div>
            <div class="hero-stat"><strong>4.9★</strong><span>Customer Rating</span></div>
            <div class="hero-stat"><strong>7AM-10PM</strong><span>Open Daily</span></div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<div style="background:white; padding: 50px 30px;">
    <div style="max-width:1100px; margin:0 auto; display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:30px;">
        <div class="feature">
            <div class="feature-icon">🌿</div>
            <h3>Ethically Sourced</h3>
            <p>Single-origin beans sourced from local Philippine farms with care.</p>
        </div>
        <div class="feature">
            <div class="feature-icon">⚙️</div>
            <h3>Fully Customizable</h3>
            <p>Choose your size, milk, sugar level, and add-ons for every drink.</p>
        </div>
        <div class="feature">
            <div class="feature-icon">⭐</div>
            <h3>Loyalty Rewards</h3>
            <p>Earn points on every order and redeem them for discounts.</p>
        </div>
        <div class="feature">
            <div class="feature-icon">⚡</div>
            <h3>Quick Service</h3>
            <p>Order from our kiosk and your drink is ready in minutes.</p>
        </div>
    </div>
</div>

<!-- MENU PREVIEW -->
<section class="section" style="background:#f5ede3;">
    <div class="section-inner">
        <div class="section-title">
            <h2>What We Serve</h2>
            <p>A taste of what's waiting for you</p>
        </div>
        <div class="menu-preview-grid">
            <div class="menu-preview-card">
                <div class="menu-preview-icon">☕</div>
                <h3>Coffee</h3>
                <p>Americano, Latte, Cappuccino, Mocha & more</p>
                <div class="menu-preview-price">From ₱100</div>
            </div>
            <div class="menu-preview-card">
                <div class="menu-preview-icon">🍵</div>
                <h3>Non-Coffee</h3>
                <p>Matcha, Taro, Hot Chocolate, Strawberry Milk</p>
                <div class="menu-preview-price">From ₱130</div>
            </div>
            <div class="menu-preview-card">
                <div class="menu-preview-icon">🥤</div>
                <h3>Frappe</h3>
                <p>Mocha, Caramel, Matcha, Oreo blended frappes</p>
                <div class="menu-preview-price">From ₱170</div>
            </div>
            <div class="menu-preview-card">
                <div class="menu-preview-icon">🥐</div>
                <h3>Pastries</h3>
                <p>Croissant, Cinnamon Roll, Chocolate Brownie</p>
                <div class="menu-preview-price">From ₱75</div>
            </div>
            <div class="menu-preview-card">
                <div class="menu-preview-icon">🥪</div>
                <h3>Sandwiches</h3>
                <p>Egg & Cheese, Avocado Toast, Club Sandwich</p>
                <div class="menu-preview-price">From ₱130</div>
            </div>
        </div>
        <div style="text-align:center; margin-top:30px;">
            <a href="menu.php" class="btn-hero-primary">View Full Menu</a>
        </div>
    </div>
</section>

<!-- LOYALTY -->
<section class="loyalty-section">
    <h2>⭐Kape Natin Rewards⭐</h2>
    <p>Earn points every time you order and redeem them for discounts on your next cup.</p>
    <div class="loyalty-steps">
        <div class="loyalty-step">
            <div class="loyalty-step-num">1</div>
            <h4>Sign Up</h4>
            <p>Create a free account to start earning loyalty points.</p>
        </div>
        <div class="loyalty-step">
            <div class="loyalty-step-num">2</div>
            <h4>Order & Earn</h4>
            <p>Earn 1 point for every ₱1 spent on any order.</p>
        </div>
        <div class="loyalty-step">
            <div class="loyalty-step-num">3</div>
            <h4>Redeem</h4>
            <p>Use your points at checkout — ₱0.10 discount per point.</p>
        </div>
    </div>
    <a href="signup.php" class="btn-hero-primary">Join for Free</a>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-inner">
        <div class="footer-brand">
            <span class="logo">☕ Kape Natin</span>
            <p>A cozy corner where every cup tells a story. Honest coffee, shared with the community.</p>
        </div>
        <div>
            <h4>Navigate</h4>
            <a href="index.php">Home</a>
            <a href="menu.php">Menu</a>
            <a href="login.php">Login</a>
            <a href="signup.php">Sign Up</a>
        </div>
        <div>
            <h4>Contact</h4>
            <a href="#">📍 Tomas Claudio St. Morong, Rizal</a>
            <a href="#">📞 (02) 8123-4567</a>
            <a href="#">📧 hello@kapenatin.ph</a>
            <a href="#">🕐 Mon-Sun: 7AM - 10PM</a>
        </div>
    </div>
    <div class="footer-bottom">
        <p>© 2025 Kape Natin Coffee Co. All rights reserved. Proudly brewed in the Philippines 🇵🇭</p>
    </div>
</footer>

</body>
</html>
