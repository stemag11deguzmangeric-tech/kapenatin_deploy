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

// Stats
$count_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$total_orders = $count_stmt->get_result()->fetch_assoc()['count'];
$count_stmt->close();

$spent_stmt = $mysqli->prepare("SELECT SUM(total) as total FROM orders WHERE user_id = ?");
$spent_stmt->bind_param("i", $user_id);
$spent_stmt->execute();
$total_spent = $spent_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$spent_stmt->close();

$pts_stmt = $mysqli->prepare("SELECT loyalty_points FROM users WHERE id = ?");
$pts_stmt->bind_param("i", $user_id);
$pts_stmt->execute();
$my_points = $pts_stmt->get_result()->fetch_assoc()['loyalty_points'] ?? 0;
$pts_stmt->close();

// Orders list
$orders_stmt = $mysqli->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_query = $orders_stmt->get_result();

// Cart count for badge
$cart_stmt = $mysqli->prepare("SELECT SUM(quantity) as total FROM cart WHERE session_id = ?");
$cart_stmt->bind_param("s", $session_id);
$cart_stmt->execute();
$cart_count = $cart_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$cart_stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Orders - Kape Natin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-image: url(bg.png); min-height: 100vh; }
        header { background: linear-gradient(135deg, #3d1c0b 0%, #8b5e3c 100%); box-shadow: 0 4px 12px rgba(61,28,11,0.3); width: 100%; }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 20px 30px; }
        .logo {font-family: 'Quiapo Free'; font-size: 32px; font-weight: bold; color: white;letter-spacing: 6px; }
        .logo a{text-decoration: none; color:white;}
        .nav-links { display: flex; gap: 20px; align-items: center; }
        .nav-links a { text-decoration: none; color: white; font-weight: 500; padding: 8px 16px; border-radius: 5px; transition: all 0.3s; }
        .nav-links a:hover { background: rgba(255,255,255,0.2); }
        .cart-badge { background: #e74c3c; color: white; border-radius: 50%; padding: 2px 8px; font-size: 12px; margin-left: 5px; }
        .container { max-width: 80%; margin: 30px auto; padding: 20px; }
        .dashboard { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        h2 { color: #3d1c0b; margin-bottom: 10px; }
        .dashboard > p { color: #666; margin-bottom: 30px; }
        h3 { color: #3d1c0b; margin-bottom: 20px; margin-top: 30px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #f5ede3; padding: 25px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .stat-number { font-size: 36px; font-weight: bold; margin-bottom: 10px; color: #8b5e3c; }
        .orders-table { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; background: white; }
        thead tr { background: linear-gradient(135deg, #3d1c0b 0%, #8b5e3c 100%); color: white; }
        th { padding: 15px; text-align: left; }
        td { padding: 15px; border-bottom: 1px solid #e0e0e0; }
        .order-id { font-weight: 600; color: #8b5e3c; }
        .order-total { text-align: right; font-weight: bold; color: #27ae60; }
        .payment-badge { background: #27ae60; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px; }
        .status-badge { background: #c8864b; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px; }
        .btn-view-details { background: #8b5e3c; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 13px; }
        .btn-view-details:hover { background: #3d1c0b; }
        .order-details-row { display: none; }
        .order-details-content { padding: 20px; background: #f5ede3; }
        .order-details-inner { max-width: 700px; }
        .order-info { margin-bottom: 15px; }
        .order-info-details { margin-top: 8px; padding: 10px; background: white; border-radius: 5px; font-size: 14px; line-height: 1.8; color: #555; }
        .order-items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .order-items-table th { background: #8b5e3c; color: white; padding: 10px; text-align: left; font-size: 13px; }
        .order-items-table td { padding: 10px; border-bottom: 1px solid #e0e0e0; font-size: 13px; }
        .item-name { font-weight: 600; }
        .item-specs { font-size: 11px; color: #888; margin-top: 2px; }
        .item-code { font-size: 11px; color: #999; }
        .items-total-row td { background: #f5ede3; font-weight: bold; }
        .items-total-label { text-align: right; font-weight: bold; }
        .items-total-amount { text-align: right; font-weight: bold; font-size: 16px; color: #8b5e3c; }
        .empty-orders { text-align: center; padding: 50px; background: #f5ede3; border-radius: 10px; color: #999; }
        .empty-orders p:first-child { font-size: 48px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo"> <a href = "index.php">KAPENATIN</a></div>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="menu.php">Menu</a>
                <a href="orders.php">My Orders</a>
                <a href="cart.php">
                    🛒 Cart
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-badge"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
            </div>
        </nav>
    </header>
    <hr style = "color: white";>
    <div class="container">
        <div class="dashboard">
            <h2>My Orders</h2>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username']); ?>!</p>

            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_orders; ?></div>
                    <div>Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">₱<?php echo number_format($total_spent, 0); ?></div>
                    <div>Total Spent</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($my_points); ?></div>
                    <div>⭐ Loyalty Points</div>
                </div>
            </div>

            <h3>Order History</h3>

            <?php if ($orders_query->num_rows == 0): ?>
                <div class="empty-orders">
                    <p>☕</p>
                    <p>No orders yet — go grab a coffee!</p>
                </div>
            <?php else: ?>
                <div class="orders-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th style="text-align:right;">Total</th>
                                <th style="text-align:center;">Payment</th>
                                <th style="text-align:center;">Status</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $index = 0;
                            while ($order = $orders_query->fetch_assoc()):
                            ?>
                                <tr>
                                    <td class="order-id"><?php echo htmlspecialchars($order['order_number']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></td>
                                    <td class="order-total">₱<?php echo number_format($order['total'], 2); ?></td>
                                    <td style="text-align:center;">
                                        <span class="payment-badge"><?php echo htmlspecialchars($order['payment_method']); ?></span>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="status-badge"><?php echo htmlspecialchars($order['status']); ?></span>
                                    </td>
                                    <td style="text-align:center;">
                                        <button onclick="toggleOrderDetails(<?php echo $index; ?>)" class="btn-view-details">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                                <tr id="order-details-<?php echo $index; ?>" class="order-details-row">
                                    <td colspan="6" class="order-details-content">
                                        <div class="order-details-inner">
                                            <h4 style="margin-bottom:15px; color:#3d1c0b;">Order Details</h4>

                                            <div class="order-info">
                                                <strong>Customer:</strong>
                                                <div class="order-info-details">
                                                    Name: <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                                    Phone: <?php echo htmlspecialchars($order['customer_phone']); ?><br>
                                                    Points Earned: <?php echo $order['loyalty_points_earned']; ?> &nbsp;|&nbsp;
                                                    Points Used: <?php echo $order['loyalty_points_used']; ?>
                                                    <?php if ($order['discount_amount'] > 0): ?>
                                                        <br>Loyalty Discount: -₱<?php echo number_format($order['discount_amount'], 2); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <strong>Items Ordered:</strong>
                                            <table class="order-items-table">
                                                <thead>
                                                    <tr>
                                                        <th>Product</th>
                                                        <th style="text-align:center;">Qty</th>
                                                        <th style="text-align:right;">Unit Price</th>
                                                        <th style="text-align:right;">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $it_stmt = $mysqli->prepare("SELECT * FROM order_items WHERE order_number = ?");
                                                    $it_stmt->bind_param("s", $order['order_number']);
                                                    $it_stmt->execute();
                                                    $it_query = $it_stmt->get_result();
                                                    while ($item = $it_query->fetch_assoc()):
                                                    ?>
                                                        <tr>
                                                            <td>
                                                                <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                                                <div class="item-specs">
                                                                    <?php
                                                                    $sp = array_filter([$item['size_name'], $item['milk_name'], $item['sugar_name'], $item['addons']]);
                                                                    echo htmlspecialchars(implode(' · ', $sp));
                                                                    if ($item['notes']) echo ' | Note: ' . htmlspecialchars($item['notes']);
                                                                    ?>
                                                                </div>
                                                            </td>
                                                            <td style="text-align:center;"><?php echo $item['quantity']; ?></td>
                                                            <td style="text-align:right;">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                                            <td style="text-align:right; font-weight:bold;">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                                                        </tr>
                                                    <?php
                                                    endwhile;
                                                    $it_stmt->close();
                                                    ?>
                                                    <tr class="items-total-row">
                                                        <td colspan="2"></td>
                                                        <td class="items-total-label">VAT (12%):</td>
                                                        <td class="items-total-amount">₱<?php echo number_format($order['tax_amount'], 2); ?></td>
                                                    </tr>
                                                    <tr class="items-total-row">
                                                        <td colspan="2"></td>
                                                        <td class="items-total-label">Total:</td>
                                                        <td class="items-total-amount">₱<?php echo number_format($order['total'], 2); ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                            $index++;
                            endwhile;
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function toggleOrderDetails(index) {
        var row = document.getElementById('order-details-' + index);
        row.style.display = (row.style.display === '' || row.style.display === 'none') ? 'table-row' : 'none';
    }
    </script>
</body>
</html>
<?php $orders_stmt->close(); ?>
