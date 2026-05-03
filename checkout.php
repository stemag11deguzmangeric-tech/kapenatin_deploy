<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require("config.php");

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$session_id = session_id();
$user_id    = $_SESSION['user_id'];
$is_guest   = ($_SESSION['role'] ?? '') === 'guest';

// Redirect if cart is empty
$check = $mysqli->prepare("SELECT COUNT(*) as c FROM cart WHERE session_id = ?");
$check->bind_param("s", $session_id);
$check->execute();
if ($check->get_result()->fetch_assoc()['c'] == 0) {
    $check->close();
    header('Location: cart.php');
    exit;
}
$check->close();

$order_placed = false;
$order_data   = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $customer_name  = $_POST['customer_name'];
    $customer_phone = $_POST['customer_phone'];
    $payment_method = $_POST['payment_method'];

    // Loyalty points — guests get no discount and earn no points
    $discount    = 0.00;
    $points_used = 0;
    if (!$is_guest && !empty($_POST['use_points'])) {
        $lp = $mysqli->prepare("SELECT loyalty_points FROM users WHERE id = ?");
        $lp->bind_param("i", $user_id);
        $lp->execute();
        $lp_row = $lp->get_result()->fetch_assoc();
        $lp->close();
        if ($lp_row && $lp_row['loyalty_points'] > 0) {
            $points_used = $lp_row['loyalty_points'];
            $discount    = round($points_used * 0.01, 2);
        }
    }

    // Load cart
    $cart_stmt = $mysqli->prepare("SELECT * FROM cart WHERE session_id = ?");
    $cart_stmt->bind_param("s", $session_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    $cart_stmt->close();

    $subtotal = 0;
    $items    = [];
    while ($item = $cart_result->fetch_assoc()) {
        $subtotal += $item['unit_price'] * $item['quantity'];
        $items[]   = $item;
    }

    $discount   = min($discount, $subtotal);
    $taxable    = $subtotal - $discount;
    $tax        = round($taxable * 0.12, 2);
    $total      = round($taxable + $tax, 2);
    $pts_earned = $is_guest ? 0 : (int)floor($total); // guests earn no points

    $order_number = 'KN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

    $ord = $mysqli->prepare("INSERT INTO orders (order_number, user_id, customer_name, customer_phone, subtotal, tax_amount, discount_amount, total, payment_method, loyalty_points_earned, loyalty_points_used) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ord->bind_param("sissddddsis", $order_number, $user_id, $customer_name, $customer_phone, $subtotal, $tax, $discount, $total, $payment_method, $pts_earned, $points_used);

    if ($ord->execute()) {
        foreach ($items as $item) {
            $sub = $item['unit_price'] * $item['quantity'];
            $it  = $mysqli->prepare("INSERT INTO order_items (order_number, product_code, product_name, product_price, size_name, milk_name, sugar_name, addons, quantity, unit_price, subtotal, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $it->bind_param("sssdssssidds", $order_number, $item['product_code'], $item['product_name'], $item['product_price'], $item['size_name'], $item['milk_name'], $item['sugar_name'], $item['addons'], $item['quantity'], $item['unit_price'], $sub, $item['notes']);
            $it->execute();
            $it->close();
        }

        // Only update loyalty points for registered users
        if (!$is_guest) {
            if ($points_used > 0) {
                $upd = $mysqli->prepare("UPDATE users SET loyalty_points = loyalty_points - ? WHERE id = ?");
                $upd->bind_param("ii", $points_used, $user_id);
                $upd->execute();
                $upd->close();
            }
            $earn = $mysqli->prepare("UPDATE users SET loyalty_points = loyalty_points + ?, total_spent = total_spent + ? WHERE id = ?");
            $earn->bind_param("idi", $pts_earned, $total, $user_id);
            $earn->execute();
            $earn->close();
        }

        $del = $mysqli->prepare("DELETE FROM cart WHERE session_id = ?");
        $del->bind_param("s", $session_id);
        $del->execute();
        $del->close();

        $order_data   = ['order_number' => $order_number, 'customer_name' => $customer_name, 'total' => $total, 'points_earned' => $pts_earned, 'items' => $items];
        $order_placed = true;
    }
    $ord->close();
}

// Reload cart for the summary display side
$items_stmt = $mysqli->prepare("SELECT * FROM cart WHERE session_id = ?");
$items_stmt->bind_param("s", $session_id);
$items_stmt->execute();
$cart_display = $items_stmt->get_result();
$items_stmt->close();

$subtotal_disp = 0;
$items_arr     = [];
while ($item = $cart_display->fetch_assoc()) {
    $subtotal_disp += $item['unit_price'] * $item['quantity'];
    $items_arr[]    = $item;
}
$tax_disp   = round($subtotal_disp * 0.12, 2);
$total_disp = round($subtotal_disp + $tax_disp, 2);

$count_stmt = $mysqli->prepare("SELECT SUM(quantity) as total FROM cart WHERE session_id = ?");
$count_stmt->bind_param("s", $session_id);
$count_stmt->execute();
$cart_count = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

$lpts_stmt = $mysqli->prepare("SELECT loyalty_points FROM users WHERE id = ?");
$lpts_stmt->bind_param("i", $user_id);
$user_points = 0;
if (!$is_guest && $user_id) {
    $lpts_stmt->execute();
    $user_points = $lpts_stmt->get_result()->fetch_assoc()['loyalty_points'] ?? 0;
}
$lpts_stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Checkout - Kape Natin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-image: url(bg.png); min-height: 100vh; }
        header { background: linear-gradient(135deg, #3d1c0b 0%, #8b5e3c 100%); box-shadow: 0 4px 12px rgba(61,28,11,0.3); width: 100%; }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 20px 30px; }
        .logo { font-family: 'Quiapo Free'; font-size: 32px; font-weight: bold; color: white;letter-spacing: 6px; }
        .logo a{text-decoration: none; color:white;}
        .nav-links { display: flex; gap: 20px; align-items: center; }
        .nav-links a { text-decoration: none; color: white; font-weight: 500; padding: 8px 16px; border-radius: 5px; transition: all 0.3s; }
        .nav-links a:hover { background: rgba(255,255,255,0.2); }
        .cart-badge { background: #e74c3c; color: white; border-radius: 50%; padding: 2px 8px; font-size: 12px; margin-left: 5px; }
        .container { max-width: 80%; margin: 30px auto; padding: 20px; }
        .checkout-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        h2 { color: #3d1c0b; margin-bottom: 30px; }
        h3 { margin-bottom: 20px; color: #333; }
        .success-message { background: #27ae60; color: white; padding: 25px; border-radius: 10px; text-align: center; margin-bottom: 20px; }
        .success-message h2 { color: white; margin-bottom: 15px; }
        .success-message p { font-size: 16px; margin-bottom: 8px; }
        .success-message a { color: white; text-decoration: underline; }
        .checkout-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: 500; }
        input[type="text"], input[type="tel"], select {
            width: 100%; padding: 12px; border: 2px solid #e0e0e0;
            border-radius: 5px; font-size: 14px; font-family: inherit;
        }
        input:focus, select:focus { outline: none; border-color: #8b5e3c; }
        .loyalty-box { background: #fdf5e8; border: 2px solid #c8864b; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        .loyalty-box p { font-size: 14px; color: #8b5e3c; margin-bottom: 10px; font-weight: 600; }
        .loyalty-box label { font-weight: normal; display: flex; align-items: center; gap: 8px; font-size: 13px; margin-bottom: 5px; }
        .loyalty-box small { color: #888; font-size: 12px; }
        .btn { width: 100%; padding: 12px; color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn-checkout { background: #8b5e3c; }
        .btn-checkout:hover { background: #3d1c0b; }
        .order-summary { background: #f5ede3; padding: 20px; border-radius: 10px; }
        .order-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 10px 0; border-bottom: 1px solid #ddd; }
        .order-item-name { font-weight: 600; margin-bottom: 4px; font-size: 14px; }
        .order-item-specs { font-size: 12px; color: #888; }
        .order-item-price { font-weight: bold; color: #8b5e3c; white-space: nowrap; margin-left: 10px; }
        .order-total { display: flex; justify-content: space-between; padding-top: 15px; border-top: 2px solid #c8864b; font-size: 20px; font-weight: bold; color: #3d1c0b; }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo"> <a href = "index.php">KAPENATIN</a></div>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="menu.php">Menu</a>
                <?php if (!$is_guest): ?>
                <a href="orders.php">My Orders</a>
                <?php endif; ?>
                <a href="cart.php">
                    🛒 Cart
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-badge"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['role'] === 'guest' ? 'Guest' : $_SESSION['username']); ?>)</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="checkout-container">
            <?php if ($order_placed): ?>
                <div class="success-message">
                    <h2>✓ Order Placed!</h2>
                    <p>Order #: <strong><?php echo htmlspecialchars($order_data['order_number']); ?></strong></p>
                    <p>Thank you, <?php echo htmlspecialchars($order_data['customer_name']); ?>!</p>
                    <?php if (!$is_guest): ?>
                    <p>⭐ You earned <strong><?php echo number_format($order_data['points_earned']); ?> loyalty points</strong>!</p>
                    <?php else: ?>
                    <p>💡 <a href="signup.php" style="color:white;font-weight:600;">Create an account</a> to earn loyalty points on future orders!</p>
                    <?php endif; ?>
                    <p style="margin-top:15px;">
                        <a href="menu.php">Continue Ordering</a> &nbsp;|&nbsp;
                        <a href="orders.php">View My Orders</a>
                    </p>
                </div>
                <div class="order-summary" style="margin-top:20px;">
                    <h3>Order Summary</h3>
                    <?php foreach ($order_data['items'] as $item): ?>
                        <div class="order-item">
                            <div>
                                <div class="order-item-name"><?php echo htmlspecialchars($item['product_name']); ?> x<?php echo $item['quantity']; ?></div>
                                <div class="order-item-specs">
                                    <?php
                                    $sp = array_filter([$item['size_name'], $item['milk_name'], $item['sugar_name'], $item['addons']]);
                                    echo htmlspecialchars(implode(' · ', $sp));
                                    ?>
                                </div>
                            </div>
                            <div class="order-item-price">₱<?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                    <div class="order-total"><span>Total Paid:</span><span>₱<?php echo number_format($order_data['total'], 2); ?></span></div>
                </div>

            <?php else: ?>
                <h2>Checkout</h2>
                <div class="checkout-grid">
                    <div>
                        <h3>Your Information</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" name="customer_name" required value="<?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="customer_phone" placeholder="09XX-XXX-XXXX">
                            </div>
                            <div class="form-group">
                                <label>Payment Method</label>
                                <select name="payment_method">
                                    <option value="Cash">💵 Cash</option>
                                    <option value="GCash">📱 GCash</option>
                                    <option value="Maya">💜 Maya</option>
                                    <option value="Card">💳 Card</option>
                                </select>
                            </div>

                            <?php if ($user_points > 0): ?>
                            <div class="loyalty-box">
                                <p>⭐ You have <?php echo number_format($user_points); ?> points (≈ ₱<?php echo number_format($user_points * 0.01, 2); ?> discount)</p>
                                <label>
                                    <input type="checkbox" name="use_points" value="1">
                                    Use my loyalty points for a discount
                                </label>
                                <small>₱0.01 value per point</small>
                            </div>
                            <?php endif; ?>

                            <button type="submit" name="place_order" class="btn btn-checkout">
                                Place Order — ₱<?php echo number_format($total_disp, 2); ?>
                            </button>
                        </form>
                    </div>

                    <div>
                        <h3>Order Summary</h3>
                        <div class="order-summary">
                            <?php foreach ($items_arr as $item): ?>
                                <div class="order-item">
                                    <div>
                                        <div class="order-item-name"><?php echo htmlspecialchars($item['product_name']); ?> x<?php echo $item['quantity']; ?></div>
                                        <div class="order-item-specs">
                                            <?php
                                            $sp = array_filter([$item['size_name'], $item['milk_name'], $item['sugar_name'], $item['addons']]);
                                            echo htmlspecialchars(implode(' · ', $sp));
                                            ?>
                                        </div>
                                    </div>
                                    <div class="order-item-price">₱<?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></div>
                                </div>
                            <?php endforeach; ?>
                            <div style="text-align:right; padding:10px 0; font-size:14px; color:#666; line-height:1.8;">
                                Subtotal: ₱<?php echo number_format($subtotal_disp, 2); ?><br>
                                VAT (12%): ₱<?php echo number_format($tax_disp, 2); ?>
                            </div>
                            <div class="order-total">
                                <span>Total:</span>
                                <span>₱<?php echo number_format($total_disp, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
