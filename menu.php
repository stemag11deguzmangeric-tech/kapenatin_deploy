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
$is_admin   = ($_SESSION['role'] ?? '') === 'admin';

// ── ADMIN: Delete product ────────────────────────────────────────────────────
if ($is_admin && isset($_POST['delete_product'])) {
    $del_code = $_POST['product_code'];
    $stmt = $mysqli->prepare("DELETE FROM products WHERE code = ?");
    $stmt->bind_param("s", $del_code);
    $stmt->execute();
    $stmt->close();
    header('Location: menu.php?deleted=1');
    exit;
}

// ── ADMIN: Add product ───────────────────────────────────────────────────────
$add_error = '';
if ($is_admin && isset($_POST['add_product'])) {
    $new_code  = trim($_POST['new_code']);
    $new_name  = trim($_POST['new_name']);
    $new_price = floatval($_POST['new_price']);
    $new_cat   = intval($_POST['new_category_id']);
    $new_desc  = trim($_POST['new_description']);

    // Customization availability flags
    $has_sizes  = isset($_POST['has_sizes'])  ? 1 : 0;
    $has_milk   = isset($_POST['has_milk'])   ? 1 : 0;
    $has_sugar  = isset($_POST['has_sugar'])  ? 1 : 0;
    $has_addons = isset($_POST['has_addons']) ? 1 : 0;

    if (!$new_code || !$new_name || $new_price <= 0 || !$new_cat) {
        $add_error = 'Please fill in all required fields.';
    } else {
        $chk = $mysqli->prepare("SELECT id FROM products WHERE code = ?");
        $chk->bind_param("s", $new_code);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) $add_error = 'Product code already exists.';
        $chk->close();

        // Handle image upload
        $image_path = null;
        if (!$add_error && isset($_FILES['new_image']) && $_FILES['new_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
            $ftype   = mime_content_type($_FILES['new_image']['tmp_name']);
            if (!in_array($ftype, $allowed)) {
                $add_error = 'Image must be JPG, PNG, GIF, or WEBP.';
            } elseif ($_FILES['new_image']['size'] > 5 * 1024 * 1024) {
                $add_error = 'Image must be under 5 MB.';
            } else {
                $upload_dir = 'imgs/products/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
                $ext        = pathinfo($_FILES['new_image']['name'], PATHINFO_EXTENSION);
                $filename   = preg_replace('/[^a-zA-Z0-9_-]/', '', $new_code) . '.' . strtolower($ext);
                $dest       = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['new_image']['tmp_name'], $dest)) {
                    $image_path = $dest;
                } else {
                    $add_error = 'Failed to save image. Check folder permissions.';
                }
            }
        }

        if (!$add_error) {
            // Store customization flags as JSON in description metadata isn't ideal —
            // we use the existing schema and store flags in the description field as a
            // simple prefix tag that the menu reader can strip. Better: alter the table.
            // For this project we store them as a JSON note appended to description.
            // Actually we'll use the products table as-is and track flags via session
            // post values; the customize box on the card already branches on category.
            // We'll save image_path into the image column.
            $ins = $mysqli->prepare("INSERT INTO products (code, name, price, category_id, description, image, has_sizes, has_milk, has_sugar, has_addons) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("ssdissiiii", $new_code, $new_name, $new_price, $new_cat, $new_desc, $image_path, $has_sizes, $has_milk, $has_sugar, $has_addons);
            if ($ins->execute()) {
                $ins->close();
                header('Location: menu.php?added_product=1');
                exit;
            } else {
                $add_error = $ins->error;
                $ins->close();
            }
        }
    }
}

// ── Add to cart ───────────────────────────────────────────────────────────────
if (isset($_POST['add_to_cart'])) {
    $product_code = $_POST['product_code'];
    $size_name    = $_POST['size_name'] ?? '';
    $milk_name    = $_POST['milk_name'] ?? '';
    $sugar_name   = $_POST['sugar_name'] ?? '';
    $notes        = $_POST['notes'] ?? '';
    $quantity     = max(1, intval($_POST['quantity'] ?? 1));

    $addons_arr = $_POST['addons'] ?? [];
    $addons_str = implode(', ', $addons_arr);

    $stmt = $mysqli->prepare("SELECT * FROM products WHERE code = ?");
    $stmt->bind_param("s", $product_code);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($product) {
        $size_mod = 0;
        if ($size_name == 'Medium') $size_mod = 20;
        if ($size_name == 'Large')  $size_mod = 40;
        if ($size_name == 'XL')     $size_mod = 60;

        $milk_mod = 0;
        if (in_array($milk_name, ['Oat Milk','Almond Milk','Coconut Milk'])) $milk_mod = 25;
        if ($milk_name == 'Soy Milk') $milk_mod = 20;

        $addon_price = 0;
        foreach ($addons_arr as $a) {
            if (in_array($a, ['Extra Shot','Vanilla Syrup','Caramel Syrup','Hazelnut Syrup','Brown Sugar Syrup'])) $addon_price += 20;
            elseif (in_array($a, ['Whipped Cream','Caramel Drizzle','Chocolate Drizzle'])) $addon_price += 15;
            elseif ($a == 'Cheese Foam') $addon_price += 35;
            elseif ($a == 'Boba Pearls') $addon_price += 30;
            elseif ($a == 'Cinnamon Powder') $addon_price += 10;
        }

        $unit_price = $product['price'] + $size_mod + $milk_mod + $addon_price;

        $check = $mysqli->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND product_code = ? AND size_name = ? AND milk_name = ? AND sugar_name = ? AND addons = ?");
        $check->bind_param("ssssss", $session_id, $product_code, $size_name, $milk_name, $sugar_name, $addons_str);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            $new_qty = $existing['quantity'] + $quantity;
            $upd = $mysqli->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $upd->bind_param("ii", $new_qty, $existing['id']);
            $upd->execute();
            $upd->close();
        } else {
            $ins = $mysqli->prepare("INSERT INTO cart (session_id, product_code, product_name, product_price, size_name, milk_name, sugar_name, addons, quantity, unit_price, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("sssdssssdds", $session_id, $product['code'], $product['name'], $product['price'], $size_name, $milk_name, $sugar_name, $addons_str, $quantity, $unit_price, $notes);
            $ins->execute();
            $ins->close();
        }
    }

    header('Location: menu.php?added=1');
    exit;
}

// ── Cart count ────────────────────────────────────────────────────────────────
$cart_stmt = $mysqli->prepare("SELECT SUM(quantity) as total FROM cart WHERE session_id = ?");
$cart_stmt->bind_param("s", $session_id);
$cart_stmt->execute();
$cart_count = $cart_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$cart_stmt->close();

// ── Categories (for add-product dropdown) ────────────────────────────────────
$cats_query = $mysqli->query("SELECT * FROM categories ORDER BY display_order");
$categories = [];
while ($c = $cats_query->fetch_assoc()) $categories[] = $c;

// ── Load products grouped by category ────────────────────────────────────────
$prods_query = $mysqli->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_available = 1 ORDER BY p.category_id, p.name");
$by_category = [];
while ($p = $prods_query->fetch_assoc()) {
    $by_category[$p['cat_name']][] = $p;
}

$show_added         = isset($_GET['added']);
$show_added_product = isset($_GET['added_product']);
$show_deleted       = isset($_GET['deleted']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Menu - Kape Natin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-image: url(bg.png); min-height: 100vh; }
        header {
            background: linear-gradient(135deg, #3d1c0b 0%, #8b5e3c 100%);
            box-shadow: 0 4px 12px rgba(61,28,11,0.3); width: 100%;
        }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 20px 30px; }
        .logo { font-family: 'Quiapo Free';font-size: 32px; font-weight: bold; color: white;letter-spacing: 6px; }
        .logo a{text-decoration: none; color:white;}
        .nav-links { display: flex; gap: 20px; align-items: center; }
        .nav-links a {
            text-decoration: none; color: white; font-weight: 500;
            padding: 8px 16px; border-radius: 5px; transition: all 0.3s;
        }
        .nav-links a:hover { background: rgba(255,255,255,0.2); }
        .cart-badge {
            background: #e74c3c; color: white;
            border-radius: 50%; padding: 2px 8px; font-size: 12px; margin-left: 5px;
        }
        .container { max-width: 90%; margin: 30px auto; padding: 20px; }
        .menu-container {
            background: white; padding: 30px;
            border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        h2 { color: #3d1c0b; margin-bottom: 10px; }
        .menu-container > p { color: #666; margin-bottom: 20px; }
        .alert {
            padding: 12px; border-radius: 5px; margin-bottom: 20px; text-align: center; font-weight: 500;
        }
        .alert-success { background: #27ae60; color: white; }
        .alert-danger  { background: #e74c3c; color: white; }
        .category-title {
            font-size: 20px; font-weight: bold; color: #8b5e3c;
            margin: 30px 0 15px; padding-bottom: 8px;
            border-bottom: 2px solid #c8864b;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px; margin-bottom: 10px;
        }
        .product-card {
            background: #faf6f0; border-radius: 10px;
            overflow: hidden; box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(139,94,60,0.2); }
        .product-img { width: 100%; height: 180px; object-fit: cover; display: block; }
        .product-img-placeholder {
            width: 100%; height: 180px;
            background: linear-gradient(135deg, #5c2d0e, #8b5e3c);
            display: flex; align-items: center; justify-content: center; font-size: 52px;
        }
        .product-info { padding: 18px; }
        .product-name { font-size: 17px; font-weight: 600; color: #333; margin-bottom: 6px; }
        .product-desc { font-size: 13px; color: #888; margin-bottom: 10px; }
        .product-price { font-size: 22px; font-weight: bold; color: #c8864b; margin-bottom: 12px; }
        .customize-toggle {
            background: none; border: 1px solid #c8864b; color: #8b5e3c;
            width: 100%; padding: 8px; border-radius: 5px;
            font-size: 13px; font-weight: 600; cursor: pointer; margin-bottom: 8px;
        }
        .customize-toggle:hover { background: #f5ede3; }
        .customize-box { display: none; padding: 12px; background: #f5ede3; border-radius: 5px; margin-bottom: 10px; }
        .customize-box label { font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px; margin-top: 10px; }
        .customize-box label:first-child { margin-top: 0; }
        .customize-box select, .customize-box input[type="text"] {
            width: 100%; padding: 7px; border: 1px solid #ddd;
            border-radius: 4px; font-size: 13px; font-family: inherit;
        }
        .customize-box select:focus, .customize-box input:focus { outline: none; border-color: #8b5e3c; }
        .addons-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; }
        .addon-label { font-size: 12px; display: flex; align-items: center; gap: 5px; font-weight: normal !important; margin-top: 0 !important; }
        .qty-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .qty-row label { margin: 0; font-size: 13px; font-weight: 600; color: #555; }
        .qty-row input[type="number"] { width: 60px; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
        .btn-add-cart {
            width: 100%; padding: 10px; background: #8b5e3c; color: white;
            border: none; border-radius: 5px; font-weight: 600; cursor: pointer; font-size: 14px;
        }
        .btn-add-cart:hover { background: #3d1c0b; }

        /* ── Admin: Delete button in customize box ── */
        .admin-product-info { font-size: 13px; color: #666; line-height: 1.9; }
        .admin-product-info strong { color: #333; }
        .admin-divider { border: none; border-top: 1px solid #ddd; margin: 12px 0; }
        .btn-delete-product {
            width: 100%; padding: 9px; background: none;
            border: 1.5px solid #e74c3c; color: #e74c3c;
            border-radius: 5px; font-weight: 600; cursor: pointer; font-size: 13px;
            transition: all 0.2s;
        }
        .btn-delete-product:hover { background: #e74c3c; color: white; }

        /* ── Admin: Add Product bar ── */
        .admin-bar { display: flex; justify-content: flex-end; margin-bottom: 24px; }
        .btn-add-product {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white; border: none; padding: 11px 24px; border-radius: 8px;
            font-size: 14px; font-weight: 600; cursor: pointer;
            box-shadow: 0 3px 10px rgba(39,174,96,0.3); transition: all 0.2s;
        }
        .btn-add-product:hover { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(39,174,96,0.4); }

        /* ── Admin badge on card ── */
        .admin-badge {
            position: absolute; top: 10px; right: 10px;
            background: rgba(61,28,11,0.85); color: #f5ede3;
            font-size: 10px; font-weight: 700; padding: 3px 9px; border-radius: 50px;
            letter-spacing: 0.5px; text-transform: uppercase; backdrop-filter: blur(2px);
        }

        /* ── Modals (shared) ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 9000;
            align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: white; border-radius: 14px;
            padding: 32px; max-width: 460px; width: 90%;
            box-shadow: 0 24px 60px rgba(0,0,0,0.25);
            animation: modalIn 0.2s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.93) translateY(-12px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-icon { font-size: 40px; margin-bottom: 14px; display: block; }
        .modal h3 { color: #3d1c0b; margin-bottom: 10px; font-size: 20px; }
        .modal p  { color: #666; font-size: 14px; line-height: 1.7; margin-bottom: 24px; }
        .modal-product-name { font-weight: 700; color: #3d1c0b; }
        .modal-actions { display: flex; gap: 12px; }
        .btn-modal-cancel {
            flex: 1; padding: 11px; background: #f0f0f0; color: #555;
            border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .btn-modal-cancel:hover { background: #e0e0e0; }
        .btn-modal-delete {
            flex: 1; padding: 11px; background: #e74c3c; color: white;
            border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .btn-modal-delete:hover { background: #c0392b; }
        .btn-modal-add {
            flex: 1; padding: 11px; background: #27ae60; color: white;
            border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .btn-modal-add:hover { background: #219a52; }

        /* ── Add Product Modal form ── */
        .modal.add-modal { max-width: 580px; max-height: 90vh; overflow-y: auto; padding: 0; }
        .add-modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 24px 28px 0; position: sticky; top: 0; background: white; z-index: 1;
            padding-bottom: 16px; border-bottom: 1px solid #f0ece8;
        }
        .add-modal-header h3 { color: #3d1c0b; font-size: 20px; margin: 0; }
        .modal-close-x {
            background: none; border: none; font-size: 18px; color: #aaa;
            cursor: pointer; padding: 4px 8px; border-radius: 6px; line-height: 1;
        }
        .modal-close-x:hover { background: #f5f5f5; color: #555; }
        #addProductForm { padding: 20px 28px 24px; }
        .form-section { margin-bottom: 22px; }
        .form-section-title {
            font-size: 11px; font-weight: 800; letter-spacing: 1px;
            text-transform: uppercase; color: #8b5e3c; margin-bottom: 12px;
            display: flex; align-items: center; gap: 8px;
        }
        .optional-tag {
            background: #f0ece8; color: #aaa; font-size: 10px;
            padding: 2px 8px; border-radius: 20px; font-weight: 600; letter-spacing: 0;
        }
        .form-section-hint { font-size: 12px; color: #aaa; margin-bottom: 12px; margin-top: -6px; }
        .form-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-row { margin-bottom: 14px; }
        .form-row label {
            display: block; font-size: 12px; font-weight: 700;
            color: #555; margin-bottom: 5px;
        }
        .req { color: #e74c3c; }
        .form-row input, .form-row select, .form-row textarea {
            width: 100%; padding: 9px 12px; border: 2px solid #e8e8e8;
            border-radius: 7px; font-size: 14px; font-family: inherit; transition: border-color 0.2s;
        }
        .form-row input:focus, .form-row select:focus, .form-row textarea:focus {
            outline: none; border-color: #8b5e3c;
        }
        .form-row textarea { resize: vertical; min-height: 68px; }

        /* ── Image upload zone ── */
        .image-upload-zone {
            border: 2px dashed #d5c5b5; border-radius: 10px;
            background: #fdfaf7; cursor: pointer;
            transition: all 0.2s; position: relative;
            min-height: 120px; display: flex; align-items: center; justify-content: center;
            overflow: hidden;
        }
        .image-upload-zone:hover { border-color: #8b5e3c; background: #f8f2ec; }
        .image-upload-zone.drag-over { border-color: #8b5e3c; background: #f0e6d8; }
        .image-upload-placeholder { text-align: center; padding: 20px; pointer-events: none; }
        .upload-icon { font-size: 32px; display: block; margin-bottom: 6px; }
        .upload-text { display: block; font-size: 14px; font-weight: 600; color: #8b5e3c; margin-bottom: 4px; }
        .upload-hint { display: block; font-size: 11px; color: #bbb; }
        .image-preview-img {
            width: 100%; height: 180px; object-fit: cover;
            border-radius: 8px; display: block;
        }
        .image-clear-btn {
            position: absolute; top: 8px; right: 8px;
            background: rgba(0,0,0,0.6); color: white;
            border: none; border-radius: 6px; padding: 4px 10px;
            font-size: 12px; font-weight: 600; cursor: pointer;
        }
        .image-clear-btn:hover { background: rgba(231,76,60,0.9); }

        /* ── Customization option toggle cards ── */
        .options-toggle-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;
        }
        .option-toggle-card {
            display: flex; flex-direction: column; align-items: center;
            border: 2px solid #e8e8e8; border-radius: 10px; padding: 12px 8px;
            cursor: pointer; transition: all 0.18s; text-align: center; user-select: none;
            position: relative;
        }
        .option-toggle-card input[type="checkbox"] {
            position: absolute; opacity: 0; width: 0; height: 0;
        }
        .option-toggle-card:hover { border-color: #c8864b; background: #fdfaf7; }
        .option-toggle-card.active { border-color: #8b5e3c; background: #f0e6d8; }
        .option-toggle-card.active .otc-label { color: #3d1c0b; }
        .otc-icon { font-size: 22px; margin-bottom: 5px; }
        .otc-label { font-size: 12px; font-weight: 700; color: #555; margin-bottom: 2px; }
        .otc-sub { font-size: 10px; color: #aaa; }

        /* ── Live customize preview pills ── */
        .customize-preview {
            margin-top: 14px; background: #f5ede3; border-radius: 8px;
            padding: 10px 14px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        }
        .cp-label { font-size: 11px; font-weight: 700; color: #8b5e3c; white-space: nowrap; }
        .cp-pill {
            background: white; border: 1px solid #d5c5b5; color: #555;
            font-size: 11px; padding: 3px 10px; border-radius: 20px; font-weight: 500;
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
                <?php if ($is_admin): ?>
                    <a href="staff_dashboard.php">Staff Dashboard</a>
                <?php elseif (($_SESSION['role'] ?? '') !== 'guest'): ?>
                    <a href="orders.php">My Orders</a>
                    <a href="cart.php">
                        🛒 Cart
                        <?php if ($cart_count > 0): ?>
                            <span class="cart-badge"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>
                <?php else: ?>
                    <a href="cart.php">
                        🛒 Cart
                        <?php if ($cart_count > 0): ?>
                            <span class="cart-badge"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['role'] === 'guest' ? 'Guest' : $_SESSION['username']); ?>)</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="menu-container">
            <h2>Our Menu</h2>
            <p><?php echo $is_admin ? 'Manage products — add new items or remove existing ones.' : 'Crafted to perfection, customized for you'; ?></p>

            <?php if ($show_added): ?>
                <div class="alert alert-success">✓ Item added to cart successfully!</div>
            <?php endif; ?>
            <?php if ($show_added_product): ?>
                <div class="alert alert-success">✓ New product added to the menu!</div>
            <?php endif; ?>
            <?php if ($show_deleted): ?>
                <div class="alert alert-success">🗑 Product removed from the menu.</div>
            <?php endif; ?>
            <?php if ($add_error): ?>
                <div class="alert alert-danger">⚠ <?php echo htmlspecialchars($add_error); ?></div>
            <?php endif; ?>

            <?php if ($is_admin): ?>
            <div class="admin-bar">
                <button class="btn-add-product" onclick="openAddModal()">＋ Add New Product</button>
            </div>
            <?php endif; ?>

            <?php
            $cat_emoji = ['Coffee'=>'☕','Non-Coffee'=>'🍵','Frappe'=>'🥤','Pastries'=>'🥐','Sandwiches'=>'🥪'];
            foreach ($by_category as $cat_name => $products):
                $emoji = $cat_emoji[$cat_name] ?? '☕';
            ?>
                <div class="category-title"><?php echo $emoji . ' ' . htmlspecialchars($cat_name); ?></div>
                <div class="products-grid">
                    <?php foreach ($products as $product):
                        $is_food = in_array($cat_name, ['Pastries','Sandwiches']);
                    ?>
                        <div class="product-card">
                            <?php if ($is_admin): ?>
                                <div class="admin-badge">✏ Admin</div>
                            <?php endif; ?>

                            <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-img">
                            <?php else: ?>
                                <div class="product-img-placeholder"><?php echo $emoji; ?></div>
                            <?php endif; ?>

                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-desc"><?php echo htmlspecialchars($product['description']); ?></div>
                                <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>

                                <button class="customize-toggle" onclick="toggleCustomize('<?php echo $product['code']; ?>')">
                                    <?php echo $is_admin ? '⚙ View / Manage' : '⚙ Customize & Add to Cart'; ?>
                                </button>

                                <div class="customize-box" id="custom-<?php echo $product['code']; ?>">
                                    <?php if ($is_admin): ?>
                                        <!-- Admin: product details + delete button -->
                                        <div class="admin-product-info">
                                            <strong>Code:</strong> <?php echo htmlspecialchars($product['code']); ?><br>
                                            <strong>Category:</strong> <?php echo htmlspecialchars($cat_name); ?><br>
                                            <strong>Base Price:</strong> ₱<?php echo number_format($product['price'], 2); ?>
                                        </div>
                                        <hr class="admin-divider">
                                        <button class="btn-delete-product"
                                            onclick="openDeleteModal(
                                                '<?php echo htmlspecialchars($product['code'], ENT_QUOTES); ?>',
                                                '<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>'
                                            )">
                                            🗑 Delete This Product
                                        </button>

                                    <?php else: ?>
                                        <!-- Customer: full customize + add to cart form -->
                                        <form method="POST">
                                            <input type="hidden" name="product_code" value="<?php echo htmlspecialchars($product['code']); ?>">

                                            <?php if (!$is_food): ?>
                                            <label>Size:</label>
                                            <select name="size_name">
                                                <option value="Small">Small (₱+0)</option>
                                                <option value="Medium" selected>Medium (₱+20)</option>
                                                <option value="Large">Large (₱+40)</option>
                                                <option value="XL">XL (₱+60)</option>
                                            </select>

                                            <label>Milk Type:</label>
                                            <select name="milk_name">
                                                <option value="Whole Milk">Whole Milk</option>
                                                <option value="Skim Milk">Skim Milk</option>
                                                <option value="Oat Milk">Oat Milk (₱+25)</option>
                                                <option value="Almond Milk">Almond Milk (₱+25)</option>
                                                <option value="Soy Milk">Soy Milk (₱+20)</option>
                                                <option value="Coconut Milk">Coconut Milk (₱+25)</option>
                                                <option value="No Milk">No Milk</option>
                                            </select>

                                            <label>Sugar Level:</label>
                                            <select name="sugar_name">
                                                <option value="No Sugar">No Sugar</option>
                                                <option value="Less Sweet">Less Sweet (30%)</option>
                                                <option value="Half Sweet">Half Sweet (50%)</option>
                                                <option value="Regular" selected>Regular (100%)</option>
                                                <option value="Extra Sweet">Extra Sweet (130%)</option>
                                            </select>

                                            <label>Add-ons:</label>
                                            <div class="addons-grid">
                                                <label class="addon-label"><input type="checkbox" name="addons[]" value="Extra Shot"> Extra Shot +₱20</label>
                                                <label class="addon-label"><input type="checkbox" name="addons[]" value="Vanilla Syrup"> Vanilla Syrup +₱20</label>
                                                <label class="addon-label"><input type="checkbox" name="addons[]" value="Caramel Syrup"> Caramel Syrup +₱20</label>
                                                <label class="addon-label"><input type="checkbox" name="addons[]" value="Hazelnut Syrup"> Hazelnut Syrup +₱20</label>
                                                <label class="addon-label"><input type="checkbox" name="addons[]" value="Whipped Cream"> Whipped Cream +₱15</label>
                                                <label class="addon-label"><input type="checkbox" name="addons[]" value="Caramel Drizzle"> Caramel Drizzle +₱15</label>
                                                <label class="addon-label"><input type="checkbox" name="addons[]" value="Boba Pearls"> Boba Pearls +₱30</label>
                                                <label class="addon-label"><input type="checkbox" name="addons[]" value="Cheese Foam"> Cheese Foam +₱35</label>
                                            </div>
                                            <?php else: ?>
                                                <input type="hidden" name="size_name" value="">
                                                <input type="hidden" name="milk_name" value="">
                                                <input type="hidden" name="sugar_name" value="">
                                            <?php endif; ?>

                                            <label>Special Notes:</label>
                                            <input type="text" name="notes" placeholder="e.g. less ice, extra hot...">

                                            <div class="qty-row" style="margin-top:10px;">
                                                <label>Qty:</label>
                                                <input type="number" name="quantity" value="1" min="1" max="20">
                                            </div>

                                            <button type="submit" name="add_to_cart" class="btn-add-cart">Add to Cart</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ═══ DELETE CONFIRMATION MODAL ═══════════════════════════════════════ -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <span class="modal-icon">🗑</span>
            <h3>Delete Product?</h3>
            <p>
                You're about to permanently remove
                <span class="modal-product-name" id="deleteProductName"></span>
                from the menu. This <strong>cannot be undone</strong>.
            </p>
            <div class="modal-actions">
                <button class="btn-modal-cancel" onclick="closeDeleteModal()">Cancel</button>
                <form method="POST" style="flex:1; display:flex;">
                    <input type="hidden" name="product_code" id="deleteProductCode">
                    <button type="submit" name="delete_product" class="btn-modal-delete" style="width:100%;">
                        Yes, Delete It
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══ ADD PRODUCT MODAL ════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="addModal">
        <div class="modal add-modal">
            <div class="add-modal-header">
                <h3>＋ Add New Product</h3>
                <button class="modal-close-x" onclick="closeAddModal()">✕</button>
            </div>

            <form method="POST" enctype="multipart/form-data" id="addProductForm">

                <!-- ── IMAGE UPLOAD ── -->
                <div class="form-section">
                    <div class="form-section-title">Product Image <span class="optional-tag">optional</span></div>
                    <div class="image-upload-zone" id="imageUploadZone" onclick="document.getElementById('new_image').click()">
                        <div class="image-upload-placeholder" id="imagePlaceholder">
                            <span class="upload-icon">📷</span>
                            <span class="upload-text">Click to upload photo</span>
                            <span class="upload-hint">JPG, PNG, WEBP · max 5 MB</span>
                        </div>
                        <img id="imagePreview" class="image-preview-img" src="" alt="Preview" style="display:none;">
                        <button type="button" class="image-clear-btn" id="imageClearBtn" style="display:none;" onclick="clearImage(event)">✕ Remove</button>
                    </div>
                    <input type="file" name="new_image" id="new_image" accept="image/*" style="display:none;" onchange="previewImage(this)">
                </div>

                <!-- ── BASIC INFO ── -->
                <div class="form-section">
                    <div class="form-section-title">Basic Info</div>
                    <div class="form-row">
                        <label>Product Name <span class="req">*</span></label>
                        <input type="text" name="new_name" placeholder="e.g. Caramel Cold Brew" required maxlength="200"
                            value="<?php echo htmlspecialchars($_POST['new_name'] ?? ''); ?>">
                    </div>
                    <div class="form-2col">
                        <div class="form-row">
                            <label>Product Code <span class="req">*</span></label>
                            <input type="text" name="new_code" placeholder="e.g. COF007" required maxlength="50"
                                value="<?php echo htmlspecialchars($_POST['new_code'] ?? ''); ?>">
                        </div>
                        <div class="form-row">
                            <label>Base Price (₱) <span class="req">*</span></label>
                            <input type="number" name="new_price" placeholder="e.g. 150" min="1" step="0.01" required
                                value="<?php echo htmlspecialchars($_POST['new_price'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <label>Category <span class="req">*</span></label>
                        <select name="new_category_id" required>
                            <option value="">— Select Category —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"
                                    <?php echo (($_POST['new_category_id'] ?? '') == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Description</label>
                        <textarea name="new_description" placeholder="Short description shown on the menu card..."><?php echo htmlspecialchars($_POST['new_description'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- ── CUSTOMIZATION OPTIONS ── -->
                <div class="form-section">
                    <div class="form-section-title">Customization Options</div>
                    <p class="form-section-hint">Choose which options customers can customise for this product.</p>

                    <div class="options-toggle-grid">
                        <label class="option-toggle-card <?php echo isset($_POST['has_sizes'])  ? 'active' : ''; ?>">
                            <input type="checkbox" name="has_sizes"  <?php echo isset($_POST['has_sizes'])  ? 'checked' : ''; ?>>
                            <span class="otc-icon">📏</span>
                            <span class="otc-label">Sizes</span>
                            <span class="otc-sub">S / M / L / XL</span>
                        </label>
                        <label class="option-toggle-card <?php echo isset($_POST['has_milk'])   ? 'active' : ''; ?>">
                            <input type="checkbox" name="has_milk"   <?php echo isset($_POST['has_milk'])   ? 'checked' : ''; ?>>
                            <span class="otc-icon">🥛</span>
                            <span class="otc-label">Milk Type</span>
                            <span class="otc-sub">Whole, Oat, Soy…</span>
                        </label>
                        <label class="option-toggle-card <?php echo isset($_POST['has_sugar'])  ? 'active' : ''; ?>">
                            <input type="checkbox" name="has_sugar"  <?php echo isset($_POST['has_sugar'])  ? 'checked' : ''; ?>>
                            <span class="otc-icon">🍬</span>
                            <span class="otc-label">Sugar Level</span>
                            <span class="otc-sub">None to Extra</span>
                        </label>
                        <label class="option-toggle-card <?php echo isset($_POST['has_addons']) ? 'active' : ''; ?>">
                            <input type="checkbox" name="has_addons" <?php echo isset($_POST['has_addons']) ? 'checked' : ''; ?>>
                            <span class="otc-icon">✨</span>
                            <span class="otc-label">Add-ons</span>
                            <span class="otc-sub">Syrup, Foam…</span>
                        </label>
                    </div>

                    <!-- Live preview of what customer will see -->
                    <div class="customize-preview" id="customizePreview" style="display:none;">
                        <div class="cp-label">Customer will see:</div>
                        <div class="cp-pills" id="cpPills"></div>
                    </div>
                </div>

                <div class="modal-actions" style="margin-top:8px;">
                    <button type="button" class="btn-modal-cancel" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" name="add_product" class="btn-modal-add">＋ Add to Menu</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function toggleCustomize(id) {
        var box = document.getElementById('custom-' + id);
        box.style.display = box.style.display === 'block' ? 'none' : 'block';
    }

    // ── Delete modal ─────────────────────────────────────────────────────────
    function openDeleteModal(code, name) {
        document.getElementById('deleteProductCode').value = code;
        document.getElementById('deleteProductName').textContent = '"' + name + '"';
        document.getElementById('deleteModal').classList.add('active');
    }
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
    });

    // ── Add product modal ────────────────────────────────────────────────────
    function openAddModal() {
        document.getElementById('addModal').classList.add('active');
    }
    function closeAddModal() {
        document.getElementById('addModal').classList.remove('active');
    }
    document.getElementById('addModal').addEventListener('click', function(e) {
        if (e.target === this) closeAddModal();
    });

    // ── Image upload preview ─────────────────────────────────────────────────
    function previewImage(input) {
        var file = input.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('imagePreview').style.display = 'block';
            document.getElementById('imagePlaceholder').style.display = 'none';
            document.getElementById('imageClearBtn').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
    function clearImage(event) {
        event.stopPropagation();
        document.getElementById('new_image').value = '';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('imagePreview').src = '';
        document.getElementById('imagePlaceholder').style.display = 'block';
        document.getElementById('imageClearBtn').style.display = 'none';
    }

    // Drag-and-drop onto the upload zone
    var zone = document.getElementById('imageUploadZone');
    if (zone) {
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });
        zone.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            var file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                var input = document.getElementById('new_image');
                var dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
                previewImage(input);
            }
        });
    }

    // ── Customization toggle cards ────────────────────────────────────────────
    var optionLabels = {
        has_sizes:  'Sizes (S/M/L/XL)',
        has_milk:   'Milk Type',
        has_sugar:  'Sugar Level',
        has_addons: 'Add-ons'
    };

    function updateCustomizePreview() {
        var pills = [];
        document.querySelectorAll('.option-toggle-card input[type="checkbox"]').forEach(function(cb) {
            if (cb.checked) pills.push(optionLabels[cb.name] || cb.name);
        });
        var preview = document.getElementById('customizePreview');
        var pillsDiv = document.getElementById('cpPills');
        if (pills.length > 0) {
            pillsDiv.innerHTML = pills.map(function(p) {
                return '<span class="cp-pill">✓ ' + p + '</span>';
            }).join('');
            preview.style.display = 'flex';
        } else {
            preview.style.display = 'none';
        }
    }

    document.querySelectorAll('.option-toggle-card').forEach(function(card) {
        var cb = card.querySelector('input[type="checkbox"]');
        cb.addEventListener('change', function() {
            card.classList.toggle('active', cb.checked);
            updateCustomizePreview();
        });
    });

    // Init preview on page load (for error re-open)
    updateCustomizePreview();

    <?php if ($add_error): ?>
    window.addEventListener('DOMContentLoaded', openAddModal);
    <?php endif; ?>
    </script>
</body>
</html>
