<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/ui.php';
ensure_admin();

// Quick stats
$pendingOrders = (int)db()->query("SELECT COUNT(*) c FROM orders WHERE order_status='pending'")->fetch()['c'];
$pendingServices = 0; try { $pendingServices = (int)db()->query("SELECT COUNT(*) c FROM services WHERE status='Pending'")->fetch()['c']; } catch(Throwable $e) {}
// Pending messages: count distinct orders that have at least one message while order still pending
try { $pendingMsgs = (int)db()->query("SELECT COUNT(DISTINCT o.order_id) c FROM order_messages om JOIN orders o ON o.order_id=om.order_id WHERE o.order_status='pending'")->fetch()['c']; } catch(Throwable $e) { $pendingMsgs = 0; }
$products = (int)db()->query('SELECT COUNT(*) c FROM products')->fetch()['c'];
// Estimated Revenue depends only on orders marked as Done (delivered)
$revenue = (float)db()->query("SELECT IFNULL(SUM(total_price),0) t FROM orders WHERE order_status = 'delivered'")->fetch()['t'];

// Recent orders list
$recent = db()->query('SELECT order_id,customer_name,total_price,order_status,created_at,delivery_date FROM orders ORDER BY created_at DESC LIMIT 8')->fetchAll();

// Top customers (exclude any admin role) - by number of orders
$topCustomers = db()->query("SELECT u.id,u.name,u.email,COUNT(o.order_id) cnt,SUM(o.total_price) amt FROM users u JOIN orders o ON o.user_id=u.id WHERE (u.role IS NULL OR u.role<>'admin') GROUP BY u.id,u.name,u.email ORDER BY cnt DESC, amt DESC LIMIT 5")->fetchAll();

// Order overview aggregated by day (last 14 days) for chart (date, total sales, order count)
$overview = db()->query("SELECT DATE(created_at) d, SUM(total_price) sales, COUNT(*) orders FROM orders WHERE created_at >= DATE_SUB(CURDATE(),INTERVAL 13 DAY) GROUP BY DATE(created_at) ORDER BY d ASC")->fetchAll();
$ovMap = []; foreach($overview as $o){ $ovMap[$o['d']]=$o; }
$days=[]; for($i=13;$i>=0;$i--){ $d=date('Y-m-d',strtotime("-$i day")); $row=$ovMap[$d]??['d'=>$d,'sales'=>0,'orders'=>0]; $days[]=$row; }

admin_layout_start('Dashboard');
?>
<div class="card dash-card" style="background:linear-gradient(135deg,#04223f,#063866);padding:24px;display:flex;flex-direction:column;gap:20px">
  <div class="dash-head" style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start">
    <div class="dash-left" style="flex:1">
      <div class="dash-welcome" style="font-size:13px;color:#8fbbe2;font-weight:600">WELCOME BACK SIR, <?= h(current_user()['name'] ?? 'Admin'); ?></div>
  <div class="dash-subtitle" style="font-size:14px;margin-top:6px;color:#b5d4ec;letter-spacing:.5px">YOUR CONTROL CENTER RECENT ORDERS & INSIGHTS</div>
    </div>
    <div class="dash-right" style="text-align:right">
      <div style="font-size:11px;color:#8fbbe2;text-transform:uppercase">Estimated Revenue</div>
      <div id="revVal" style="font-size:30px;font-weight:800;margin-top:4px;letter-spacing:1px"><?= CURRENCY . number_format($revenue,2); ?></div>
    </div>
  </div>
  <?php $statStyle='background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);padding:18px 16px;border-radius:12px;min-height:90px;display:flex;flex-direction:column;justify-content:space-between'; ?>
  <div class="dash-stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px">
    <div style="<?= $statStyle ?>"><div style="font-size:11px;color:#8fbbe2;text-transform:uppercase">Pending Orders</div><div id="statPendingOrders" style="font-size:26px;font-weight:700;line-height:1;margin-top:4px"><?= $pendingOrders; ?></div></div>
    <div style="<?= $statStyle ?>"><div style="font-size:11px;color:#8fbbe2;text-transform:uppercase">Pending Services</div><div id="statPendingServices" style="font-size:26px;font-weight:700;margin-top:4px"><?= $pendingServices; ?></div></div>
    <div style="<?= $statStyle ?>"><div style="font-size:11px;color:#8fbbe2;text-transform:uppercase">Pending Messages</div><div id="statPendingMsgs" style="font-size:26px;font-weight:700;margin-top:4px"><?= $pendingMsgs; ?></div></div>
    <div style="<?= $statStyle ?>"><div style="font-size:11px;color:#8fbbe2;text-transform:uppercase">Total Products</div><div id="statProducts" style="font-size:26px;font-weight:700;margin-top:4px"><?= $products; ?></div></div>
  </div>
</div>

<div class="adm-two" style="display:grid;grid-template-columns:1fr 260px;gap:16px;margin-top:16px">
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card" style="padding:0;overflow:hidden">
      <div style="padding:14px 16px;font-weight:600;border-bottom:1px solid rgba(255,255,255,.08)">Recent Orders</div>
  <table class="table rtable" style="font-size:13px"><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Delivery</th><th>Status</th><th>Actions</th></tr></thead><tbody id="recentTbody">
      <?php if(!$recent): ?><tr><td colspan="6" style="padding:30px;text-align:center;color:#90a9c0">No recent orders</td></tr><?php endif; ?>
      <?php foreach($recent as $r): ?>
        <tr>
          <td data-label="Order">#<?= (int)$r['order_id']; ?></td>
          <td data-label="Customer"><?= h($r['customer_name']); ?></td>
          <td data-label="Total"><?= CURRENCY . number_format($r['total_price'],2); ?></td>
          <td data-label="Delivery"><?= h($r['delivery_date'] ?: '-'); ?></td>
          <td data-label="Status"><span class="badge"><?= h($r['order_status']); ?></span></td>
          <td data-label="Actions"><a class="btn" href="<?= BASE_URL ?>/admin/order_view.php?id=<?= (int)$r['order_id']; ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
    <div class="card" style="position:relative">
      <div style="font-weight:600;margin-bottom:6px">Orders Overview</div>
      <canvas id="ovChart" height="180" style="width:100%;max-height:260px"></canvas>
          <div style="display:flex;gap:16px;font-size:11px;margin-top:8px">
            <div style="display:flex;align-items:center;gap:4px"><span style="width:18px;height:10px;display:inline-block;border:2px solid #48c7ff;border-radius:2px;background:transparent"></span> Sales (PHP)</div>
            <div style="display:flex;align-items:center;gap:4px"><span style="width:18px;height:10px;display:inline-block;background:#1d84d4;border-radius:2px"></span> Orders</div>
          </div>
    </div>
  </div>
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card" style="min-height:340px">
      <div style="font-weight:600;margin-bottom:8px">Top Customers</div>
      <div id="topCustomers">
      <?php if(!$topCustomers): ?><div style="font-size:12px;color:#7ea7c6">No customers yet</div><?php endif; ?>
      <?php foreach($topCustomers as $c): ?>
        <div style="padding:8px 0;border-bottom:1px solid rgba(255,255,255,.08)">
          <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h(strtoupper($c['name'] ?? 'Customer')); ?></div>
          <div style="font-size:11px;color:#89abc6;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($c['email']); ?></div>
          <div style="font-size:11px;color:#4fb2ff;margin-top:2px">Orders: <?= (int)$c['cnt']; ?> • <?= CURRENCY . number_format($c['amt'],2); ?></div>
        </div>
      <?php endforeach; ?>
      </div>
      <div style="display:flex;justify-content:center;margin-top:14px">
        <a class="btn" style="min-width:140px;text-align:center" href="<?= BASE_URL ?>/admin/admin_users.php">View all users</a>
      </div>
    </div>
  </div>
</div>

<script>
// Orders Overview: dual y-axes (left = sales, right = order count) with tidy ticks.
(function(){
  var data = <?php echo json_encode($days); ?>;
  var cv=document.getElementById('ovChart'); if(!cv) return; var ctx=cv.getContext('2d');
  function roundUpNice(v){ var steps=[1000,2000,5000,10000,20000,25000,50000,100000]; for(var i=0;i<steps.length;i++){ if(v<=steps[i]) return steps[i]; } return Math.pow(10, Math.ceil(Math.log10(v))); }
  function draw(){
    var w=cv.width=cv.clientWidth*2, h=cv.height=cv.clientHeight*2; ctx.setTransform(1,0,0,1,0,0); ctx.scale(2,2); w/=2; h/=2; ctx.clearRect(0,0,w,h);
    var padBottom=34, padTop=10, left=60, right=w-48; // reserve right for orders axis
    var plotH = h - padBottom - padTop;
    var maxSales=0, maxOrders=0; data.forEach(r=>{ maxSales=Math.max(maxSales,r.sales); maxOrders=Math.max(maxOrders,r.orders); });
    // Sales axis (left)
    var targetTicks=5; var topSales = roundUpNice(maxSales||5000); if(topSales<5000) topSales=5000; var stepSales = topSales/targetTicks; // ensure even spacing
    // Order axis (right) integer ticks
    var topOrders = Math.max(1, maxOrders); var orderTicks=[]; for(var o=0;o<=topOrders;o++){ orderTicks.push(o); }
    // Draw horizontal grid lines & sales labels
    ctx.strokeStyle='rgba(255,255,255,.12)'; ctx.fillStyle='#7ea7c6'; ctx.font='11px system-ui'; ctx.textAlign='right';
    for(var sv=0; sv<=topSales+1; sv+=stepSales){ var y = padTop + (1 - sv/topSales)*plotH; ctx.beginPath(); ctx.moveTo(left,y); ctx.lineTo(right,y); ctx.stroke(); ctx.fillText('₱'+Math.round(sv).toLocaleString(), left-6, y+4); }
    // Right order axis labels
    ctx.textAlign='left'; ctx.fillStyle='#a8d4f7'; orderTicks.forEach(function(v){ var y = padTop + (1 - (v/topOrders))*plotH; ctx.fillText(v.toFixed(0), right+6, y+4); });
    // X labels
    var bw=(right-left)/data.length; ctx.textAlign='center'; ctx.fillStyle='#7ea7c6';
    data.forEach(function(r,i){ if(i%2) return; var x=left + bw*i + bw/2; var d=new Date(r.d+'T00:00:00'); ctx.fillText(d.toLocaleDateString(undefined,{month:'short',day:'numeric'}), x, h-14); });
    // Bars (orders)
    data.forEach(function(r,i){ var x=left + bw*i + 4; var barW = bw-8; var bh = (r.orders/topOrders)*plotH; ctx.fillStyle='#1d84d4'; ctx.fillRect(x, padTop + (plotH-bh), barW, bh); });
    // Sales smoothed line
    var pts = data.map(function(r,i){ var x=left + bw*i + bw/2; var y = padTop + (1 - (r.sales/topSales))*plotH; return {x:x,y:y}; });
    ctx.strokeStyle='#48c7ff'; ctx.lineWidth=2; ctx.beginPath();
    for(var i=0;i<pts.length;i++){
      if(i===0){ ctx.moveTo(pts[i].x, pts[i].y); }
      else {
        var prev=pts[i-1]; var midX=(prev.x+pts[i].x)/2; var midY=(prev.y+pts[i].y)/2; ctx.quadraticCurveTo(prev.x, prev.y, midX, midY);
        if(i===pts.length-1){ ctx.quadraticCurveTo(pts[i].x, pts[i].y, pts[i].x, pts[i].y); }
      }
    }
    ctx.stroke();
    // Points
    pts.forEach(function(p){ ctx.fillStyle='#48c7ff'; ctx.beginPath(); ctx.arc(p.x,p.y,3,0,Math.PI*2); ctx.fill(); });
  }
  draw(); window.addEventListener('resize', draw);

  // Live updates: poll admin/data.php every 8s
  function fmtCurrency(v){ try{ return (new Intl.NumberFormat(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})).format(v); }catch(e){ return Number(v).toFixed(2); } }
  function renderRecent(rows){
    var tb = document.getElementById('recentTbody'); if(!tb) return;
    if(!rows || rows.length===0){ tb.innerHTML = '<tr><td colspan="6" style="padding:30px;text-align:center;color:#90a9c0">No recent orders</td></tr>'; return; }
    tb.innerHTML = rows.map(function(r){
      var total = '<?= CURRENCY ?>' + fmtCurrency(r.total_price||0);
      var delv = r.delivery_date || '-';
      var st = (r.order_status||'').replace(/^./, c=>c.toUpperCase());
      return '<tr>'+
        '<td>#'+r.order_id+'</td>'+
        '<td>'+ (r.customer_name?escapeHtml(r.customer_name):'') +'</td>'+
        '<td>'+ total +'</td>'+
        '<td>'+ escapeHtml(delv) +'</td>'+
        '<td><span class="badge">'+ escapeHtml(st) +'</span></td>'+
        '<td><a class="btn" href="<?= BASE_URL ?>/admin/order_view.php?id='+r.order_id+'">View</a></td>'+
      '</tr>';
    }).join('');
  }
  function renderTopCustomers(rows){
    var el = document.getElementById('topCustomers'); if(!el) return;
    if(!rows || rows.length===0){ el.innerHTML = '<div style="font-size:12px;color:#7ea7c6">No customers yet</div>'; return; }
    el.innerHTML = rows.map(function(c){
      var name = (c.name||'Customer').toString().toUpperCase();
      var amt = '<?= CURRENCY ?>' + fmtCurrency(c.amt||0);
      return '<div style="padding:8px 0;border-bottom:1px solid rgba(255,255,255,.08)">' +
        '<div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+escapeHtml(name)+'</div>'+
        '<div style="font-size:11px;color:#89abc6;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+escapeHtml(c.email||'')+'</div>'+
        '<div style="font-size:11px;color:#4fb2ff;margin-top:2px">Orders: '+(c.cnt||0)+' • '+amt+'</div>'+
      '</div>';
    }).join('');
  }
  function escapeHtml(s){ if(s==null) return ''; return String(s).replace(/[&<>"']/g,function(c){return ({"&":"&amp;","<":"&lt;",
      ">":"&gt;","\"":"&quot;","'":"&#39;"})[c];}); }
  
  function updateDash(){
    fetch('<?= BASE_URL ?>/admin/data.php', {cache:'no-store'}).then(r=>r.json()).then(function(j){
      if(j.counts){
        var c=j.counts; var el;
        if(el=document.getElementById('statPendingOrders')) el.textContent = c.pendingOrders;
        if(el=document.getElementById('statPendingServices')) el.textContent = c.pendingServices;
        if(el=document.getElementById('statPendingMsgs')) el.textContent = c.pendingMsgs;
        if(el=document.getElementById('statProducts')) el.textContent = c.products;
        if(el=document.getElementById('revVal')) el.textContent = '<?= CURRENCY ?>' + fmtCurrency(c.revenue||0);
      }
      if(Array.isArray(j.recent)) renderRecent(j.recent);
      if(Array.isArray(j.topCustomers)) renderTopCustomers(j.topCustomers);
      if(Array.isArray(j.overview)) { data = j.overview; draw(); }
    }).catch(function(){});
  }
  setInterval(updateDash, 8000);
  // Gentle initial refresh after 1s for perceived responsiveness
  setTimeout(updateDash, 1000);
})();
</script>
<?php admin_layout_end(); ?>
<style>
/* Dashboard-only responsive tweaks */
@media (max-width: 980px){
  .adm-two{ grid-template-columns:1fr !important }
  .dash-card{ padding:16px !important; gap:12px !important }
  .dash-head{ gap:12px !important }
  .dash-left{ min-width:0 !important }
  .dash-welcome{ font-size:12px !important }
  .dash-subtitle{ font-size:12px !important; letter-spacing:.2px !important }
  .dash-right #revVal{ font-size:22px !important; letter-spacing:.5px !important }
  .dash-stats-grid{ grid-template-columns:repeat(auto-fit,minmax(140px,1fr)) !important; gap:10px !important }
}
@media (max-width: 640px){
  .dash-card{ padding:12px !important }
  .dash-right #revVal{ font-size:20px !important }
}
</style>
