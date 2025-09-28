<?php
require_once __DIR__ . '/includes/functions.php';
$body_class = 'mobile-orders-header';

// Lightweight JSON endpoint: {BASE_URL}/orders.php?count=pending
if (isset($_GET['count']) && $_GET['count'] === 'pending') {
    header('Content-Type: application/json');
  try {
    $stmt = db()->query("SELECT COUNT(*) AS c FROM orders WHERE order_status IN ('pending','confirmed','pending_rating')");
        $row = $stmt->fetch();
        echo json_encode(['pending' => (int)($row['c'] ?? 0)]);
    } catch (Throwable $e) {
        echo json_encode(['pending' => 0]);
    }
    exit;
}

// Live JSON for a single order status (customer-facing)
if (isset($_GET['json']) && $_GET['json'] === 'order' && isset($_GET['id'])) {
  header('Content-Type: application/json');
  $oid = (int)$_GET['id'];
  $o = get_order($oid);
  if (!$o || !is_logged_in() || (int)$o['user_id'] !== (int)current_user()['id']) { echo json_encode(['error'=>true]); exit; }
  echo json_encode([
    'order_id' => (int)$o['order_id'],
    'order_status' => (string)$o['order_status'],
    'total_price' => (float)$o['total_price'],
    'updated_at' => (string)($o['updated_at'] ?? $o['created_at']),
  ]);
  exit;
}

// Live JSON for the current user's orders list (brief fields)
if (isset($_GET['json']) && $_GET['json'] === 'list') {
  header('Content-Type: application/json');
  if (!is_logged_in()) { echo json_encode(['orders'=>[]]); exit; }
  $rows = user_orders();
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      'order_id' => (int)$r['order_id'],
      'order_status' => (string)$r['order_status'],
      'total_price' => (float)$r['total_price'],
      'created_at' => (string)$r['created_at'],
      'quantity' => (int)$r['quantity'],
      'product_name' => (string)$r['product_name'],
    ];
  }
  echo json_encode(['orders'=>$out]);
  exit;
}

if (!is_logged_in()) { header('Location: ' . BASE_URL . '/signin.php'); exit; }
$order_id = (int)($_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel']) && $order_id>0) {
  cancel_order($order_id);
  header('Location: ' . BASE_URL . '/orders.php?id=' . $order_id);
  exit;
}
$detail = $order_id > 0;
if ($detail) {
  $o = get_order($order_id);
  if (!$o || (int)$o['user_id'] !== (int)current_user()['id']) { header('Location: ' . BASE_URL . '/orders.php'); exit; }
  $items = get_order_items($order_id);
  $page_title = 'Order Details'; // hide internal ID from page title
} else {
  $page_title = 'My Orders';
  $orders = user_orders();
}
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <?php if ($detail): ?>
  <a href="<?= BASE_URL ?>/orders.php" class="btn-back">Back</a>
  <?php $custLabel = [ 'pending'=>'Pending', 'confirmed'=>'Order Placed', 'shipped'=>'Order Shipped Out', 'delivered'=>'Order Received', 'cancelled'=>'Cancelled' ][$o['order_status']] ?? ucfirst($o['order_status']); ?>
  <h2 style="margin-top:8px">Order Details <span id="detailStatus" class="badge" style="background:#e0ecfb;color:#0b4f8f"><?= h($custLabel); ?></span></h2>

      <!-- Simple 4-step tracker: Pending → Placed → Shipped → Received -->
      <?php
        $st = strtolower((string)$o['order_status']);
        $pending = ($st==='pending');
        $placed = in_array($st, ['confirmed','shipped','delivered']);
        $shipped = in_array($st, ['shipped','delivered']);
        $received = in_array($st, ['delivered']);
        function step($label,$active,$key){ ?>
          <div class="stp" data-step="<?= h($key); ?>" style="display:flex;align-items:center;gap:10px">
            <div class="stp-dot" style="width:30px;height:30px;border-radius:999px;border:3px solid <?= $active?'#16a34a':'#94a3b8' ?>;display:flex;align-items:center;justify-content:center;color:<?= $active?'#16a34a':'#94a3b8' ?>;font-weight:800">✔</div>
            <div class="stp-label" style="color:<?= $active?'#111827':'#64748b' ?>;font-weight:600;min-width:110px"><?= $label ?></div>
          </div>
        <?php } ?>
      <div id="stepper" style="display:flex;gap:24px;align-items:center;margin:10px 0 16px">
        <?php step('Pending', $pending || (!$placed && !$shipped && !$received), 'pending'); ?>
        <div style="flex:1;height:2px;background:#e5e7eb"></div>
        <?php step('Order Placed', $placed, 'placed'); ?>
        <div style="flex:1;height:2px;background:#e5e7eb"></div>
        <?php step('Order Shipped Out', $shipped, 'shipped'); ?>
        <div style="flex:1;height:2px;background:#e5e7eb"></div>
        <?php step('Order Received', $received, 'received'); ?>
      </div>

      <div class="form" style="margin-top:12px">
        <div style="display:flex;flex-direction:column;gap:12px">
          <?php foreach ($items as $it): ?>
            <div style="display:flex;gap:12px;align-items:center">
              <img src="<?= h(product_image_url($it['product_image'])); ?>" alt="" style="width:72px;height:72px;object-fit:contain;border:1px solid #e5e7eb;border-radius:8px;background:#fff"/>
              <div style="flex:1">
                <div style="font-weight:600"><a href="<?= BASE_URL ?>/product.php?id=<?= (int)$it['product_id']; ?>" style="text-decoration:none;color:inherit"><?= h($it['product_name']); ?></a></div>
                <div style="color:#64748b;font-size:13px">Qty: <?= (int)$it['quantity']; ?> × <?= CURRENCY . number_format($it['unit_price'],2); ?></div>
              </div>
              <div style="font-weight:700;white-space:nowrap"><?= CURRENCY . number_format($it['total_price'],2); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <hr />
        <div style="display:flex;justify-content:flex-end;gap:16px;align-items:center">
          <div style="font-size:18px;font-weight:800;color:#024787">Order Total: <?= CURRENCY . number_format($o['total_price'],2); ?></div>
          <?php if ($o['order_status']==='pending'): ?>
          <form method="post" action="<?= BASE_URL ?>/orders.php?id=<?= (int)$o['order_id']; ?>" onsubmit="return confirm('Cancel this order?');">
            <input type="hidden" name="cancel" value="1" />
            <button class="btn secondary" type="submit">Cancel Order</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <h2>My Orders</h2>
      <?php if (!$orders): ?>
        <p>No orders yet. <a href="<?= BASE_URL ?>/index.php#home-products">Start shopping</a>.</p>
      <?php else: ?>
  <div id="ordersList" style="display:flex;flex-direction:column;gap:14px">
          <?php foreach ($orders as $o): $item = order_first_item((int)$o['order_id']); ?>
          <article style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:var(--shadow);overflow:hidden">
            <div style="display:flex;align-items:stretch;gap:12px;padding:12px">
              <div style="width:92px;height:92px;border-radius:10px;overflow:hidden;background:#fff;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center">
                <?php $img = $item['product_image'] ?? ''; ?>
                <img src="<?= h(product_image_url($img)); ?>" alt="" style="width:100%;height:100%;object-fit:contain" />
              </div>
              <div style="flex:1;min-width:0">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:center">
                  <strong style="font-size:16px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($o['product_name']); ?></strong>
                  <?php $custLabel = [ 'pending'=>'Pending','confirmed'=>'Order Placed','shipped'=>'Order Shipped Out','delivered'=>'Order Received','cancelled'=>'Cancelled' ][$o['order_status']] ?? ucfirst($o['order_status']); ?>
                  <span class="badge order-status" data-order-id="<?= (int)$o['order_id']; ?>" style="background:#e0ecfb;color:#0b4f8f"><?= h($custLabel); ?></span>
                </div>
                <div style="color:#64748b;font-size:13px;margin-top:4px">
                  <span><?= h(date('Y-m-d', strtotime($o['created_at']))); ?></span> • <span><?= (int)$o['quantity']; ?> item(s)</span>
                </div>
              </div>
              <div style="text-align:right;min-width:140px;display:flex;flex-direction:column;justify-content:center">
                <div style="font-weight:800;color:#024787;">Total: <?= CURRENCY . number_format($o['total_price'], 2); ?></div>
                <a class="btn link" href="<?= BASE_URL ?>/orders.php?id=<?= (int)$o['order_id']; ?>">View item</a>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
(function(){
  // Auto-refresh for order detail page
  var detailBadge = document.getElementById('detailStatus');
  if(detailBadge){
    var m = location.search.match(/id=(\d+)/); var oid = m? parseInt(m[1],10):0;
    function label(st){
      var map={pending:'Pending',confirmed:'Order Placed',shipped:'Order Shipped Out',delivered:'Order Received',cancelled:'Cancelled'}; return map[st]||String(st||'').replace(/^./,c=>c.toUpperCase());
    }
    function updateSteps(st){
      var stepper=document.getElementById('stepper'); if(!stepper) return;
      var state=String(st||'').toLowerCase();
      var act={ pending: state==='pending', placed: ['confirmed','shipped','delivered'].includes(state), shipped: ['shipped','delivered'].includes(state), received: state==='delivered' };
      stepper.querySelectorAll('.stp').forEach(function(node){
        var key=node.getAttribute('data-step'); var on=!!act[key];
        var dot=node.querySelector('.stp-dot'); var lb=node.querySelector('.stp-label');
        if(dot){ dot.style.borderColor = on?'#16a34a':'#94a3b8'; dot.style.color = on?'#16a34a':'#94a3b8'; }
        if(lb){ lb.style.color = on?'#111827':'#64748b'; }
      });
    }
    function tick(){ fetch('<?= BASE_URL ?>/orders.php?json=order&id='+oid,{cache:'no-store'}).then(r=>r.json()).then(function(j){
      if(j && !j.error && j.order_status){ detailBadge.textContent = label(j.order_status); updateSteps(j.order_status); }
    }).catch(function(){}); }
    setInterval(tick, 3000); setTimeout(tick, 800);
  }
  // Auto-refresh for orders list page
  var listRoot = document.getElementById('ordersList');
  if(listRoot){
    function label(st){ var map={pending:'Pending',confirmed:'Order Placed',shipped:'Order Shipped Out',delivered:'Order Received',cancelled:'Cancelled'}; return map[st]||String(st||'').replace(/^./,c=>c.toUpperCase()); }
    function tick(){ fetch('<?= BASE_URL ?>/orders.php?json=list',{cache:'no-store'}).then(r=>r.json()).then(function(j){
      if(!j || !Array.isArray(j.orders)) return;
      // Only update the status badges in-place to avoid layout flicker
      j.orders.forEach(function(o){
        var el = document.querySelector('.order-status[data-order-id="'+o.order_id+'"]'); if(el){ el.textContent = label(o.order_status); }
      });
    }).catch(function(){}); }
    setInterval(tick, 4000); setTimeout(tick, 1000);
  }
})();
</script>
