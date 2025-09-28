<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Basic site settings
const SITE_NAME = 'AVR Shop';
const CURRENCY = '₱';

// Ensure key assets exist by copying from legacy paths once per session
function ensure_assets(): void {
    static $done = false; if ($done) return; $done = true;
    $base = dirname(__DIR__);
    $dstLogo = $base . '/assets/images/logo.png';
    $logoSources = [
        // Prefer a logo shipped alongside the project (AVR root)
        $base . '/logo.png',
        // Fallback to legacy sibling (old structure)
        dirname($base) . '/logo.png',
    ];
    if (!file_exists($dstLogo)) {
        foreach ($logoSources as $srcLogo) {
            if (file_exists($srcLogo)) { @copy($srcLogo, $dstLogo); break; }
        }
    }
    $dstBg = $base . '/assets/images/bg.jpg';
    $bgCandidates = [dirname($base) . '/picture products/bg/bg.jpg', dirname($base) . '/picture products/bg/1.jpg'];
    if (!file_exists($dstBg)) {
        foreach ($bgCandidates as $src) { if (file_exists($src)) { @copy($src, $dstBg); break; } }
    }
}
ensure_assets();

// Ensure order_status enum includes 'shipped' (adds seamlessly if already present)
try { db()->exec("ALTER TABLE orders MODIFY order_status ENUM('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending'"); } catch(Throwable $e) {}

// Compute project base URL (e.g., /website/AVR). Works under subfolders and Windows paths.
if (!defined('BASE_URL')) {
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $base = '';
    if ($docRoot && strpos($projectRoot, $docRoot) === 0) {
        $base = substr($projectRoot, strlen($docRoot));
    }
    $base = '/' . ltrim($base, '/');
    define('BASE_URL', rtrim($base, '/'));
}

// Image path normalization: map legacy DB paths like
// "picture products/product 1/1.jpg" to self-contained assets under
// {BASE_URL}/assets/images/products/product 1/1.jpg, copying on first access.
function product_image_url($path): string {
    $base = dirname(__DIR__); // project root (filesystem)
    $public_prefix = BASE_URL ?: '';
    if (!$path) return $public_prefix . '/assets/images/logo.png';
    // Normalize slashes and trim known prefixes
    $p = str_replace('\\', '/', trim((string)$path));
    if ($p === '') return $public_prefix . '/assets/images/logo.png';
    // If already absolute under project base, just return
    if (BASE_URL && str_starts_with($p, BASE_URL . '/')) return $p;
    // If already under assets/images, prefix /avr
    if (preg_match('#^(assets/|/assets/)#i', $p)) {
        $p = ltrim($p, '/');
        return $public_prefix . '/' . $p;
    }
    // Strip possible leading legacy folder name
    $sub = preg_replace('#^(?:/?picture products/)+#i', '', $p);
    $sub = ltrim($sub, '/');
    // Reject path traversal
    $sub = str_replace(['..', '\\'], ['', '/'], $sub);
    // 1) If a file already exists at assets/images/{sub}, use it directly (your current assets layout)
    $existingRel = 'assets/images/' . $sub;
    $existingAbs = $base . '/' . $existingRel;
    if (file_exists($existingAbs) && is_file($existingAbs)) {
        return $public_prefix . '/' . $existingRel;
    }
    // 2) Otherwise, destination under assets/images/products/{sub}
    $destRel = 'assets/images/products/' . $sub;
    $destAbs = $base . '/' . $destRel;
    // If not present yet, try to copy from legacy location if it exists
    if (!file_exists($destAbs)) {
        $srcAbs = dirname($base) . '/picture products/' . $sub; // legacy root sibling
        if (file_exists($srcAbs) && is_file($srcAbs)) {
            @mkdir(dirname($destAbs), 0777, true);
            @copy($srcAbs, $destAbs);
        }
    }
    // If still not present, fall back to original relative path under /avr (may work if user copied folder)
    if (!file_exists($destAbs)) {
        $fallback = $public_prefix . '/' . ltrim($p, '/');
        return $fallback;
    }
    return $public_prefix . '/' . $destRel;
}

function is_logged_in(): bool { return !empty($_SESSION['user']); }
function current_user() { return $_SESSION['user'] ?? null; }

function login(string $usernameOrEmail, string $password): bool {
    // Use two distinct named params (MySQL PDO doesn't allow reusing the same name twice)
    $sql = 'SELECT * FROM users WHERE (email = :email OR username = :username) AND status = "active" LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute([':email' => $usernameOrEmail, ':username' => $usernameOrEmail]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);
        $_SESSION['user'] = $user;
        return true;
    }
    return false;
}

function register_user(array $data, &$error = null): bool {
    try {
        $stmt = db()->prepare('INSERT INTO users (email, name, location, phone, username, password, role, status) VALUES (:email,:name,:location,:phone,:username,:password, "customer", "active")');
        $stmt->execute([
            ':email' => trim($data['email'] ?? ''),
            ':name' => trim($data['name'] ?? ''),
            ':location' => trim($data['location'] ?? ''),
            ':phone' => trim($data['phone'] ?? ''),
            ':username' => trim($data['username'] ?? ''),
            ':password' => password_hash($data['password'] ?? '', PASSWORD_BCRYPT)
        ]);
        return true;
    } catch (PDOException $e) {
        $error = $e->getMessage();
        return false;
    }
}

function logout(): void { $_SESSION = []; session_destroy(); }

// Products
function get_products(): array {
    $stmt = db()->query('SELECT id, sku, name, price, short_description, image, stock, type FROM products WHERE is_active = 1 ORDER BY created_at DESC');
    return $stmt->fetchAll();
}

function get_product(int $id) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

// Total quantity sold for a product (from order_items)
function product_sold_count(int $productId): int {
    try {
        $stmt = db()->prepare('SELECT COALESCE(SUM(quantity),0) AS sold FROM order_items WHERE product_id = :id');
        $stmt->execute([':id' => $productId]);
        $row = $stmt->fetch();
        return (int)($row['sold'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

// Return multiple image URLs for a product by scanning the product folder under assets/images
function get_product_images(array $product): array {
    $images = [];
    $first = product_image_url($product['image'] ?? '');
    if ($first) $images[] = $first;
    // Try sibling numbered images in a product-specific folder derived from the first image path
    $base = dirname(__DIR__);
    $pubBase = BASE_URL;
    // Heuristic: if first is like /.../assets/images/product X/1.jpg, scan that folder
        $firstPath = str_replace('\\','/',$first);
    if (preg_match('#(.*/assets/images/[^\n]*/)(?:[0-9]+\.[a-zA-Z]+)$#',$firstPath,$m)) {
        $folderPub = rtrim($m[1],'/');
        $folderAbs = $base . substr($folderPub, strlen($pubBase));
        if (is_dir($folderAbs)) {
            $files = @scandir($folderAbs) ?: [];
            natcasesort($files);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                if (!preg_match('/\.(jpe?g|png|webp)$/i',$f)) continue;
                $url = $folderPub . '/' . $f;
                if (!in_array($url, $images, true)) $images[] = $url;
            }
        }
    }
    return array_values(array_unique($images));
}

// Cart stored per user (if logged) or per session token
function cart_token(): string {
    if (!isset($_SESSION['cart_token'])) {
        $_SESSION['cart_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['cart_token'];
}

function add_to_cart(int $product_id, int $qty = 1): void {
    // Block adding if no stock
    try {
        $chk = db()->prepare('SELECT stock FROM products WHERE id = :id');
        $chk->execute([':id' => $product_id]);
        $st = (int)($chk->fetchColumn() ?: 0);
        if ($st <= 0) return;
    } catch (Throwable $e) { /* ignore and proceed */ }
    $user_id = is_logged_in() ? current_user()['id'] : null;
    $token = cart_token();
    // upsert
    if ($user_id) {
        $sql = 'INSERT INTO cart (user_id, product_id, quantity, session_token) VALUES (:u,:p,:q,:t)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = CURRENT_TIMESTAMP';
        $stmt = db()->prepare($sql);
        $stmt->execute([':u'=>$user_id, ':p'=>$product_id, ':q'=>$qty, ':t'=>$token]);
    } else {
        // session-only row; allow multiple same product rows by aggregating later
        $stmt = db()->prepare('INSERT INTO cart (user_id, product_id, quantity, session_token) VALUES (0, :p, :q, :t)');
        $stmt->execute([':p'=>$product_id, ':q'=>$qty, ':t'=>$token]);
    }
}

function update_cart(int $product_id, int $qty): void {
    $user_id = is_logged_in() ? current_user()['id'] : 0;
    $token = cart_token();
    if ($qty <= 0) {
        $sql = 'DELETE FROM cart WHERE product_id = :p AND (session_token = :t OR user_id = :u)';
        $stmt = db()->prepare($sql);
        $stmt->execute([':p'=>$product_id, ':t'=>$token, ':u'=>$user_id]);
        return;
    }
    $sql = 'UPDATE cart SET quantity = :q, updated_at = CURRENT_TIMESTAMP WHERE product_id = :p AND (session_token = :t OR user_id = :u)';
    $stmt = db()->prepare($sql);
    $stmt->execute([':q'=>$qty, ':p'=>$product_id, ':t'=>$token, ':u'=>$user_id]);
}

function get_cart(): array {
    $user_id = is_logged_in() ? current_user()['id'] : 0;
    $token = cart_token();
    $sql = 'SELECT c.product_id as id, p.name, p.price, p.image, SUM(c.quantity) as quantity
            FROM cart c JOIN products p ON p.id = c.product_id
            WHERE (c.session_token = :t OR c.user_id = :u)
            GROUP BY c.product_id, p.name, p.price, p.image';
    $stmt = db()->prepare($sql);
    $stmt->execute([':t'=>$token, ':u'=>$user_id]);
    $items = $stmt->fetchAll();
    foreach ($items as &$it) { $it['subtotal'] = (float)$it['price'] * (int)$it['quantity']; }
    return $items;
}

function cart_totals(array $items): array {
    $total = 0.0; $qty = 0;
    foreach ($items as $it) { $total += (float)$it['subtotal']; $qty += (int)$it['quantity']; }
    // Promo rules: 5% off ONLY when exactly 4 items; 5 or more items = FREE shipping and NO 5% off
    $discount = ($qty === 4) ? $total * 0.05 : 0.0;
    $shipping = ($qty >= 5) ? 0.0 : 3000.0;
    $grand = max(0, $total - $discount) + $shipping;
    return ['qty'=>$qty,'total'=>$total,'discount'=>$discount,'shipping'=>$shipping,'grand'=>$grand];
}

// Orders (COD only)
function place_order_cod(array $customer, array $cartItems, array $totals, ?array $selectedIds = null): int {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $user_id = is_logged_in() ? current_user()['id'] : null;
        $stmt = $pdo->prepare('INSERT INTO orders (customer_name, contact_number, delivery_location, payment_method, product_name, quantity, total_price, delivery_date, user_id, customer_email, is_paid, order_status) VALUES (:name,:phone,:addr, "COD", :pname, :qty, :amount, :ddate, :uid, :email, 0, "pending")');
        $summaryName = count($cartItems) === 1 ? $cartItems[0]['name'] : ($cartItems[0]['name'] . ' and others');
        $totalQty = $totals['qty'];
        $stmt->execute([
            ':name'=>$customer['name'], ':phone'=>$customer['phone'], ':addr'=>$customer['address'],
            ':pname'=>$summaryName, ':qty'=>$totalQty, ':amount'=>$totals['grand'], ':ddate'=>$customer['delivery_date'],
            ':uid'=>$user_id, ':email'=>$customer['email']
        ]);
        $order_id = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, product_image, quantity, unit_price, total_price) VALUES (:oid,:pid,:name,:img,:qty,:price,:total)');
        foreach ($cartItems as $it) {
            $itemStmt->execute([
                ':oid'=>$order_id, ':pid'=>$it['id'], ':name'=>$it['name'], ':img'=>$it['image'], ':qty'=>$it['quantity'], ':price'=>$it['price'], ':total'=>$it['subtotal']
            ]);
            // stock reduce
            $upd = $pdo->prepare('UPDATE products SET stock = GREATEST(0, stock - :q) WHERE id = :id');
            $upd->execute([':q'=>$it['quantity'], ':id'=>$it['id']]);
        }

        // Clear cart: all or only selected
        $token = cart_token();
        $uid = is_logged_in() ? current_user()['id'] : 0;
        if ($selectedIds && count($selectedIds) > 0) {
            // Delete only chosen product_ids
            $in = implode(',', array_fill(0, count($selectedIds), '?'));
            $params = $selectedIds;
            array_push($params, $token, $uid);
            $sql = 'DELETE FROM cart WHERE product_id IN (' . $in . ') AND (session_token = ? OR user_id = ?)';
            $del = $pdo->prepare($sql);
            $del->execute($params);
        } else {
            $del = $pdo->prepare('DELETE FROM cart WHERE session_token = :t OR user_id = :u');
            $del->execute([':t'=>$token, ':u'=>$uid]);
        }

        $pdo->commit();
        return $order_id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function user_orders(): array {
    if (!is_logged_in()) return [];
    $stmt = db()->prepare('SELECT * FROM orders WHERE user_id = :u ORDER BY created_at DESC');
    $stmt->execute([':u'=>current_user()['id']]);
    return $stmt->fetchAll();
}

function order_first_item(int $orderId): ?array {
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :oid ORDER BY id ASC LIMIT 1');
    $stmt->execute([':oid'=>$orderId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_order(int $orderId) {
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_id = :oid LIMIT 1');
    $stmt->execute([':oid'=>$orderId]);
    return $stmt->fetch();
}

function get_order_items(int $orderId): array {
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :oid ORDER BY id ASC');
    $stmt->execute([':oid'=>$orderId]);
    return $stmt->fetchAll();
}

function cancel_order(int $orderId): bool {
    if (!is_logged_in()) return false;
    // Only allow cancel if it belongs to the user and still pending
    $stmt = db()->prepare('UPDATE orders SET order_status = "cancelled" WHERE order_id = :oid AND user_id = :uid AND order_status = "pending"');
    $stmt->execute([':oid'=>$orderId, ':uid'=>current_user()['id']]);
    return $stmt->rowCount() > 0;
}

function get_user(int $id) {
    $stmt = db()->prepare('SELECT id, email, name, location, phone, username, role, status FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id'=>$id]);
    return $stmt->fetch();
}

function update_user_profile(int $id, array $data): bool {
    $stmt = db()->prepare('UPDATE users SET email = :email, name = :name, location = :location, phone = :phone WHERE id = :id');
    return $stmt->execute([
        ':email'=>trim($data['email'] ?? ''),
        ':name'=>trim($data['name'] ?? ''),
        ':location'=>trim($data['location'] ?? ''),
        ':phone'=>trim($data['phone'] ?? ''),
        ':id'=>$id
    ]);
}

function ensure_admin(): void {
    if (!is_logged_in() || (current_user()['role'] ?? '') !== 'admin') {
        header('Location: ' . BASE_URL . '/signin.php');
        exit;
    }
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Simple order message support (customer note at checkout) ---
function save_order_message(int $orderId, int $userId=null, string $msg): void {
    $msg = trim($msg); if($msg==='') return; $pdo = db();
    // Create table if it doesn't exist (idempotent cheap check)
    static $checked=false; if(!$checked){
        $pdo->exec("CREATE TABLE IF NOT EXISTS order_messages (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, user_id INT NULL, message TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(order_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $checked=true;
    }
    $stmt=$pdo->prepare('INSERT INTO order_messages (order_id,user_id,message) VALUES (:o,:u,:m)');
    $stmt->execute([':o'=>$orderId, ':u'=>$userId, ':m'=>$msg]);
}

function order_messages(int $orderId): array {
    $stmt = db()->prepare('SELECT * FROM order_messages WHERE order_id = :o ORDER BY id ASC');
    $stmt->execute([':o'=>$orderId]);
    return $stmt->fetchAll();
}

?>
