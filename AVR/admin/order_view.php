<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/ui.php';
ensure_admin();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order = $id ? get_order($id) : null;
if (!$order) { header('Location: ' . BASE_URL . '/admin/admin_orders.php'); exit; }
$items = get_order_items($id);
$msgs = function_exists('order_messages') ? order_messages($id) : [];
admin_layout_start('Order Details');
?>
<div style="max-width:980px;margin:0 auto">
  <a class="btn-back" href="<?= BASE_URL ?>/admin/admin_orders.php" style="margin-bottom:16px">Back to Orders</a>
  <div style="background:linear-gradient(135deg,#0c3557,#0d4873);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:24px;position:relative;overflow:hidden">
    <div style="font-weight:600;font-size:19px;margin:0 0 20px 0">Order Details</div>
  <div class="ov-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:760px;font-size:14px;line-height:1.55">
      <div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Order ID:</span> #<?= (int)$order['order_id']; ?></div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Customer:</span> <?= h(strtoupper($order['customer_name'])); ?></div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Contact:</span> <?= h($order['contact_number']); ?></div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Address:</span> <?= h($order['delivery_location']); ?></div>
      </div>
      <div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Products:</span> <?= count($items); ?> item(s)</div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Total:</span> <?= CURRENCY . number_format($order['total_price'],2); ?></div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Payment Method:</span> <?= h($order['payment_method']); ?></div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Paid:</span> <?= $order['is_paid']? 'Paid':'Unpaid'; ?></div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Order Status:</span> <?= h(strtoupper($order['order_status'])); ?></div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Delivery Date:</span> <?= h($order['delivery_date'] ?: '-'); ?></div>
      </div>
    </div>
    <?php if($items): ?>
      <div style="margin-top:20px;font-weight:600;font-size:14px">Product Items</div>
      <div style="display:flex;flex-wrap:wrap;gap:18px;margin-top:10px">
        <?php foreach($items as $it): $img=product_image_url($it['product_image']); ?>
          <div style="width:108px;text-align:center;font-size:11px;line-height:1.3">
            <div style="width:98px;height:98px;background:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;margin:0 auto 6px;box-shadow:0 0 0 1px rgba(255,255,255,.15)">
              <img src="<?= h($img); ?>" alt="" style="max-width:84px;max-height:84px;object-fit:contain">
            </div>
            <div style="color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= h($it['product_name']); ?>"><?= h($it['product_name']); ?></div>
            <div style="color:#90acc2;font-size:11px">x <?= (int)$it['quantity']; ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <hr style="margin:26px 0;border:0;border-top:1px solid rgba(255,255,255,.15)">
    <div style="font-weight:600;margin-bottom:8px">Messages</div>
    <?php if(!$msgs): ?>
      <div style="font-size:13px;color:#90acc2">No recent activity</div>
    <?php else: foreach($msgs as $m): ?>
      <div style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,.08)">
        <div style="font-size:13px;white-space:pre-wrap;line-height:1.4;color:#e2e8f0;"><?= h($m['message']); ?></div>
        <div style="font-size:11px;color:#89a6bb;margin-top:3px;"><?= h(date('M d, Y H:i', strtotime($m['created_at']))); ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php admin_layout_end(); ?>
<style>@media(max-width:900px){ .ov-grid{ grid-template-columns:1fr !important } }</style>
