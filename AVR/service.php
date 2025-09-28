<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Service';
$msg = $_SESSION['flash_msg'] ?? null; unset($_SESSION['flash_msg']);
$user = current_user();

// Lightweight JSON for current user's services statuses
if (isset($_GET['json']) && $_GET['json'] === 'my') {
  header('Content-Type: application/json');
  if (!is_logged_in() || empty($user['email'])) { echo json_encode(['services'=>[]]); exit; }
  try {
    $stmt = db()->prepare('SELECT id, status FROM services WHERE email = :e ORDER BY created_at DESC');
    $stmt->execute([':e'=>$user['email']]);
    $rows = $stmt->fetchAll();
    echo json_encode(['services'=>$rows]);
  } catch (Throwable $e) {
    echo json_encode(['services'=>[]]);
  }
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
  $date = $_POST['date'] ?? null;
  try {
    // Server-side guard: block if date conflicts with confirmed orders/services (4-day span)
    $q1 = db()->prepare("SELECT 1 FROM orders WHERE order_status IN ('confirmed','shipped') AND delivery_date IS NOT NULL AND :d BETWEEN delivery_date AND DATE_ADD(delivery_date, INTERVAL 3 DAY) LIMIT 1");
    $q1->execute([':d'=>$date]);
    $q2 = db()->prepare("SELECT 1 FROM services WHERE status='Confirmed' AND preferred_date IS NOT NULL AND :d BETWEEN preferred_date AND DATE_ADD(preferred_date, INTERVAL 3 DAY) LIMIT 1");
    $q2->execute([':d'=>$date]);
    if ($q1->fetch() || $q2->fetch()) {
      $msg = 'Selected service date is unavailable. Please choose another available date.';
    } else {
      $stmt = db()->prepare('INSERT INTO services (customer_name,email,phone,service_type,description,preferred_date,status) VALUES (:n,:e,:p,:t,:d,:date,\'Pending\')');
      $stmt->execute([
        ':n'=>trim($user['name'] ?? ''), ':e'=>trim($user['email'] ?? ''), ':p'=>trim($user['phone'] ?? ''),
        ':t'=>trim($_POST['type'] ?? ''), ':d'=>trim($_POST['desc'] ?? ''), ':date'=>$date
      ]);
      $_SESSION['flash_msg'] = 'Service request submitted (Status: Pending).';
      header('Location: ' . BASE_URL . '/service.php');
      exit;
    }
  } catch (Throwable $e) {
    $_SESSION['flash_msg'] = 'Error: ' . $e->getMessage();
    header('Location: ' . BASE_URL . '/service.php');
    exit;
  }
}

// Total service requests (global)
try { $service_total = (int)db()->query('SELECT COUNT(*) c FROM services')->fetch()['c']; } catch(Throwable $e){ $service_total = 0; }

// Fetch current user's service requests (by email) if logged in
$my_services = [];
if (is_logged_in() && !empty($user['email'])) {
  try {
    $stmt = db()->prepare('SELECT id, service_type, description, preferred_date, status, created_at FROM services WHERE email = :e ORDER BY created_at DESC');
    $stmt->execute([':e'=>$user['email']]);
    $my_services = $stmt->fetchAll();
  } catch (Throwable $e) { $my_services = []; }
}

include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <h2>Request Service</h2>
  <p style="margin-top:4px;font-size:13px;color:#475569">Total service requests submitted: <strong><?= (int)$service_total; ?></strong></p>
    <?php if ($msg): ?><div class="form" style="background:#ecfeff;border-color:#a5f3fc;"><?= h($msg); ?></div><?php endif; ?>

    <?php if (!is_logged_in()): ?>
      <div style="display:flex;justify-content:center;align-items:center">
        <div class="form" style="max-width:760px;margin:20px auto;padding:26px;text-align:center">
          <div style="width:110px;height:110px;margin:0 auto 10px;border-radius:50%;background:linear-gradient(135deg,#f8fafc,#eef2f7);display:flex;align-items:center;justify-content:center;color:#0f172a;font-size:64px;line-height:1">☹</div>
          <h3 style="margin:4px 0 6px;color:#0f172a">Sign in required</h3>
          <p style="margin:0 0 14px;color:#475569">You need to log in or register an account to request a service. We’ll use your profile’s name, email, and phone.</p>
          <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <a class="btn" style="min-width:140px" href="<?= BASE_URL ?>/signin.php">Sign in</a>
            <a class="btn" style="min-width:140px" href="<?= BASE_URL ?>/register.php">Register</a>
          </div>
        </div>
      </div>
    <?php else: ?>
      <form class="form" method="post">
        <div class="row">
          <div><label>Service Type<br><input name="type" placeholder="Installation, Repair, etc." required></label></div>
          <div><label>Preferred Date<br><input type="text" name="date" placeholder="YYYY-MM-DD" value="<?= h(date('Y-m-d', strtotime('+5 days'))); ?>" required readonly></label></div>
          <div style="grid-column:1/3"><label>Description<br><textarea name="desc" rows="3" placeholder="Describe the issue or request..."></textarea></label></div>
        </div>
        <div style="margin-top:12px"><button class="btn">Submit</button></div>
      </form>
      <?php if ($my_services): ?>
        <div class="form" style="margin-top:18px">
          <h3 style="margin-top:0">My Service Requests</h3>
          <div style="overflow:auto">
            <table class="table" style="min-width:640px;font-size:14px">
              <thead><tr><th style="width:140px">Service Type</th><th>Description</th><th style="width:120px">Preferred Date</th><th style="width:100px">Status</th></tr></thead>
              <tbody>
              <?php foreach ($my_services as $sv): ?>
                <tr>
                  <td><?= h($sv['service_type']); ?></td>
                  <td style="max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= h($sv['description']); ?>"><?= h($sv['description']); ?></td>
                  <td><?= h($sv['preferred_date']); ?></td>
                  <td><span class="badge svc-status" data-svc-id="<?= (int)$sv['id']; ?>" style="background:#e0ecfb;color:#0b4f8f"><?= h($sv['status']); ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
// Reuse busy date calendar logic (same as checkout) for service preferred date
(function(){
  var busy=[], cal, openFor=null, input=document.querySelector('input[name=date]'); if(!input) return;
  function fetchBusy(){ fetch('<?= BASE_URL ?>/busy_dates.php').then(r=>r.json()).then(j=>{busy=j.busy||[];}); }
  function build(){ if(cal) return; cal=document.createElement('div'); cal.style.cssText='position:absolute;z-index:60;background:#fff;border:1px solid #cbd5e1;border-radius:6px;padding:6px;width:250px;font:12px system-ui,Arial;box-shadow:0 4px 18px rgba(0,0,0,.08);display:none'; document.body.appendChild(cal); document.addEventListener('click',e=>{ if(!cal.contains(e.target)&&e.target!==input) hide(); }); }
  function hide(){ cal.style.display='none'; }
  function draw(date){ var y=date.getFullYear(),m=date.getMonth(), first=new Date(y,m,1), start=first.getDay(), days=new Date(y,m+1,0).getDate(); var html='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">'+"<button type=button data-nav='-1' style='background:#e2e8f0;border:0;width:26px;height:26px;border-radius:4px'>‹</button>"+'<strong style="font-size:13px">'+first.toLocaleString(undefined,{month:'long'})+' '+y+'</strong>'+"<button type=button data-nav='1' style='background:#e2e8f0;border:0;width:26px;height:26px;border-radius:4px'>›</button></div><table style='width:100%;border-collapse:collapse;font-size:11px;text-align:center'><thead><tr>"+['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(d=>'<th style=\"padding:2px 0\">'+d+'</th>').join('')+'</tr></thead><tbody>'; var d=1; for(var w=0; w<6 && d<=days; w++){ html+='<tr>'; for(var i=0;i<7;i++){ if(w===0&&i<start||d>days){ html+='<td></td>'; } else { var ds=y+'-'+String(m+1).padStart(2,'0')+'-'+String(d).padStart(2,'0'); var isBusy=busy.indexOf(ds)>-1; var past=ds<new Date().toISOString().slice(0,10); html+='<td style="padding:3px"><div data-date="'+ds+'" style="padding:4px 0;border-radius:4px;'+(isBusy?'background:#fee2e2;color:#991b1b':'background:#f1f5f9;cursor:pointer')+'">'+d+'</div></td>'; d++; } } html+='</tr>'; } html+='</tbody></table>'; cal.innerHTML=html; cal.querySelectorAll('[data-nav]').forEach(function(b){ b.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); var dir=parseInt(this.getAttribute('data-nav')); draw(new Date(y,m+dir,1)); }); }); cal.querySelectorAll('[data-date]').forEach(el=>{ var ds=el.getAttribute('data-date'); if(busy.indexOf(ds)>-1||ds<new Date().toISOString().slice(0,10)) return; el.onclick=function(){ input.value=ds; hide(); }; }); }
  // Also guard if user types a blocked date
  document.addEventListener('DOMContentLoaded', function(){ input.addEventListener('change', function(){ if(busy.indexOf(this.value)>-1){ alert('Selected service date is unavailable. Please choose another available date.'); this.value=''; show(); } }); });
  function show(){ build(); openFor=input; var r=input.getBoundingClientRect(); cal.style.left=(window.scrollX+r.left)+'px'; cal.style.top=(window.scrollY+r.bottom+4)+'px'; cal.style.display='block'; var base=input.value? new Date(input.value): new Date(); draw(new Date(base.getFullYear(), base.getMonth(),1)); }
  fetchBusy(); input.addEventListener('focus',show); input.addEventListener('click',show);
})();
// Live update my service statuses without reload
(function(){
  var hasList = document.querySelector('.svc-status'); if(!hasList) return;
  function tick(){
    fetch('<?= BASE_URL ?>/service.php?json=my',{cache:'no-store'}).then(r=>r.json()).then(function(j){
      if(!j || !Array.isArray(j.services)) return;
      j.services.forEach(function(s){
        var el = document.querySelector('.svc-status[data-svc-id="'+s.id+'"]'); if(el){ el.textContent = s.status; }
      });
    }).catch(function(){});
  }
  setInterval(tick, 9000); setTimeout(tick, 1800);
})();
</script>
