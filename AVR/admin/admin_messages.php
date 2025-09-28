<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/ui.php';
ensure_admin();
// mark all messages as seen for counter reset
try { $lastId = (int)db()->query('SELECT IFNULL(MAX(id),0) m FROM order_messages')->fetch()['m']; $_SESSION['admin_msgs_seen']=$lastId; } catch(Throwable $e) {}
admin_layout_start('Messages');
// Message Seller (customer notes at checkout/cart): latest per order
$orders = db()->query("SELECT o.order_id, o.customer_name,
  (SELECT m.message FROM order_messages m WHERE m.order_id = o.order_id AND m.user_id IS NOT NULL ORDER BY m.id DESC LIMIT 1) AS note,
  (SELECT m.created_at FROM order_messages m WHERE m.order_id = o.order_id AND m.user_id IS NOT NULL ORDER BY m.id DESC LIMIT 1) AS msg_time
  FROM orders o
  WHERE EXISTS (SELECT 1 FROM order_messages m2 WHERE m2.order_id = o.order_id AND m2.user_id IS NOT NULL)
  ORDER BY msg_time DESC LIMIT 50")->fetchAll();
// Service descriptions: recent submissions
try {
  $services = db()->query("SELECT id, customer_name, description, created_at FROM services ORDER BY created_at DESC LIMIT 50")->fetchAll();
} catch (Throwable $e) { $services = []; }
?>
<div class="card">
  <div style="font-weight:700;margin-bottom:8px">Message Seller (Checkout / Cart)</div>
  <div style="overflow:auto"><table class="table rtable"><thead><tr><th>ID</th><th>Customer</th><th>Message</th><th>Date</th></tr></thead><tbody>
    <?php foreach ($orders as $r): ?>
      <tr>
        <td data-label="ID">#<?= (int)$r['order_id']; ?></td>
        <td data-label="Customer"><?= h($r['customer_name']); ?></td>
        <td data-label="Message" style="max-width:520px;font-size:13px;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= h($r['note'] ?? ''); ?>"><?= h($r['note'] ?? ''); ?></td>
        <td data-label="Date"><?= h(date('M d, Y H:i', strtotime($r['msg_time'] ?? ($r['created_at'] ?? 'now')))); ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>
<div class="card" style="margin-top:16px">
  <div style="font-weight:700;margin-bottom:8px">Service Requests (Descriptions)</div>
  <div style="overflow:auto"><table class="table rtable"><thead><tr><th>ID</th><th>Customer</th><th>Description</th><th>Date</th></tr></thead><tbody>
    <?php foreach ($services as $s): ?>
      <tr>
        <td data-label="ID">#<?= (int)$s['id']; ?></td>
        <td data-label="Customer"><?= h($s['customer_name']); ?></td>
        <td data-label="Description" style="max-width:620px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= h($s['description']); ?>"><?= h($s['description']); ?></td>
        <td data-label="Date"><?= h(date('M d, Y H:i', strtotime($s['created_at']))); ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php admin_layout_end(); ?>
