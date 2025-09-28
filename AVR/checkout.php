<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Checkout (COD)';

// Direct single-product checkout (from product page) ?pid= & qty=
$direct = false; $pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0; $reqQty = isset($_GET['qty']) ? max(1,(int)$_GET['qty']) : 1;
if ($pid) {
  $p = get_product($pid);
  if ($p) {
    $direct = true;
    $items = [[
      'id'=>$p['id'],
      'name'=>$p['name'],
      'price'=>$p['price'],
      'image'=>$p['image'],
      'quantity'=>$reqQty,
      'subtotal'=>$p['price'] * $reqQty
    ]];
  }
}

if (!isset($items)) { // normal cart-based flow
  $all = get_cart();
  $selectedIds = array_map('intval', (array)($_REQUEST['sel'] ?? []));
  if (!$selectedIds && !empty($_SESSION['checkout_sel'])) { $selectedIds = array_map('intval', (array)$_SESSION['checkout_sel']); unset($_SESSION['checkout_sel']); }
  if ($selectedIds) {
    $items = array_values(array_filter($all, function($it) use ($selectedIds){ return in_array((int)$it['id'], $selectedIds, true); }));
  } else {
    $items = $all;
  }
}
if (!$items) { header('Location: ' . BASE_URL . '/cart.php'); exit; }
$totals = cart_totals($items);

$message = $_SESSION['flash_msg'] ?? null; unset($_SESSION['flash_msg']); $order_id = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = current_user() ?: [];
  $customer = [
    'name' => $u['name'] ?? trim($_POST['name'] ?? ''),
    'email' => $u['email'] ?? trim($_POST['email'] ?? ''),
    'phone' => $u['phone'] ?? trim($_POST['phone'] ?? ''),
    'address' => trim($_POST['address'] ?? ($u['location'] ?? '')),
    'delivery_date' => $_POST['delivery_date'] ?? date('Y-m-d', strtotime('+3 days')),
  ];
  // Server-side busy-date guard: block confirmed/shipped orders and confirmed services (4-day span)
  try {
    $d = $customer['delivery_date'];
    $q1 = db()->prepare("SELECT 1 FROM orders WHERE order_status IN ('confirmed','shipped') AND delivery_date IS NOT NULL AND :d BETWEEN delivery_date AND DATE_ADD(delivery_date, INTERVAL 3 DAY) LIMIT 1");
    $q1->execute([':d'=>$d]);
    $q2 = db()->prepare("SELECT 1 FROM services WHERE status='Confirmed' AND preferred_date IS NOT NULL AND :d BETWEEN preferred_date AND DATE_ADD(preferred_date, INTERVAL 3 DAY) LIMIT 1");
    $q2->execute([':d'=>$d]);
    if ($q1->fetch() || $q2->fetch()) {
      $_SESSION['flash_msg'] = 'Selected delivery date is unavailable. Please choose another available date.';
      if (!empty($_POST['sel'])) { $_SESSION['checkout_sel'] = array_map('intval', (array)$_POST['sel']); }
      header('Location: ' . BASE_URL . '/checkout.php');
      exit;
    } else {
      $order_id = place_order_cod($customer, $items, $totals, isset($selectedIds) ? ($selectedIds ?: null) : null);
      if(!empty($_POST['note'])) save_order_message($order_id, $u['id']??null, (string)$_POST['note']);
      header('Location: ' . BASE_URL . '/orders.php?id=' . (int)$order_id);
      exit;
    }
  } catch (Throwable $e) {
    $_SESSION['flash_msg'] = 'Failed to place order: ' . $e->getMessage();
    header('Location: ' . BASE_URL . '/checkout.php');
    exit;
  }
}
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <h2>Checkout</h2>
    <?php if ($message): ?>
      <div class="form" style="margin-bottom:16px; background:#ecfeff; border-color:#a5f3fc;">
        <?= h($message); ?>
        <?php if ($order_id): ?>
          <div style="margin-top:8px"><a class="btn" href="<?= BASE_URL ?>/orders.php">Go to My Orders</a></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($direct): ?>
      <form class="form" method="post" style="padding:0;border:none;background:transparent">
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;padding:16px">
          <h3 style="margin-top:0">Delivery Address</h3>
          <div style="font-weight:600; color:#0f172a;"><?= h((current_user()['name'] ?? '')); ?> <span style="font-weight:400">(<?= h(current_user()['phone'] ?? ''); ?>)</span></div>
          <div style="margin-top:4px; color:#475569; line-height:1.4;"><?= h(current_user()['location'] ?? ''); ?></div>
          <div style="margin-top:10px; max-width:420px">
            <label style="display:block;font-size:13px;color:#475569">Change Address<br>
              <textarea name="address" rows="3" style="width:100%;margin-top:4px" required><?= h(current_user()['location'] ?? ''); ?></textarea>
            </label>
          </div>
          <div style="margin-top:10px"><label style="font-size:13px;color:#475569">Delivery Date<br><input type="text" name="delivery_date" placeholder="YYYY-MM-DD" value="<?= h(date('Y-m-d', strtotime('+3 days'))); ?>" style="margin-top:4px" readonly></label></div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;padding:16px">
          <h3 style="margin-top:0">Products Ordered</h3>
          <?php $it = $items[0]; ?>
          <div class="po-grid-head">
            <div></div>
            <div>Item</div>
            <div style="text-align:right">Unit Price</div>
            <div style="text-align:right">Qty</div>
            <div style="text-align:right">Subtotal</div>
          </div>
          <div class="po-row">
            <div class="po-img"><img src="<?= h(product_image_url($it['image'])); ?>" alt=""></div>
            <div class="po-title"><?= h($it['name']); ?></div>
            <div class="po-meta">
              <div class="po-price"><?= CURRENCY . number_format($it['price'],2); ?></div>
              <div class="po-qty">× <?= (int)$it['quantity']; ?></div>
              <div class="po-sub"><strong><?= CURRENCY . number_format($it['subtotal'],2); ?></strong></div>
            </div>
          </div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:16px">
          <h3 style="margin-top:0">Payment Method</h3>
          <div style="display:flex;justify-content:space-between;align-items:center"><div>Cash on Delivery</div><span class="badge">COD</span></div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px">
          <h3 style="margin-top:0">Order Summary</h3>
          <div style="margin:0 0 10px">
            <label style="display:block;font-size:13px;color:#475569">Message to Seller (optional)<br>
              <textarea name="note" rows="2" style="width:100%;margin-top:4px" placeholder="Add instructions or requests..."></textarea>
            </label>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:0"><span>Merchandise Subtotal</span><strong><?= CURRENCY . number_format($totals['total'],2); ?></strong></div>
          <div style="display:flex;justify-content:space-between;margin-top:4px"><span>Shipping</span><strong><?= $totals['shipping']>0 ? CURRENCY . number_format($totals['shipping'],2) : 'FREE'; ?></strong></div>
          <?php if ($totals['discount']>0): ?>
            <div style="display:flex;justify-content:space-between;margin-top:4px"><span>Discount</span><strong>- <?= CURRENCY . number_format($totals['discount'],2); ?></strong></div>
          <?php endif; ?>
          <hr />
          <div style="display:flex;justify-content:space-between;font-size:18px;color:#dc2626"><span>Total Payment:</span><strong><?= CURRENCY . number_format($totals['grand'],2); ?></strong></div>
          <div style="text-align:right;margin-top:16px"><button class="btn">Place Order</button></div>
        </div>
      </form>
    <?php else: ?>
      <form class="form" method="post" style="padding:0;border:none;background:transparent">
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;padding:16px">
          <h3 style="margin-top:0">Delivery Address</h3>
          <div style="font-weight:600; color:#0f172a;"><?= h((current_user()['name'] ?? '')); ?> <span style="font-weight:400">(<?= h(current_user()['phone'] ?? ''); ?>)</span></div>
          <div style="margin-top:4px; color:#475569; line-height:1.4;">&nbsp;<?= h(current_user()['location'] ?? ''); ?></div>
          <div style="margin-top:10px; max-width:420px">
            <label style="display:block;font-size:13px;color:#475569">Change Address<br>
              <textarea name="address" rows="3" style="width:100%;margin-top:4px" required><?= h(current_user()['location'] ?? ''); ?></textarea>
            </label>
          </div>
          <div style="margin-top:10px"><label style="font-size:13px;color:#475569">Delivery Date<br><input type="text" name="delivery_date" placeholder="YYYY-MM-DD" value="<?= h(date('Y-m-d', strtotime('+3 days'))); ?>" style="margin-top:4px" readonly></label></div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;padding:16px">
          <h3 style="margin-top:0">Products Ordered</h3>
          <div class="po-grid-head">
            <div></div>
            <div>Item</div>
            <div style="text-align:right">Unit Price</div>
            <div style="text-align:right">Qty</div>
            <div style="text-align:right">Subtotal</div>
          </div>
          <?php foreach ($items as $it): ?>
            <div class="po-row">
              <div class="po-img"><img src="<?= h(product_image_url($it['image'])); ?>" alt=""></div>
              <div class="po-title"><?= h($it['name']); ?></div>
              <div class="po-meta">
                <div class="po-price"><?= CURRENCY . number_format($it['price'],2); ?></div>
                <div class="po-qty">× <?= (int)$it['quantity']; ?></div>
                <div class="po-sub"><strong><?= CURRENCY . number_format($it['subtotal'],2); ?></strong></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:16px">
          <h3 style="margin-top:0">Payment Method</h3>
          <div style="display:flex;justify-content:space-between;align-items:center"><div>Cash on Delivery</div><span class="badge">COD</span></div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px">
          <h3 style="margin-top:0">Order Summary</h3>
          <div style="margin:0 0 10px">
            <label style="display:block;font-size:13px;color:#475569">Message to Seller (optional)<br>
              <textarea name="note" rows="2" style="width:100%;margin-top:4px" placeholder="Add instructions or requests..."></textarea>
            </label>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:0"><span>Merchandise Subtotal</span><strong><?= CURRENCY . number_format($totals['total'],2); ?></strong></div>
          <div style="display:flex;justify-content:space-between;margin-top:4px"><span>Shipping</span><strong><?= $totals['shipping']>0 ? CURRENCY . number_format($totals['shipping'],2) : 'FREE'; ?></strong></div>
          <?php if ($totals['discount']>0): ?><div style="display:flex;justify-content:space-between;margin-top:4px"><span>Discount</span><strong>- <?= CURRENCY . number_format($totals['discount'],2); ?></strong></div><?php endif; ?>
          <hr />
          <div style="display:flex;justify-content:space-between;font-size:18px;color:#dc2626"><span>Total Payment:</span><strong><?= CURRENCY . number_format($totals['grand'],2); ?></strong></div>
          <div style="text-align:right;margin-top:16px"><button class="btn">Place Order</button></div>
        </div>
        <?php foreach (($selectedIds ?? []) as $sid): ?><input type="hidden" name="sel[]" value="<?= (int)$sid; ?>" /><?php endforeach; ?>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
// Lightweight inline calendar: appears on focus, blocks busy dates (confirmed orders + next 3 days)
(function(){
  var busy=[]; var cal; var inpEls; var openFor=null;
  function fetchBusy(){ fetch('<?= BASE_URL ?>/busy_dates.php').then(r=>r.json()).then(j=>{busy=j.busy||[];}); }
  function build(){ if(cal) return; cal=document.createElement('div'); cal.id='miniCal'; cal.style.cssText='position:absolute;z-index:50;background:#fff;border:1px solid #cbd5e1;border-radius:6px;padding:6px;width:250px;font:12px system-ui,Arial,sans-serif;box-shadow:0 4px 18px rgba(0,0,0,.08);display:none'; document.body.appendChild(cal); document.addEventListener('click',e=>{ if(!cal.contains(e.target) && ![...inpEls].includes(e.target)) hide(); }); }
  function hide(){ cal.style.display='none'; openFor=null; }
  function draw(date){ // date is first day of month
    var y=date.getFullYear(), m=date.getMonth(); var first=new Date(y,m,1); var start=first.getDay(); var days=new Date(y,m+1,0).getDate();
    var html='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">'
      +'<button type=button data-nav="-1" style="background:#e2e8f0;border:0;width:26px;height:26px;border-radius:4px;cursor:pointer">‹</button>'
      +'<strong style="font-size:13px">'+first.toLocaleString(undefined,{month:'long'})+' '+y+'</strong>'
      +'<button type=button data-nav="1" style="background:#e2e8f0;border:0;width:26px;height:26px;border-radius:4px;cursor:pointer">›</button>'
      +'</div><table style="width:100%;border-collapse:collapse;font-size:11px;text-align:center">'
      +'<thead><tr>'+['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(d=>'<th style="padding:2px 0;font-weight:600;color:#334155">'+d+'</th>').join('')+'</tr></thead><tbody>';
    var d=1; for(var w=0; w<6 && d<=days; w++){ html+='<tr>'; for(var i=0;i<7;i++){ var cell=''; if(w===0 && i<start || d>days){ cell=''; }
        else { var ds=y+'-'+String(m+1).padStart(2,'0')+'-'+String(d).padStart(2,'0'); var isBusy=busy.indexOf(ds)>-1; var todayStr=new Date().toISOString().slice(0,10); var past=ds<todayStr; var cls=''; var style='padding:3px;'; if(isBusy){ style+='background:#fee2e2;color:#991b1b;border-radius:4px;'; }
          else if(!past){ style+='cursor:pointer;background:#f1f5f9;border-radius:4px;'; }
          if(openFor && openFor.value===ds) style+='outline:2px solid #0ea5e9'; cell='<td><div data-date="'+ds+'" style="'+style+'">'+d+'</div></td>'; d++; html+=cell; continue; }
        html+='<td></td>'; }
      html+='</tr>'; }
    html+='</tbody></table><div style="margin-top:4px;font-size:10px;text-align:left;color:#64748b"><span style="display:inline-block;width:10px;height:10px;background:#fee2e2;border:1px solid #fca5a5;margin-right:4px;border-radius:2px"></span> Busy (unavailable)</div>';
  cal.innerHTML=html; cal.querySelectorAll('[data-nav]').forEach(function(btn){ btn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); var dir=parseInt(this.getAttribute('data-nav')); draw(new Date(y,m+dir,1)); }); });
    cal.querySelectorAll('[data-date]').forEach(el=>{ var ds=el.getAttribute('data-date'); if(busy.indexOf(ds)>-1) return; if(ds<new Date().toISOString().slice(0,10)) return; el.onclick=function(){ openFor.value=ds; hide(); }; });
  }
  function showFor(input){ build(); openFor=input; var rect=input.getBoundingClientRect(); cal.style.left=(window.scrollX+rect.left)+'px'; cal.style.top=(window.scrollY+rect.bottom+4)+'px'; cal.style.display='block'; var startDate=input.value? new Date(input.value): new Date(); draw(new Date(startDate.getFullYear(), startDate.getMonth(),1)); }
  window.addEventListener('DOMContentLoaded', function(){
    inpEls=document.querySelectorAll('input[name=delivery_date]'); if(!inpEls.length) return; fetchBusy();
    inpEls.forEach(inp=>{
      inp.addEventListener('focus', function(){ showFor(inp); });
      inp.addEventListener('click', function(){ showFor(inp); });
    });
  });
  // If user types a busy date or uses native picker, prevent it
  window.addEventListener('DOMContentLoaded', function(){
    var els=document.querySelectorAll('input[name=delivery_date]'); els.forEach(function(inp){
      inp.addEventListener('change', function(){ if(busy.indexOf(this.value)>-1){ alert('Selected delivery date is unavailable. Please choose another available date.'); this.value=''; showFor(this);} });
    });
  });
})();
</script>
