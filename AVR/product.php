<?php
require_once __DIR__ . '/includes/functions.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = $id ? get_product($id) : null;
if (!$product) { header('Location: ' . BASE_URL . '/products.php'); exit; }
$page_title = $product['name'];
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container grid" style="grid-template-columns: 1fr 1fr; gap:24px;">
    <div>
      <?php $imgs = get_product_images($product); $main = $imgs[0] ?? product_image_url($product['image']); ?>
  <img id="main-img" src="<?= h($main); ?>" alt="<?= h($product['name']); ?>" style="width:100%; max-height:520px; border-radius:10px; background:#fff; object-fit:contain" />
      <?php if (count($imgs) > 1): ?>
        <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
          <?php foreach ($imgs as $u): ?>
            <img src="<?= h($u); ?>" alt="thumb" style="width:68px;height:68px;object-fit:contain;border:1px solid #e5e7eb;border-radius:8px;cursor:pointer;background:#fff" onclick="document.getElementById('main-img').src='<?= h($u); ?>'" />
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div>
      <h1><?= h($product['name']); ?></h1>
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:6px;flex-wrap:wrap">
        <div class="price" style="font-size:26px;letter-spacing:.5px;"><?= CURRENCY . number_format($product['price'], 2); ?></div>
        <div id="qtyBox" style="display:flex;align-items:center;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.06)">
          <button type="button" id="qMinus" style="background:#f1f5f9;border:0;width:40px;height:44px;font-size:20px;cursor:pointer;color:#0f172a;font-weight:600">−</button>
          <input id="qInput" type="number" min="1" value="1" style="width:70px;text-align:center;font-size:18px;border:0;height:44px;outline:none;color:#0f172a;font-weight:600" />
          <button type="button" id="qPlus" style="background:#f1f5f9;border:0;width:40px;height:44px;font-size:20px;cursor:pointer;color:#0f172a;font-weight:600">+</button>
        </div>
        <div id="subtotalChip" style="background:linear-gradient(135deg,#024787,#0475d2);color:#fff;padding:8px 14px;border-radius:999px;font-size:14px;font-weight:600;box-shadow:0 4px 12px rgba(4,117,210,.25)">Subtotal: <span id="subVal"><?= CURRENCY . number_format($product['price'],2); ?></span></div>
      </div>
      <?php $shipDate = date('F j, Y'); ?>
      <div id="promoBox" style="margin:12px 0 10px;background:#f1f5f9;border-radius:14px;padding:14px 16px;color:#0f172a;position:relative;transition:.3s">
        <div style="font-weight:600;display:flex;align-items:center;gap:6px"><span style="display:inline-block;width:8px;height:8px;background:#0475d2;border-radius:50%"></span> Shipping & Promos</div>
        <div style="margin-top:6px;font-size:14px;line-height:1.5">
          <div id="promoDiscount" style="display:flex;align-items:center;gap:6px"><span class="dot" style="width:6px;height:6px;background:#64748b;border-radius:50%"></span> Buy <strong>exactly 4</strong> items: <span class="promotext">5% off</span></div>
          <div id="promoShip" style="display:flex;align-items:center;gap:6px;margin-top:4px"><span class="dot" style="width:6px;height:6px;background:#64748b;border-radius:50%"></span> Buy <strong>5+</strong> items: <span class="promotext">Free Shipping (no 5% off)</span></div>
          <div style="margin-top:6px;color:#475569">Estimated ship date (<?= h($shipDate); ?>) + up to 7 days.</div>
        </div>
        <div id="promoGlow" style="position:absolute;inset:0;border:2px solid transparent;border-radius:14px;pointer-events:none"></div>
      </div>
      <div style="white-space:pre-line; font-size:17px; line-height:1.6; color:#0f172a;">
        <?= nl2br(h($product['specification'] ?? '')); ?>
      </div>
      <?php $inStock = (int)$product['stock'] > 0; ?>
      <div style="margin:12px 0;">Stock: <span class="badge"><?= (int)$product['stock']; ?></span></div>
      <div style="display:flex; gap:10px;flex-wrap:wrap">
        <button class="btn" id="addBtn" data-pid="<?= (int)$product['id']; ?>" <?= $inStock? '' : 'disabled style="opacity:.6;cursor:not-allowed"' ?>>Add to Cart</button>
        <a id="orderNow" class="btn order" style="text-decoration:none<?= $inStock? '' : ';pointer-events:none;opacity:.6;cursor:not-allowed' ?>" href="<?= $inStock? (is_logged_in() ? (BASE_URL . '/checkout.php?pid=' . (int)$product['id'] . '&qty=1') : '#') : '#' ?>" data-login-required="<?= is_logged_in()? '0':'1' ?>">Order Now</a>
      </div>
    </div>
  </div>
</section>
<style>@media(max-width:960px){ .container.grid[style] { grid-template-columns:1fr !important } }</style>
<!-- Auth required overlay -->
<div id="authGate" style="position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;z-index:1000">
  <div role="dialog" aria-modal="true" aria-labelledby="authTitle" style="position:relative;background:#ffffff;border-radius:18px;max-width:560px;width:92%;padding:22px 22px 20px;box-shadow:0 24px 48px -12px rgba(15,23,42,.45);text-align:center;border:1px solid #e2e8f0">
    <button type="button" aria-label="Close" onclick="AuthGate.close()" style="position:absolute;top:10px;right:12px;background:#f1f5f9;border:1px solid #e2e8f0;width:30px;height:30px;border-radius:8px;cursor:pointer;color:#0f172a">×</button>
    <div style="width:68px;height:68px;margin:6px auto 8px;background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:34px;color:#0ea5e9">☹️</div>
    <h3 id="authTitle" style="margin:6px 0 6px;font-size:20px;color:#0f172a">Sign in required</h3>
    <div style="color:#475569;font-size:14px;line-height:1.6;margin:0 auto 6px;max-width:460px">
      You need to log in or register an account to order <strong><?= h($product['name']); ?></strong>. We'll use your profile's name, email, and phone.
    </div>
    <div style="display:flex;gap:10px;justify-content:center;margin-top:10px">
      <a class="btn" href="<?= BASE_URL ?>/signin.php?redirect=<?= urlencode(BASE_URL . '/product.php?id=' . (int)$product['id']) ?>">Sign in</a>
      <a class="btn secondary" href="<?= BASE_URL ?>/register.php?redirect=<?= urlencode(BASE_URL . '/product.php?id=' . (int)$product['id']) ?>">Register</a>
    </div>
  </div>
  
</div>
<script>
  (function(){
    var LOGGED_IN = <?= is_logged_in() ? 'true' : 'false' ?>;
    var price = <?= json_encode((float)$product['price']); ?>;
    var pid = <?= (int)$product['id']; ?>;
    var qInput = document.getElementById('qInput');
    var subVal = document.getElementById('subVal');
    var orderNow = document.getElementById('orderNow');
    var addBtn = document.getElementById('addBtn');
    var promoBox = document.getElementById('promoBox');
    function fmt(n){ return '<?= CURRENCY; ?>' + Number(n).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
    function update(){
      var q = Math.max(1, parseInt(qInput.value||'1')); qInput.value = q;
      subVal.textContent = fmt(price * q);
      if(LOGGED_IN){ orderNow.href = '<?= BASE_URL ?>/checkout.php?pid='+pid+'&qty='+q; }
      else { orderNow.href = '#'; }
      // Promos visual activation
      var disc = document.getElementById('promoDiscount');
      var ship = document.getElementById('promoShip');
      var glow = document.getElementById('promoGlow');
      function activate(el){ el.style.background='#ecfdf5'; el.style.borderRadius='10px'; el.querySelector('.dot').style.background='#059669'; el.querySelector('.promotext').style.color='#047857'; }
      function reset(el){ el.style.background='transparent'; el.querySelector('.dot').style.background='#64748b'; el.querySelector('.promotext').style.color='inherit'; }
      (q===4?activate:reset)(disc); (q>=5?activate:reset)(ship);
  if(q>=5){ glow.style.borderColor='#10b981'; glow.style.boxShadow='0 0 0 3px rgba(16,185,129,.35)'; }
  else if(q===4){ glow.style.borderColor='#34d399'; glow.style.boxShadow='0 0 0 3px rgba(52,211,153,.25)'; }
      else { glow.style.borderColor='transparent'; glow.style.boxShadow='none'; }
    }
    document.getElementById('qPlus').onclick=function(){ qInput.value = parseInt(qInput.value||'1') + 1; update(); };
    document.getElementById('qMinus').onclick=function(){ qInput.value = Math.max(1, parseInt(qInput.value||'1') - 1); update(); };
    qInput.addEventListener('input', update);
    if(addBtn && !addBtn.disabled){ addBtn.addEventListener('click', function(e){ e.preventDefault(); var q = parseInt(qInput.value||'1'); addToCart(pid, q); }); }
    // Login gate: intercept Order Now when not logged in
    if(orderNow && !LOGGED_IN){ orderNow.addEventListener('click', function(e){ e.preventDefault(); AuthGate.open(); }); }
    update();
  })();
  // Simple modal controller
  var AuthGate = (function(){
    var el = null; function get(){ if(!el) el = document.getElementById('authGate'); return el; }
    function open(){ var m=get(); if(!m) return; m.style.display='flex'; document.addEventListener('keydown', esc); m.addEventListener('click', backdrop); }
    function close(){ var m=get(); if(!m) return; m.style.display='none'; document.removeEventListener('keydown', esc); m.removeEventListener('click', backdrop); }
    function esc(e){ if(e.key==='Escape') close(); }
    function backdrop(e){ var card = e.currentTarget.querySelector('[role=dialog]'); if(card && !card.contains(e.target)) close(); }
    return { open: open, close: close };
  })();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
