<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/ui.php';
ensure_admin();
$msg = $_SESSION['flash_msg'] ?? null; unset($_SESSION['flash_msg']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  $action = $_POST['action'] ?? '';
  $map = [ 'confirm' => 'confirmed', 'ship' => 'shipped', 'done' => 'delivered', 'cancel' => 'cancelled' ];
  if ($id && isset($map[$action])) {
    $stmt = db()->prepare('UPDATE orders SET order_status = :s WHERE order_id = :id');
    $stmt->execute([':s'=>$map[$action], ':id'=>$id]);
    $_SESSION['flash_msg'] = 'Order #' . $id . ' updated to ' . $map[$action];
    header('Location: ' . BASE_URL . '/admin/admin_orders.php');
    exit;
  }
}
$orders = db()->query('SELECT * FROM orders ORDER BY created_at DESC')->fetchAll();
admin_layout_start('All Orders');
?>
  <div class="card" style="margin-bottom:16px;max-width:420px;margin-left:auto;margin-right:auto">
    <h3 style="margin-top:0">Delivery Calendar</h3>
    <div id="adminCal" style="width:100%;max-width:360px"></div>
    <small style="color:#64748b">Red days are busy (confirmed orders + next 3 days blocked)</small>
  </div>
  <?php if ($msg): ?><div class="card"><?= h($msg); ?></div><?php endif; ?>
  <div style="overflow:auto"><table class="table rtable"><thead><tr><th>ID</th><th>Customer</th><th>Items</th><th>Amount</th><th>Status</th><th>Payment</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($orders as $o): ?>
    <tr>
      <td data-label="ID">#<?= (int)$o['order_id']; ?></td>
      <td data-label="Customer"><?= h($o['customer_name']); ?><br><small><?= h($o['contact_number']); ?></small></td>
      <td data-label="Items"><?= h($o['product_name']); ?> (<?= (int)$o['quantity']; ?>)</td>
      <td data-label="Amount"><?= CURRENCY . number_format($o['total_price'],2); ?></td>
      <?php $label = [
        'pending'=>'Pending',
        'confirmed'=>'Order Placed',
        'shipped'=>'Order Shipped Out',
        'delivered'=>'Order Received',
        'cancelled'=>'Cancelled'
      ][$o['order_status']] ?? $o['order_status']; ?>
      <td data-label="Status"><span class="badge"><?= h($label); ?></span></td>
      <td data-label="Payment"><?= $o['payment_method']; ?><?= $o['is_paid']? ' (paid)':''; ?></td>
      <td data-label="Actions">
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <?php if ($o['order_status']==='pending'): ?>
            <form method="post">
              <input type="hidden" name="id" value="<?= (int)$o['order_id']; ?>">
              <input type="hidden" name="action" value="confirm">
              <button class="btn" title="Confirm">Confirm</button>
            </form>
            <a class="btn secondary" href="<?= BASE_URL ?>/admin/order_view.php?id=<?= (int)$o['order_id']; ?>">View</a>
            <form method="post" onsubmit="return confirm('Cancel this order?');">
              <input type="hidden" name="id" value="<?= (int)$o['order_id']; ?>">
              <input type="hidden" name="action" value="cancel">
              <button class="btn secondary">Cancel</button>
            </form>
          <?php elseif ($o['order_status']==='confirmed'): ?>
            <form method="post">
              <input type="hidden" name="id" value="<?= (int)$o['order_id']; ?>">
              <input type="hidden" name="action" value="ship">
              <button class="btn" title="Out for Delivery">Out for Delivery</button>
            </form>
            <a class="btn secondary" href="<?= BASE_URL ?>/admin/order_view.php?id=<?= (int)$o['order_id']; ?>">View</a>
            <form method="post" onsubmit="return confirm('Cancel this order?');">
              <input type="hidden" name="id" value="<?= (int)$o['order_id']; ?>">
              <input type="hidden" name="action" value="cancel">
              <button class="btn secondary">Cancel</button>
            </form>
          <?php elseif ($o['order_status']==='shipped'): ?>
            <form method="post">
              <input type="hidden" name="id" value="<?= (int)$o['order_id']; ?>">
              <input type="hidden" name="action" value="done">
              <button class="btn" title="Mark as Done">Mark as Done</button>
            </form>
            <a class="btn secondary" href="<?= BASE_URL ?>/admin/order_view.php?id=<?= (int)$o['order_id']; ?>">View</a>
          <?php else: ?>
            <a class="btn secondary" href="<?= BASE_URL ?>/admin/order_view.php?id=<?= (int)$o['order_id']; ?>">View</a>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php admin_layout_end(); ?>
<script>
(function(){
  var root=document.getElementById('adminCal'); if(!root) return; var busy=[];
  function draw(base){
    var y=base.getFullYear(),m=base.getMonth();
    var first=new Date(y,m,1), start=first.getDay(), days=new Date(y,m+1,0).getDate();
    var html='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">'+
      "<button data-nav='-1' style='background:#e2e8f0;border:0;width:26px;height:26px;border-radius:4px;cursor:pointer'>‹</button>"+
      '<strong>'+first.toLocaleString(undefined,{month:'long'})+' '+y+'</strong>'+"<button data-nav='1' style='background:#e2e8f0;border:0;width:26px;height:26px;border-radius:4px;cursor:pointer'>›</button></div>";
    html+='<table style="width:100%;border-collapse:collapse;font-size:13px;text-align:center"><thead><tr>'+['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(d=>'<th style="padding:2px 0;font-size:12px;color:#8fbbe2">'+d+'</th>').join('')+'</tr></thead><tbody>';
    var d=1;
    for(var w=0; w<6 && d<=days; w++){
      html+='<tr>';
      for(var i=0;i<7;i++){
        if(w===0 && i<start || d>days){ html+='<td></td>'; }
        else {
          var ds=y+'-'+String(m+1).padStart(2,'0')+'-'+String(d).padStart(2,'0');
          var isBusy=busy.indexOf(ds)>-1;
          html+='<td style="padding:3px"><div style="padding:8px 0;border-radius:6px;font-size:15px;font-weight:700;'+
            (isBusy?'background:#fee2e2;color:#991b1b':'background:#e3f2fd;color:#0c3557')+'">'+d+'</div></td>';
          d++;
        }
      }
      html+='</tr>';
    }
    html+='</tbody></table>';
    root.innerHTML=html;
    root.querySelectorAll('[data-nav]').forEach(btn=>btn.onclick=function(){ var dir=parseInt(this.getAttribute('data-nav')); draw(new Date(y,m+dir,1)); });
  }
  fetch('<?= BASE_URL ?>/busy_dates.php').then(r=>r.json()).then(j=>{ busy=j.busy||[]; draw(new Date()); });
})();
</script>
