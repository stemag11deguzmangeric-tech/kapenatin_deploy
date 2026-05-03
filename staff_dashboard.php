<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require("config.php");

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php'); exit;
}
$role = $_SESSION['role'] ?? 'customer';
if (!in_array($role, ['admin','cashier','barista'])) {
    header('Location: menu.php'); exit;
}
$user_id = $_SESSION['user_id'];

// ── Place counter order (Cashier / Admin POS) ──────────────────────────────
$pos_success = '';
$pos_error   = '';
if (isset($_POST['place_counter_order'])) {
    $customer_name  = trim($_POST['customer_name']);
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $payment_method = $_POST['payment_method'];
    $items          = json_decode($_POST['items_json'] ?? '[]', true);

    if (!empty($items) && !empty($customer_name)) {
        $subtotal = 0;
        foreach ($items as $it) { $subtotal += $it['unit_price'] * $it['qty']; }
        $tax   = round($subtotal * 0.12, 2);
        $total = round($subtotal + $tax, 2);
        $pts   = (int)floor($total); // 1 pt per ₱1

        $order_number = 'KN-' . date('Ymd') . '-' . str_pad(rand(1,9999),4,'0',STR_PAD_LEFT);

        $ord = $mysqli->prepare("INSERT INTO orders (order_number, customer_name, customer_phone, subtotal, tax_amount, discount_amount, total, payment_method, loyalty_points_earned) VALUES (?,?,?,?,?,0,?,?,?)");
        $ord->bind_param("sssdddsi", $order_number, $customer_name, $customer_phone, $subtotal, $tax, $total, $payment_method, $pts);

        if ($ord->execute()) {
            foreach ($items as $it) {
                $sub   = $it['unit_price'] * $it['qty'];
                $size  = $it['size']   ?? '';
                $milk  = $it['milk']   ?? '';
                $sugar = $it['sugar']  ?? '';
                $addons= $it['addons'] ?? '';
                $notes = $it['notes']  ?? '';
                $iStmt = $mysqli->prepare("INSERT INTO order_items (order_number, product_code, product_name, product_price, size_name, milk_name, sugar_name, addons, quantity, unit_price, subtotal, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                $iStmt->bind_param("sssdssssidds", $order_number, $it['code'], $it['name'], $it['price'], $size, $milk, $sugar, $addons, $it['qty'], $it['unit_price'], $sub, $notes);
                $iStmt->execute();
                $iStmt->close();
            }
            $pos_success = "Order <strong>$order_number</strong> placed! Total: ₱" . number_format($total,2);
        } else {
            $pos_error = "DB Error: " . $ord->error;
        }
        $ord->close();
    } else {
        $pos_error = !empty($customer_name) ? "No items in order." : "Customer name is required.";
    }
}

// ── Update order status ────────────────────────────────────────────────────
if (isset($_POST['update_status'])) {
    $oid    = $mysqli->real_escape_string($_POST['order_id']);
    $status = $mysqli->real_escape_string($_POST['new_status']);
    $mysqli->query("UPDATE orders SET status='$status' WHERE order_number='$oid'");
    header('Location: staff_dashboard.php?tab=' . ($_POST['tab'] ?? 'orders')); exit;
}

// ── Toggle product availability ───────────────────────────────────────────
if (isset($_POST['toggle_product'])) {
    $pid = intval($_POST['product_id']);
    $mysqli->query("UPDATE products SET is_available = 1 - is_available WHERE id=$pid");
    header('Location: staff_dashboard.php?tab=menu'); exit;
}

// ── Load data ─────────────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? ($role === 'barista' ? 'queue' : 'orders');

$all_orders  = $mysqli->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 100");
$queue       = $mysqli->query("SELECT * FROM orders WHERE status IN ('Confirmed','Preparing') ORDER BY created_at ASC");
$today_rev   = $mysqli->query("SELECT COALESCE(SUM(total),0) as r FROM orders WHERE DATE(created_at)=CURDATE() AND status!='Cancelled'")->fetch_assoc()['r'];
$today_ord   = $mysqli->query("SELECT COUNT(*) as c FROM orders WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];
$pending_cnt = $mysqli->query("SELECT COUNT(*) as c FROM orders WHERE status IN ('Confirmed','Preparing')")->fetch_assoc()['c'];
$total_cust  = $mysqli->query("SELECT COUNT(*) as c FROM users WHERE role='customer'")->fetch_assoc()['c'];

// Products for POS (with image)
$pq = $mysqli->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_available=1 ORDER BY c.display_order, p.name");
$pos_products = [];
while ($p = $pq->fetch_assoc()) { $pos_products[$p['cat_name']][] = $p; }

// All products for menu management
$all_products = $mysqli->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id ORDER BY c.display_order, p.name");
?>
<!DOCTYPE html>
<html>
<head>
<title>Staff Dashboard — Kape Natin</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background-image: url(bg.png); min-height:100vh}
  header{background:linear-gradient(135deg,#3d1c0b 0%,#8b5e3c 100%);box-shadow:0 4px 12px rgba(61,28,11,.3);width:100%}
  nav{display:flex;justify-content:space-between;align-items:center;padding:14px 28px;flex-wrap:wrap;gap:10px}
  .logo{font-family: 'Quiapo Free';font-size:32px;font-weight:bold;color:white;letter-spacing: 6px;}
  .logo a{text-decoration: none; color:white;}
  .nav-right{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .nav-right a{text-decoration:none;color:white;font-weight:500;padding:6px 14px;border-radius:5px;transition:all .3s;font-size:14px}
  .nav-right a:hover{background:rgba(255,255,255,.2)}
  .role-badge{background:rgba(212,160,84,.25);border:1px solid rgba(212,160,84,.5);color:#d4a054;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:5px 12px;border-radius:50px}

  /* TAB NAV */
  .tab-nav{background:white;border-bottom:1px solid #e8d5c0;display:flex;overflow-x:auto;padding:0 28px}
  .tab-nav a{text-decoration:none;color:#888;font-weight:500;padding:13px 18px;border-bottom:3px solid transparent;white-space:nowrap;font-size:14px;transition:all .2s}
  .tab-nav a:hover{color:#8b5e3c}
  .tab-nav a.active{color:#3d1c0b;border-bottom-color:#c8864b;font-weight:600}

  /* CONTAINER */
  .container{max-width:95%;margin:28px auto;padding:0 16px}

  /* STATS */
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:24px}
  .stat-card{background:white;padding:20px;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,.08);border-left:4px solid #c8864b}
  .stat-card.green{border-left-color:#27ae60}
  .stat-card.blue{border-left-color:#3498db}
  .stat-card.red{border-left-color:#e74c3c}
  .stat-number{font-size:28px;font-weight:bold;color:#3d1c0b;margin-bottom:4px}
  .stat-label{font-size:13px;color:#888}

  /* SECTION CARD */
  .section-card{background:white;padding:22px;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,.08);margin-bottom:22px}
  .section-card h3{color:#3d1c0b;font-size:17px;margin-bottom:18px;padding-bottom:10px;border-bottom:2px solid #f5ede3}

  /* TABLE */
  .table-wrap{overflow-x:auto}
  table{width:100%;border-collapse:collapse;font-size:13px}
  thead tr{background:linear-gradient(135deg,#3d1c0b,#8b5e3c);color:white}
  th{padding:11px 13px;text-align:left}
  td{padding:11px 13px;border-bottom:1px solid #f0e4d4;vertical-align:top}
  tr:hover td{background:#fdf8f3}
  .order-num{font-weight:700;color:#8b5e3c}

  /* BADGES */
  .badge{padding:3px 9px;border-radius:50px;font-size:11px;font-weight:700;text-transform:uppercase;display:inline-block}
  .badge-confirmed{background:#dbeafe;color:#1d4ed8}
  .badge-preparing{background:#ffedd5;color:#c2410c}
  .badge-ready{background:#dcfce7;color:#15803d}
  .badge-completed{background:#f3e8d4;color:#8b5e3c}
  .badge-cancelled{background:#fee2e2;color:#b91c1c}
  .badge-admin{background:#fee2e2;color:#b91c1c}
  .badge-cashier{background:#dbeafe;color:#1d4ed8}
  .badge-barista{background:#dcfce7;color:#15803d}
  .badge-customer{background:#f3e8d4;color:#8b5e3c}

  /* BUTTONS */
  .btn-sm{padding:5px 11px;border:none;border-radius:5px;cursor:pointer;font-size:12px;font-weight:600;transition:all .2s}
  .btn-brown{background:#8b5e3c;color:white}.btn-brown:hover{background:#3d1c0b}
  .btn-green{background:#27ae60;color:white}.btn-green:hover{background:#219150}
  .btn-red{background:#e74c3c;color:white}.btn-red:hover{background:#c0392b}
  .btn-orange{background:#f39c12;color:white}.btn-orange:hover{background:#d68910}

  /* ALERTS */
  .alert-success{background:#27ae60;color:white;padding:12px 16px;border-radius:8px;margin-bottom:18px}
  .alert-error{background:#e74c3c;color:white;padding:12px 16px;border-radius:8px;margin-bottom:18px}

  /* ORDER DETAIL TOGGLE */
  .order-details-row{display:none}
  .order-details-content{padding:14px 18px;background:#fdf8f3}
  .detail-items-table{width:100%;border-collapse:collapse;margin-top:8px;font-size:12px}
  .detail-items-table th{background:#8b5e3c;color:white;padding:7px 10px;text-align:left}
  .detail-items-table td{padding:7px 10px;border-bottom:1px solid #f0e4d4}

  /* QUEUE */
  .queue-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
  .queue-card{background:white;border-radius:10px;overflow:hidden;box-shadow:0 3px 10px rgba(0,0,0,.08)}
  .queue-card-header{padding:13px 17px;display:flex;justify-content:space-between;align-items:center}
  .queue-card-header.confirmed{background:linear-gradient(135deg,#3d1c0b,#5c2d0e);color:white}
  .queue-card-header.preparing{background:linear-gradient(135deg,#f39c12,#e67e22);color:white}
  .queue-order-num{font-weight:700;font-size:15px}
  .queue-time{font-size:12px;opacity:.75}
  .queue-card-body{padding:13px 17px}
  .queue-item-row{padding:7px 0;border-bottom:1px dashed #e8d5c0;font-size:13px}
  .queue-item-row:last-child{border-bottom:none}
  .queue-item-name{font-weight:600;color:#333;margin-bottom:3px}
  .queue-item-specs{font-size:12px;color:#888}
  .queue-card-footer{padding:11px 17px;background:#faf6f0;display:flex;gap:8px;border-top:1px solid #f0e4d4}
  .queue-card-footer button{flex:1}

  /* ── POS Layout ──────────────────────────── */
  .pos-layout{display:grid;grid-template-columns:1fr 370px;gap:18px;min-height:70vh}
  .pos-products{background:white;border-radius:10px;padding:18px;box-shadow:0 3px 10px rgba(0,0,0,.08);overflow-y:auto}
  .pos-products h3{color:#3d1c0b;margin-bottom:12px;font-size:16px}
  .pos-cat-title{font-size:13px;font-weight:700;color:#8b5e3c;margin:14px 0 7px;padding-bottom:5px;border-bottom:1px solid #f0e4d4}
  .pos-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;margin-bottom:6px}
  .pos-item{background:#faf6f0;border:2px solid transparent;border-radius:8px;overflow:hidden;cursor:pointer;transition:all .2s;text-align:center}
  .pos-item:hover{border-color:#c8864b;background:#f5ede3}
  /* ADD IMAGE SLOT HERE — place product images in imgs/products/<code>.jpg */
  .pos-item-img{width:100%;height:80px;object-fit:cover;display:block}
  .pos-item-placeholder{width:100%;height:80px;background:linear-gradient(135deg,#5c2d0e,#8b5e3c);display:flex;align-items:center;justify-content:center;font-size:26px}
  .pos-item-body{padding:7px 8px}
  .pos-item-name{font-size:11px;font-weight:600;color:#333;margin-bottom:3px;line-height:1.3}
  .pos-item-price{font-size:12px;font-weight:bold;color:#c8864b}

  /* POS Cart */
  .pos-cart{background:white;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,.08);display:flex;flex-direction:column}
  .pos-cart-header{background:linear-gradient(135deg,#3d1c0b,#8b5e3c);color:white;padding:14px 18px;border-radius:10px 10px 0 0;display:flex;justify-content:space-between;align-items:center}
  .pos-cart-header h3{font-size:15px}
  .pos-cart-items{flex:1;overflow-y:auto;padding:12px;max-height:320px}
  .pos-cart-empty{text-align:center;color:#aaa;padding:28px;font-size:13px}
  .pos-cart-row{display:flex;align-items:flex-start;padding:7px 0;border-bottom:1px dashed #f0e4d4;font-size:12px;gap:6px}
  .pos-cart-row:last-child{border-bottom:none}
  .pos-cart-row-info{flex:1}
  .pos-cart-row-name{font-weight:600;color:#333}
  .pos-cart-row-spec{color:#999;font-size:11px;margin-top:2px}
  .pos-cart-row-price{color:#8b5e3c;font-weight:700;white-space:nowrap;padding-top:2px}
  .pos-cart-row-remove{background:none;border:none;color:#e74c3c;cursor:pointer;font-size:15px;padding:0 3px;flex-shrink:0}
  .pos-cart-footer{padding:14px 18px;border-top:1px solid #f0e4d4}
  .pos-cart-footer label{font-size:11px;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px;margin-top:10px}
  .pos-cart-footer label:first-child{margin-top:0}
  .pos-cart-footer input,.pos-cart-footer select{width:100%;padding:8px 10px;border:1.5px solid #ddd;border-radius:5px;font-size:13px;font-family:inherit}
  .pos-cart-footer input:focus,.pos-cart-footer select:focus{outline:none;border-color:#8b5e3c}
  .pos-total{display:flex;justify-content:space-between;font-size:17px;font-weight:bold;color:#3d1c0b;margin:10px 0}
  .pos-subtotals{font-size:12px;color:#888;margin-bottom:4px;line-height:1.8}
  .btn-pos-place{width:100%;padding:11px;background:#8b5e3c;color:white;border:none;border-radius:5px;font-size:14px;font-weight:600;cursor:pointer;margin-top:12px}
  .btn-pos-place:hover{background:#3d1c0b}
  .btn-clear{background:none;border:1px solid rgba(255,255,255,.4);color:white;padding:4px 10px;border-radius:5px;cursor:pointer;font-size:12px}
  .btn-clear:hover{background:rgba(255,255,255,.15)}

  /* POS Customize Modal */
  .modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1000;align-items:center;justify-content:center;padding:16px}
  .modal-bg.open{display:flex}
  .modal-box{background:white;border-radius:12px;max-width:440px;width:100%;max-height:88vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25)}
  .modal-header{background:linear-gradient(135deg,#3d1c0b,#8b5e3c);padding:16px 20px;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center}
  .modal-header h3{color:white;font-size:16px}
  .modal-close{background:none;border:none;color:white;font-size:20px;cursor:pointer;padding:0 4px;line-height:1}
  .modal-body{padding:18px}
  .modal-body label{font-size:12px;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px;margin-top:14px}
  .modal-body label:first-child{margin-top:0}
  .modal-body select,.modal-body input[type=text]{width:100%;padding:9px 11px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;font-family:inherit}
  .modal-body select:focus,.modal-body input:focus{outline:none;border-color:#8b5e3c}
  .addons-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-top:4px}
  .addon-label{font-size:12px;display:flex;align-items:center;gap:5px;font-weight:normal!important;margin-top:0!important;text-transform:none!important;letter-spacing:0!important;padding:4px 2px}
  .qty-row{display:flex;align-items:center;gap:10px;margin-top:14px}
  .qty-row label{margin:0!important;white-space:nowrap}
  .qty-row input[type=number]{width:65px;padding:8px;border:1.5px solid #ddd;border-radius:6px;font-size:14px}
  .modal-footer{padding:14px 18px 18px;border-top:1px solid #f0e4d4;display:flex;gap:10px;align-items:center}
  .modal-price{flex:1;font-size:17px;font-weight:bold;color:#c8864b}
  .modal-price small{font-size:12px;color:#aaa;font-weight:400;display:block}
  .btn-modal-add{padding:10px 22px;background:#8b5e3c;color:white;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer}
  .btn-modal-add:hover{background:#3d1c0b}
</style>
</head>
<body>

<header>
  <nav>
    <div class="logo"> <a href = "index.php">KAPENATIN - STAFF</a></div>
    <div class="nav-right">
      <span class="role-badge"><?php echo strtoupper($role); ?></span>
      <span style="color:rgba(255,255,255,.8);font-size:13px;">👤 <?php echo htmlspecialchars($_SESSION['name']); ?></span>
      <?php if ($role === 'admin'): ?>
      <a href="menu.php">🍽️ Menu Editor</a>
      <?php endif; ?>
      <a href="index.php">🏠 Home</a>
      <a href="logout.php">Logout</a>
    </div>
  </nav>
</header>

<!-- Tab nav -->
<div class="tab-nav">
  <?php if (in_array($role,['admin','cashier'])): ?>
    <a href="?tab=orders"  class="<?php echo $tab=='orders' ?'active':''; ?>">📋 Orders</a>
    <a href="?tab=pos"     class="<?php echo $tab=='pos'    ?'active':''; ?>">🖥️ POS Counter</a>
  <?php endif; ?>
  <?php if (in_array($role,['admin','barista'])): ?>
    <a href="?tab=queue"   class="<?php echo $tab=='queue'  ?'active':''; ?>">⏳ Barista Queue<?php echo $pending_cnt>0?" ($pending_cnt)":''; ?></a>
  <?php endif; ?>
  <?php if ($role==='admin'): ?>
    <a href="?tab=menu"      class="<?php echo $tab=='menu'      ?'active':''; ?>">🍽️ Menu</a>
    <a href="?tab=customers" class="<?php echo $tab=='customers' ?'active':''; ?>">👥 Customers</a>
    <a href="?tab=reports"   class="<?php echo $tab=='reports'   ?'active':''; ?>">📊 Reports</a>
  <?php endif; ?>
</div>

<div class="container">

<?php /* ═══════════════════ ORDERS ═══════════════════ */ ?>
<?php if ($tab==='orders' && in_array($role,['admin','cashier'])): ?>

  <div class="stats">
    <div class="stat-card"><div class="stat-number">₱<?php echo number_format($today_rev,0); ?></div><div class="stat-label">Today's Revenue</div></div>
    <div class="stat-card green"><div class="stat-number"><?php echo $today_ord; ?></div><div class="stat-label">Today's Orders</div></div>
    <div class="stat-card red"><div class="stat-number"><?php echo $pending_cnt; ?></div><div class="stat-label">Active Orders</div></div>
  </div>

  <div class="section-card">
    <h3>All Orders</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Order #</th><th>Customer</th><th style="text-align:right">Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php $idx=0; while ($order=$all_orders->fetch_assoc()): $bc='badge-'.strtolower($order['status']); ?>
          <tr>
            <td class="order-num"><?php echo htmlspecialchars($order['order_number']); ?></td>
            <td><?php echo htmlspecialchars($order['customer_name']); ?><?php if($order['customer_phone']): ?><br><small style="color:#aaa"><?php echo htmlspecialchars($order['customer_phone']); ?></small><?php endif; ?></td>
            <td style="text-align:right;font-weight:bold;color:#27ae60">₱<?php echo number_format($order['total'],2); ?></td>
            <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
            <td><span class="badge <?php echo $bc; ?>"><?php echo htmlspecialchars($order['status']); ?></span></td>
            <td><?php echo date('M d, Y H:i',strtotime($order['created_at'])); ?></td>
            <td>
              <div style="display:flex;gap:4px;flex-wrap:wrap">
                <button onclick="toggleDetail(<?php echo $idx; ?>)" class="btn-sm btn-brown">Details</button>
                <?php if($order['status']==='Confirmed'): ?>
                  <form method="POST" style="display:inline"><input type="hidden" name="order_id" value="<?php echo $order['order_number']; ?>"><input type="hidden" name="new_status" value="Preparing"><input type="hidden" name="tab" value="orders"><button type="submit" name="update_status" class="btn-sm btn-orange">▶ Prep</button></form>
                <?php elseif($order['status']==='Preparing'): ?>
                  <form method="POST" style="display:inline"><input type="hidden" name="order_id" value="<?php echo $order['order_number']; ?>"><input type="hidden" name="new_status" value="Ready"><input type="hidden" name="tab" value="orders"><button type="submit" name="update_status" class="btn-sm btn-green">✓ Ready</button></form>
                <?php elseif($order['status']==='Ready'): ?>
                  <form method="POST" style="display:inline"><input type="hidden" name="order_id" value="<?php echo $order['order_number']; ?>"><input type="hidden" name="new_status" value="Completed"><input type="hidden" name="tab" value="orders"><button type="submit" name="update_status" class="btn-sm btn-green">✓ Done</button></form>
                <?php endif; ?>
                <?php if(in_array($order['status'],['Confirmed','Preparing'])): ?>
                  <form method="POST" style="display:inline"><input type="hidden" name="order_id" value="<?php echo $order['order_number']; ?>"><input type="hidden" name="new_status" value="Cancelled"><input type="hidden" name="tab" value="orders"><button type="submit" name="update_status" class="btn-sm btn-red" onclick="return confirm('Cancel this order?')">✕</button></form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <tr id="detail-<?php echo $idx; ?>" class="order-details-row">
            <td colspan="7" class="order-details-content">
              <?php
                $itq=$mysqli->prepare("SELECT * FROM order_items WHERE order_number=?");
                $itq->bind_param("s",$order['order_number']); $itq->execute();
                $itr=$itq->get_result(); $itq->close();
              ?>
              <table class="detail-items-table">
                <thead><tr><th>Product</th><th>Customization</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Subtotal</th></tr></thead>
                <tbody>
                <?php while($it=$itr->fetch_assoc()): ?>
                  <tr>
                    <td style="font-weight:600"><?php echo htmlspecialchars($it['product_name']); ?></td>
                    <td style="color:#888"><?php $sp=array_filter([$it['size_name'],$it['milk_name'],$it['sugar_name'],$it['addons']]); echo htmlspecialchars(implode(' · ',$sp)); if($it['notes']) echo '<br><em>'.htmlspecialchars($it['notes']).'</em>'; ?></td>
                    <td style="text-align:center"><?php echo $it['quantity']; ?></td>
                    <td style="text-align:right">₱<?php echo number_format($it['unit_price'],2); ?></td>
                    <td style="text-align:right;font-weight:bold">₱<?php echo number_format($it['subtotal'],2); ?></td>
                  </tr>
                <?php endwhile; ?>
                  <tr style="background:#f5ede3"><td colspan="3"></td><td style="text-align:right;font-weight:bold">Total:</td><td style="text-align:right;font-weight:bold;color:#8b5e3c">₱<?php echo number_format($order['total'],2); ?></td></tr>
                </tbody>
              </table>
            </td>
          </tr>
        <?php $idx++; endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php /* ═══════════════════ POS ═══════════════════ */ ?>
<?php elseif ($tab==='pos' && in_array($role,['admin','cashier'])): ?>

  <?php if($pos_success): ?><div class="alert-success">✓ <?php echo $pos_success; ?></div><?php endif; ?>
  <?php if($pos_error):   ?><div class="alert-error">✕ <?php echo htmlspecialchars($pos_error); ?></div><?php endif; ?>

  <div class="pos-layout">
    <!-- Product grid -->
    <div class="pos-products">
      <h3>🖥️ Select Items — click to customize &amp; add</h3>
      <?php foreach ($pos_products as $cat_name => $prods):
        $cat_emoji = ['Coffee'=>'☕','Non-Coffee'=>'🍵','Frappe'=>'🥤','Pastries'=>'🥐','Sandwiches'=>'🥪'];
        $emoji = $cat_emoji[$cat_name] ?? '☕';
        $is_food = in_array($cat_name,['Pastries','Sandwiches']);
      ?>
        <div class="pos-cat-title"><?php echo htmlspecialchars($cat_name); ?></div>
        <div class="pos-grid">
          <?php foreach ($prods as $p): ?>
            <div class="pos-item" onclick="openPOSModal(
              '<?php echo addslashes($p['code']); ?>',
              '<?php echo addslashes($p['name']); ?>',
              <?php echo $p['price']; ?>,
              '<?php echo addslashes($p['cat_name']); ?>',
              <?php echo $is_food ? 'true' : 'false'; ?>
            )">
              <?php if (!empty($p['image']) && file_exists($p['image'])): ?>
                <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="pos-item-img">
              <?php else: ?>
                <div class="pos-item-placeholder"><?php echo $emoji; ?></div>
              <?php endif; ?>
              <div class="pos-item-body">
                <div class="pos-item-name"><?php echo htmlspecialchars($p['name']); ?></div>
                <div class="pos-item-price">₱<?php echo number_format($p['price'],2); ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Cart -->
    <div class="pos-cart">
      <div class="pos-cart-header">
        <h3>🧾 Order</h3>
        <button class="btn-clear" onclick="clearCart()">Clear All</button>
      </div>
      <div class="pos-cart-items" id="posCartItems">
        <div class="pos-cart-empty">Select items from the menu</div>
      </div>
      <div class="pos-cart-footer">
        <div class="pos-subtotals" id="posSubtotals">Subtotal: ₱0.00 &nbsp;|&nbsp; VAT: ₱0.00</div>
        <div class="pos-total"><span>Total:</span><span id="posTotal">₱0.00</span></div>
        <form method="POST" id="posForm">
          <input type="hidden" name="items_json" id="itemsJson">
          <label>Customer Name *</label>
          <input type="text" name="customer_name" required placeholder="Walk-in customer">
          <label>Phone (optional)</label>
          <input type="tel" name="customer_phone" placeholder="09XX-XXX-XXXX">
          <label>Payment Method</label>
          <select name="payment_method">
            <option value="Cash">💵 Cash</option>
            <option value="GCash">📱 GCash</option>
            <option value="Maya">💜 Maya</option>
            <option value="Card">💳 Card</option>
          </select>
          <button type="submit" name="place_counter_order" class="btn-pos-place" onclick="prepareOrder(event)">✔ Place Order</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Customize Modal -->
  <div class="modal-bg" id="posModal">
    <div class="modal-box">
      <div class="modal-header">
        <h3 id="modalTitle">Customize Order</h3>
        <button class="modal-close" onclick="closePOSModal()">✕</button>
      </div>
      <div class="modal-body">
        <div id="drinkOptions">
          <label>Size</label>
          <select id="mSize">
            <option value="Small"  data-mod="0">Small (+₱0)</option>
            <option value="Medium" data-mod="20" selected>Medium (+₱20)</option>
            <option value="Large"  data-mod="40">Large (+₱40)</option>
            <option value="XL"     data-mod="60">XL (+₱60)</option>
          </select>
          <label>Milk Type</label>
          <select id="mMilk">
            <option value="Whole Milk"   data-mod="0">Whole Milk</option>
            <option value="Skim Milk"    data-mod="0">Skim Milk</option>
            <option value="Oat Milk"     data-mod="25">Oat Milk (+₱25)</option>
            <option value="Almond Milk"  data-mod="25">Almond Milk (+₱25)</option>
            <option value="Soy Milk"     data-mod="20">Soy Milk (+₱20)</option>
            <option value="Coconut Milk" data-mod="25">Coconut Milk (+₱25)</option>
            <option value="No Milk"      data-mod="0">No Milk</option>
          </select>
          <label>Sugar Level</label>
          <select id="mSugar">
            <option value="No Sugar">No Sugar</option>
            <option value="Less Sweet">Less Sweet (30%)</option>
            <option value="Half Sweet">Half Sweet (50%)</option>
            <option value="Regular" selected>Regular (100%)</option>
            <option value="Extra Sweet">Extra Sweet (130%)</option>
          </select>
          <label>Add-ons</label>
          <div class="addons-grid" id="mAddons">
            <label class="addon-label"><input type="checkbox" value="Extra Shot"        data-price="20"> Extra Shot +₱20</label>
            <label class="addon-label"><input type="checkbox" value="Vanilla Syrup"     data-price="20"> Vanilla Syrup +₱20</label>
            <label class="addon-label"><input type="checkbox" value="Caramel Syrup"     data-price="20"> Caramel Syrup +₱20</label>
            <label class="addon-label"><input type="checkbox" value="Hazelnut Syrup"    data-price="20"> Hazelnut Syrup +₱20</label>
            <label class="addon-label"><input type="checkbox" value="Whipped Cream"     data-price="15"> Whipped Cream +₱15</label>
            <label class="addon-label"><input type="checkbox" value="Caramel Drizzle"   data-price="15"> Caramel Drizzle +₱15</label>
            <label class="addon-label"><input type="checkbox" value="Boba Pearls"       data-price="30"> Boba Pearls +₱30</label>
            <label class="addon-label"><input type="checkbox" value="Cheese Foam"       data-price="35"> Cheese Foam +₱35</label>
          </div>
        </div>
        <label>Special Notes</label>
        <input type="text" id="mNotes" placeholder="e.g. less ice, extra hot...">
        <div class="qty-row">
          <label>Qty:</label>
          <input type="number" id="mQty" value="1" min="1" max="20" oninput="updateModalPrice()">
        </div>
      </div>
      <div class="modal-footer">
        <div class="modal-price"><small>Unit price</small><span id="modalUnitPrice">₱0.00</span></div>
        <button class="btn-modal-add" onclick="addModalToCart()">Add to Cart</button>
      </div>
    </div>
  </div>

<?php /* ═══════════════════ QUEUE ═══════════════════ */ ?>
<?php elseif ($tab==='queue'): ?>

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
    <h3 style="color:#3d1c0b;font-size:19px">⏳ Barista Queue — <?php echo $pending_cnt; ?> active order(s)</h3>
    <a href="?tab=queue" class="btn-sm btn-brown" style="text-decoration:none;padding:8px 16px">🔄 Refresh</a>
  </div>

  <?php if ($queue->num_rows===0): ?>
    <div class="section-card" style="text-align:center;padding:60px;color:#aaa">
      <div style="font-size:48px;margin-bottom:12px">✅</div>
      <p>All caught up! No orders in queue.</p>
    </div>
  <?php else: ?>
    <div class="queue-grid">
    <?php while ($order=$queue->fetch_assoc()):
      $hc = strtolower($order['status']);
      $itq=$mysqli->prepare("SELECT * FROM order_items WHERE order_number=?");
      $itq->bind_param("s",$order['order_number']); $itq->execute();
      $itr=$itq->get_result(); $itq->close();
    ?>
      <div class="queue-card">
        <div class="queue-card-header <?php echo $hc; ?>">
          <div>
            <div class="queue-order-num"><?php echo htmlspecialchars($order['order_number']); ?></div>
            <div style="font-size:12px;opacity:.75">👤 <?php echo htmlspecialchars($order['customer_name']); ?></div>
          </div>
          <div class="queue-time"><?php echo date('H:i',strtotime($order['created_at'])); ?></div>
        </div>
        <div class="queue-card-body">
          <?php while ($it=$itr->fetch_assoc()): ?>
            <div class="queue-item-row">
              <div class="queue-item-name"><?php echo htmlspecialchars($it['product_name']); ?> <strong>x<?php echo $it['quantity']; ?></strong></div>
              <div class="queue-item-specs"><?php $sp=array_filter([$it['size_name'],$it['milk_name'],$it['sugar_name'],$it['addons']]); echo htmlspecialchars(implode(' · ',$sp)); if($it['notes']) echo ' — <em>'.htmlspecialchars($it['notes']).'</em>'; ?></div>
            </div>
          <?php endwhile; ?>
        </div>
        <div class="queue-card-footer">
          <?php if($order['status']==='Confirmed'): ?>
            <form method="POST"><input type="hidden" name="order_id" value="<?php echo $order['order_number']; ?>"><input type="hidden" name="new_status" value="Preparing"><input type="hidden" name="tab" value="queue"><button type="submit" name="update_status" class="btn-sm btn-orange" style="width:100%">▶ Start Preparing</button></form>
          <?php endif; ?>
          <?php if($order['status']==='Preparing'): ?>
            <form method="POST"><input type="hidden" name="order_id" value="<?php echo $order['order_number']; ?>"><input type="hidden" name="new_status" value="Ready"><input type="hidden" name="tab" value="queue"><button type="submit" name="update_status" class="btn-sm btn-green" style="width:100%">✓ Order Ready</button></form>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
    </div>
  <?php endif; ?>

<?php /* ═══════════════════ MENU ═══════════════════ */ ?>
<?php elseif ($tab==='menu' && $role==='admin'): ?>

  <div class="section-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:18px">
      <div>
        <h3 style="margin-bottom:6px;border:none;padding:0">🍽️ Menu Management</h3>
        <p style="font-size:13px;color:#888;margin:0">Toggle visibility of existing products below, or go to the full menu editor to add / delete products.</p>
      </div>
      <a href="menu.php"
         style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#3d1c0b,#8b5e3c);color:white;text-decoration:none;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;box-shadow:0 3px 10px rgba(61,28,11,0.3);white-space:nowrap"
         onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        ＋ Add / Delete Products
      </a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Code</th><th>Image</th><th>Name</th><th>Category</th><th style="text-align:right">Price</th><th>Description</th><th style="text-align:center">Available</th><th style="text-align:center">Action</th></tr></thead>
        <tbody>
        <?php while ($p=$all_products->fetch_assoc()): ?>
          <tr>
            <td style="color:#aaa;font-size:12px"><?php echo htmlspecialchars($p['code']); ?></td>
            <td>
              <?php if (!empty($p['image']) && file_exists($p['image'])): ?>
                <img src="<?php echo htmlspecialchars($p['image']); ?>" style="width:50px;height:40px;object-fit:cover;border-radius:5px">
              <?php else: ?>
                <div style="width:50px;height:40px;background:linear-gradient(135deg,#5c2d0e,#8b5e3c);border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:18px">☕</div>
              <?php endif; ?>
            </td>
            <td style="font-weight:600"><?php echo htmlspecialchars($p['name']); ?></td>
            <td><?php echo htmlspecialchars($p['cat_name']); ?></td>
            <td style="text-align:right;font-weight:bold;color:#c8864b">₱<?php echo number_format($p['price'],2); ?></td>
            <td style="color:#888;font-size:13px"><?php echo htmlspecialchars($p['description']); ?></td>
            <td style="text-align:center"><?php echo $p['is_available'] ? '<span class="badge badge-confirmed">Yes</span>' : '<span class="badge badge-cancelled">No</span>'; ?></td>
            <td style="text-align:center">
              <form method="POST">
                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                <button type="submit" name="toggle_product" class="btn-sm <?php echo $p['is_available']?'btn-red':'btn-green'; ?>"><?php echo $p['is_available']?'Hide':'Show'; ?></button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php /* ═══════════════════ CUSTOMERS ═══════════════════ */ ?>
<?php elseif ($tab==='customers' && $role==='admin'): ?>

  <div class="section-card">
    <h3>👥 All Accounts</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Phone</th><th>Role</th><th style="text-align:right">Points</th><th style="text-align:right">Total Spent</th><th>Joined</th></tr></thead>
        <tbody>
        <?php $uq=$mysqli->query("SELECT * FROM users ORDER BY role,name"); while($u=$uq->fetch_assoc()): ?>
          <tr>
            <td style="font-weight:600"><?php echo htmlspecialchars($u['name']); ?></td>
            <td style="color:#888"><?php echo htmlspecialchars($u['username']); ?></td>
            <td><?php echo htmlspecialchars($u['email']); ?></td>
            <td><?php echo htmlspecialchars($u['phone']??'—'); ?></td>
            <td><span class="badge badge-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span></td>
            <td style="text-align:right;font-weight:bold;color:#c8864b">⭐ <?php echo number_format($u['loyalty_points']); ?></td>
            <td style="text-align:right;color:#27ae60;font-weight:bold">₱<?php echo number_format($u['total_spent'],2); ?></td>
            <td style="color:#aaa;font-size:12px"><?php echo date('M d, Y',strtotime($u['created_at'])); ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php /* ═══════════════════ REPORTS ═══════════════════ */ ?>
<?php elseif ($tab==='reports' && $role==='admin'): ?>

  <?php
    $rev_week  = $mysqli->query("SELECT COALESCE(SUM(total),0) as r FROM orders WHERE created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) AND status!='Cancelled'")->fetch_assoc()['r'];
    $rev_month = $mysqli->query("SELECT COALESCE(SUM(total),0) as r FROM orders WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND status!='Cancelled'")->fetch_assoc()['r'];
    $rev_total = $mysqli->query("SELECT COALESCE(SUM(total),0) as r FROM orders WHERE status!='Cancelled'")->fetch_assoc()['r'];
    $ord_total = $mysqli->query("SELECT COUNT(*) as c FROM orders WHERE status!='Cancelled'")->fetch_assoc()['c'];
    $pts_total = $mysqli->query("SELECT COALESCE(SUM(loyalty_points),0) as p FROM users WHERE role='customer'")->fetch_assoc()['p'];
    // FIXED: use oi.subtotal to avoid ambiguous column
    $top_prods = $mysqli->query("SELECT oi.product_name, SUM(oi.quantity) as sold, SUM(oi.subtotal) as revenue FROM order_items oi JOIN orders o ON oi.order_number=o.order_number WHERE o.status!='Cancelled' GROUP BY oi.product_name ORDER BY sold DESC LIMIT 10");
  ?>

  <div class="stats">
    <div class="stat-card"><div class="stat-number">₱<?php echo number_format($today_rev,0); ?></div><div class="stat-label">Today</div></div>
    <div class="stat-card green"><div class="stat-number">₱<?php echo number_format($rev_week,0); ?></div><div class="stat-label">Last 7 Days</div></div>
    <div class="stat-card blue"><div class="stat-number">₱<?php echo number_format($rev_month,0); ?></div><div class="stat-label">Last 30 Days</div></div>
    <div class="stat-card"><div class="stat-number"><?php echo number_format($ord_total); ?></div><div class="stat-label">Total Orders</div></div>
    <div class="stat-card green"><div class="stat-number"><?php echo number_format($total_cust); ?></div><div class="stat-label">Customers</div></div>
    <div class="stat-card blue"><div class="stat-number"><?php echo number_format($pts_total); ?></div><div class="stat-label">Points Outstanding</div></div>
  </div>

  <div class="section-card">
    <h3>🏆 Top 10 Products</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Product</th><th style="text-align:center">Units Sold</th><th style="text-align:right">Revenue</th></tr></thead>
        <tbody>
        <?php $rank=1; while($tp=$top_prods->fetch_assoc()): ?>
          <tr>
            <td style="font-weight:bold;color:#c8864b"><?php echo $rank++; ?></td>
            <td style="font-weight:600"><?php echo htmlspecialchars($tp['product_name']); ?></td>
            <td style="text-align:center"><?php echo number_format($tp['sold']); ?></td>
            <td style="text-align:right;color:#27ae60;font-weight:bold">₱<?php echo number_format($tp['revenue'],2); ?></td>
          </tr>
        <?php endwhile; ?>
        <?php if($rank===1): ?><tr><td colspan="4" style="text-align:center;color:#aaa;padding:28px">No sales data yet</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

</div><!-- /container -->

<script>
// ── Order detail toggle ───────────────────────────────────────────────────
function toggleDetail(idx) {
    var row = document.getElementById('detail-' + idx);
    row.style.display = (row.style.display === '' || row.style.display === 'none') ? 'table-row' : 'none';
}

// ── POS Cart state ────────────────────────────────────────────────────────
var cart = [];
var modalProduct = null;

function openPOSModal(code, name, price, cat, isFood) {
    modalProduct = { code: code, name: name, price: price, cat: cat, isFood: isFood };
    document.getElementById('modalTitle').textContent = name + ' — ₱' + price.toFixed(2) + ' base';
    document.getElementById('mQty').value = 1;
    document.getElementById('mNotes').value = '';
    // Reset selects & checkboxes
    document.getElementById('mSize').value  = 'Medium';
    document.getElementById('mMilk').value  = 'Whole Milk';
    document.getElementById('mSugar').value = 'Regular';
    document.querySelectorAll('#mAddons input[type=checkbox]').forEach(function(cb){ cb.checked = false; });
    document.getElementById('drinkOptions').style.display = isFood ? 'none' : 'block';
    updateModalPrice();
    document.getElementById('posModal').classList.add('open');
}

function closePOSModal() {
    document.getElementById('posModal').classList.remove('open');
}

function updateModalPrice() {
    if (!modalProduct) return;
    var price  = modalProduct.price;
    var isFood = modalProduct.isFood;
    if (!isFood) {
        var sizeOpt = document.getElementById('mSize');
        price += parseFloat(sizeOpt.options[sizeOpt.selectedIndex].dataset.mod || 0);
        var milkOpt = document.getElementById('mMilk');
        price += parseFloat(milkOpt.options[milkOpt.selectedIndex].dataset.mod || 0);
        document.querySelectorAll('#mAddons input[type=checkbox]:checked').forEach(function(cb){
            price += parseFloat(cb.dataset.price || 0);
        });
    }
    var qty = parseInt(document.getElementById('mQty').value) || 1;
    document.getElementById('modalUnitPrice').textContent = '₱' + (price * qty).toFixed(2);
    return price;
}

// Attach change listeners to modal controls
['mSize','mMilk','mSugar'].forEach(function(id){
    var el = document.getElementById(id);
    if (el) el.addEventListener('change', updateModalPrice);
});
document.querySelectorAll('#mAddons input[type=checkbox]').forEach(function(cb){
    cb.addEventListener('change', updateModalPrice);
});

function addModalToCart() {
    if (!modalProduct) return;
    var isFood = modalProduct.isFood;
    var price  = modalProduct.price;
    var size   = '', milk = '', sugar = '', addons = [];

    if (!isFood) {
        var sizeOpt = document.getElementById('mSize');
        size  = sizeOpt.value;
        price += parseFloat(sizeOpt.options[sizeOpt.selectedIndex].dataset.mod || 0);
        var milkOpt = document.getElementById('mMilk');
        milk  = milkOpt.value;
        price += parseFloat(milkOpt.options[milkOpt.selectedIndex].dataset.mod || 0);
        sugar = document.getElementById('mSugar').value;
        document.querySelectorAll('#mAddons input[type=checkbox]:checked').forEach(function(cb){
            addons.push(cb.value);
            price += parseFloat(cb.dataset.price || 0);
        });
    }

    var qty    = parseInt(document.getElementById('mQty').value) || 1;
    var notes  = document.getElementById('mNotes').value.trim();
    var addonsStr = addons.join(', ');

    cart.push({
        code: modalProduct.code,
        name: modalProduct.name,
        price: modalProduct.price,
        unit_price: price,
        qty: qty,
        size: size,
        milk: milk,
        sugar: sugar,
        addons: addonsStr,
        notes: notes
    });

    closePOSModal();
    renderCart();
}

function removeFromCart(idx) {
    cart.splice(idx, 1);
    renderCart();
}

function clearCart() {
    cart = [];
    renderCart();
}

function renderCart() {
    var el = document.getElementById('posCartItems');
    if (!el) return;
    if (cart.length === 0) {
        el.innerHTML = '<div class="pos-cart-empty">Select items from the menu</div>';
        document.getElementById('posSubtotals').textContent = 'Subtotal: ₱0.00  |  VAT: ₱0.00';
        document.getElementById('posTotal').textContent = '₱0.00';
        return;
    }
    var html = '';
    var subtotal = 0;
    cart.forEach(function(item, idx) {
        var lineTotal = item.unit_price * item.qty;
        subtotal += lineTotal;
        var specs = [item.size, item.milk, item.sugar, item.addons].filter(Boolean).join(' · ');
        html += '<div class="pos-cart-row">';
        html += '<div class="pos-cart-row-info">';
        html += '<div class="pos-cart-row-name">' + item.name + ' x' + item.qty + '</div>';
        if (specs) html += '<div class="pos-cart-row-spec">' + specs + '</div>';
        if (item.notes) html += '<div class="pos-cart-row-spec">📝 ' + item.notes + '</div>';
        html += '</div>';
        html += '<div class="pos-cart-row-price">₱' + lineTotal.toFixed(2) + '</div>';
        html += '<button type="button" class="pos-cart-row-remove" onclick="removeFromCart(' + idx + ')">✕</button>';
        html += '</div>';
    });
    el.innerHTML = html;
    var tax   = subtotal * 0.12;
    var total = subtotal + tax;
    document.getElementById('posSubtotals').textContent = 'Subtotal: ₱' + subtotal.toFixed(2) + '  |  VAT: ₱' + tax.toFixed(2);
    document.getElementById('posTotal').textContent = '₱' + total.toFixed(2);
}

function prepareOrder(e) {
    if (cart.length === 0) { e.preventDefault(); alert('Please add items to the order first.'); return; }
    document.getElementById('itemsJson').value = JSON.stringify(cart);
}

// Close modal on background click
document.getElementById('posModal') && document.getElementById('posModal').addEventListener('click', function(e){
    if (e.target === this) closePOSModal();
});
</script>

</body>
</html>
