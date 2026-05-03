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

// Remove item
if (isset($_POST['remove_item'])) {
    $item_id = intval($_POST['item_id']);
    $stmt = $mysqli->prepare("DELETE FROM cart WHERE id = ? AND session_id = ?");
    $stmt->bind_param("is", $item_id, $session_id);
    $stmt->execute();
    $stmt->close();
    header('Location: cart.php');
    exit;
}

// Update quantity
if (isset($_POST['update_quantity'])) {
    $item_id  = intval($_POST['item_id']);
    $quantity = max(1, intval($_POST['quantity']));
    $stmt = $mysqli->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND session_id = ?");
    $stmt->bind_param("iis", $quantity, $item_id, $session_id);
    $stmt->execute();
    $stmt->close();
    header('Location: cart.php');
    exit;
}

// Get cart items
$stmt = $mysqli->prepare("SELECT * FROM cart WHERE session_id = ? ORDER BY created_at ASC");
$stmt->bind_param("s", $session_id);
$stmt->execute();
$cart_query = $stmt->get_result();
$stmt->close();

// Cart count for badge
$count_stmt = $mysqli->prepare("SELECT SUM(quantity) as total FROM cart WHERE session_id = ?");
$count_stmt->bind_param("s", $session_id);
$count_stmt->execute();
$cart_count = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

$total = 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cart - Kape Natin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-image: url(bg.png); min-height: 100vh; }
        header {
            background: linear-gradient(135deg, #3d1c0b 0%, #8b5e3c 100%);
            box-shadow: 0 4px 12px rgba(61,28,11,0.3); width: 100%;
        }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 20px 30px; }
        .logo { font-family: 'Quiapo Free'; font-size: 32px; font-weight: bold; color: white;letter-spacing: 6px; }
        .logo a{text-decoration: none; color:white;}
        .nav-links { display: flex; gap: 20px; align-items: center; }
        .nav-links a { text-decoration: none; color: white; font-weight: 500; padding: 8px 16px; border-radius: 5px; transition: all 0.3s; }
        .nav-links a:hover { background: rgba(255,255,255,0.2); }
        .cart-badge { background: #e74c3c; color: white; border-radius: 50%; padding: 2px 8px; font-size: 12px; margin-left: 5px; }
        .container { max-width: 80%; margin: 30px auto; padding: 20px; }
        .cart-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        h2 { color: #3d1c0b; margin-bottom: 30px; }
        .cart-item { display: flex; gap: 20px; padding: 20px; border-bottom: 1px solid #e0e0e0; align-items: flex-start; }
        .cart-item-icon { font-size: 40px; flex-shrink: 0; width: 70px; height: 70px; background: #f5ede3; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .cart-item-info { flex: 1; }
        .cart-item-code { font-size: 12px; color: #999; margin-bottom: 4px; }
        .cart-item-name { font-size: 17px; font-weight: 600; color: #333; margin-bottom: 6px; }
        .cart-item-specs { font-size: 13px; color: #888; margin-bottom: 4px; line-height: 1.6; }
        .cart-item-price { font-size: 14px; color: #8b5e3c; margin-bottom: 12px; }
        .cart-item-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .cart-item-actions form { display: flex; gap: 8px; align-items: center; }
        .cart-item-actions input[type="number"] { width: 60px; padding: 5px; border: 2px solid #e0e0e0; border-radius: 5px; }
        .btn-update { padding: 5px 14px; background: #8b5e3c; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; }
        .btn-update:hover { background: #3d1c0b; }
        .btn-remove { padding: 5px 14px; background: #e74c3c; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; }
        .btn-remove:hover { background: #c0392b; }
        .cart-item-total { font-size: 20px; font-weight: bold; color: #333; white-space: nowrap; }
        .cart-total { text-align: right; margin-top: 20px; font-size: 24px; font-weight: bold; color: #c8864b; }
        .cart-actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn { width: 100%; padding: 12px; color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; display: inline-block; }
        .btn-continue { background: #95a5a6; }
        .btn-continue:hover { background: #7f8c8d; }
        .btn-checkout { background: #8b5e3c; }
        .btn-checkout:hover { background: #3d1c0b; }
        .empty-cart { text-align: center; padding: 50px; color: #999; }
        .empty-cart p:first-child { font-size: 48px; margin-bottom: 20px; }
        .empty-cart p:nth-child(2) { font-size: 18px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo"> <a href = "index.php">KAPENATIN</a></div>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="menu.php">Menu</a>
                <?php if (($_SESSION['role'] ?? '') !== 'guest'): ?>
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
        <div class="cart-container">
            <h2>🛒 Your Cart</h2>

            <?php if ($cart_query->num_rows == 0): ?>
                <div class="empty-cart">
                    <p>☕</p>
                    <p>Your cart is empty</p>
                    <a href="menu.php" class="btn" style="width:auto; padding:12px 30px; margin-top:20px; background:#8b5e3c; display:inline-block;">Browse Menu</a>
                </div>
            <?php else: ?>
                <?php while ($item = $cart_query->fetch_assoc()):
                    $subtotal = $item['unit_price'] * $item['quantity'];
                    $total += $subtotal;
                ?>
                    <div class="cart-item">
                        <div class="cart-item-icon">☕</div>
                        <div class="cart-item-info">
                            <div class="cart-item-code">Code: <?php echo htmlspecialchars($item['product_code']); ?></div>
                            <div class="cart-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <div class="cart-item-specs">
                                <?php
                                $specs = [];
                                if ($item['size_name'])  $specs[] = 'Size: ' . $item['size_name'];
                                if ($item['milk_name'])  $specs[] = 'Milk: ' . $item['milk_name'];
                                if ($item['sugar_name']) $specs[] = 'Sugar: ' . $item['sugar_name'];
                                if ($item['addons'])     $specs[] = 'Add-ons: ' . $item['addons'];
                                if ($item['notes'])      $specs[] = 'Notes: ' . $item['notes'];
                                echo implode(' &bull; ', array_map('htmlspecialchars', $specs));
                                ?>
                            </div>
                            <div class="cart-item-price">₱<?php echo number_format($item['unit_price'], 2); ?> each</div>

                            <div class="cart-item-actions">
                                <form method="POST">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <label>Qty:</label>
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1">
                                    <button type="submit" name="update_quantity" class="btn-update">Update</button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" name="remove_item" class="btn-remove">Remove</button>
                                </form>
                            </div>
                        </div>
                        <div class="cart-item-total">₱<?php echo number_format($subtotal, 2); ?></div>
                    </div>
                <?php endwhile; ?>

                <?php
                $tax      = $total * 0.12;
                $grand    = $total + $tax;
                ?>
                <div style="text-align:right; margin-top:20px; font-size:15px; color:#666;">
                    Subtotal: ₱<?php echo number_format($total, 2); ?><br>
                    VAT (12%): ₱<?php echo number_format($tax, 2); ?>
                </div>
                <div class="cart-total">Total: ₱<?php echo number_format($grand, 2); ?></div>

                <div class="cart-actions">
                    <a href="menu.php" class="btn btn-continue">Continue Shopping</a>
                    <a href="checkout.php" class="btn btn-checkout">Proceed to Checkout</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
