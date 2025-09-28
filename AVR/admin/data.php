<?php
require_once __DIR__ . '/../includes/functions.php';
ensure_admin();

header('Content-Type: application/json');

try {
    // Counts
    $pdo = db();
    $pendingOrders = (int)$pdo->query("SELECT COUNT(*) c FROM orders WHERE order_status='pending'")->fetch()['c'];
    $pendingServices = 0; try { $pendingServices = (int)$pdo->query("SELECT COUNT(*) c FROM services WHERE status='Pending'")->fetch()['c']; } catch(Throwable $e) {}
    $pendingMsgs = 0; try { $pendingMsgs = (int)$pdo->query("SELECT COUNT(DISTINCT o.order_id) c FROM order_messages om JOIN orders o ON o.order_id=om.order_id WHERE o.order_status='pending'")->fetch()['c']; } catch(Throwable $e) {}
    // Unseen messages since last visit to admin_messages.php (session-based)
    $newMsgs = 0; try { $seen = (int)($_SESSION['admin_msgs_seen'] ?? 0); $st = $pdo->prepare('SELECT COUNT(*) c FROM order_messages WHERE id > :s'); $st->execute([':s'=>$seen]); $newMsgs = (int)$st->fetch()['c']; } catch(Throwable $e) {}
    $products = (int)$pdo->query('SELECT COUNT(*) c FROM products')->fetch()['c'];
    $revenue = (float)$pdo->query("SELECT IFNULL(SUM(total_price),0) t FROM orders WHERE order_status = 'delivered'")->fetch()['t'];

    // Recent orders
    $recent = $pdo->query('SELECT order_id,customer_name,total_price,order_status,created_at,delivery_date FROM orders ORDER BY created_at DESC LIMIT 8')->fetchAll();

    // Top customers
    $topCustomers = $pdo->query("SELECT u.id,u.name,u.email,COUNT(o.order_id) cnt,SUM(o.total_price) amt FROM users u JOIN orders o ON o.user_id=u.id WHERE (u.role IS NULL OR u.role<>'admin') GROUP BY u.id,u.name,u.email ORDER BY cnt DESC, amt DESC LIMIT 5")->fetchAll();

    // Overview (last 14 days)
    $overview = $pdo->query("SELECT DATE(created_at) d, SUM(total_price) sales, COUNT(*) orders FROM orders WHERE created_at >= DATE_SUB(CURDATE(),INTERVAL 13 DAY) GROUP BY DATE(created_at) ORDER BY d ASC")->fetchAll();
    $ovMap = []; foreach($overview as $o){ $ovMap[$o['d']]=$o; }
    $days=[]; for($i=13;$i>=0;$i--){ $d=date('Y-m-d',strtotime("-$i day")); $row=$ovMap[$d]??['d'=>$d,'sales'=>0,'orders'=>0]; $days[]=$row; }

    echo json_encode([
        'counts' => [
            'pendingOrders' => $pendingOrders,
            'pendingServices' => $pendingServices,
            'pendingMsgs' => $pendingMsgs,
            'products' => $products,
            'revenue' => $revenue,
            'newMsgs' => $newMsgs,
        ],
        'recent' => $recent,
        'topCustomers' => $topCustomers,
        'overview' => $days,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => true]);
}

?>
