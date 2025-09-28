<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/json');
// Collect confirmed orders and block the delivery date plus next 3 days (total 4-day span)
// Orders (confirmed or shipped) block 4-day span
$rows = db()->query("SELECT delivery_date FROM orders WHERE order_status IN ('confirmed','shipped')")->fetchAll();
$busy = [];
foreach ($rows as $r) {
        $d = $r['delivery_date']; if(!$d) continue; for ($i=0;$i<4;$i++) $busy[] = date('Y-m-d', strtotime($d." +$i day"));
}
// Services (Confirmed) also block 4-day span
try {
    $srows = db()->query("SELECT preferred_date FROM services WHERE status='Confirmed'")->fetchAll();
    foreach ($srows as $sr){ $d=$sr['preferred_date']; if(!$d) continue; for($i=0;$i<4;$i++) $busy[] = date('Y-m-d', strtotime($d." +$i day")); }
} catch(Throwable $e) {}
$busy = array_values(array_unique($busy));
sort($busy);
echo json_encode(['busy'=>$busy]);
