<?php require_once __DIR__ . '/functions.php'; ?>
<style>
  /* Scoped header typography bump */
  .site-header { --hdr-base: clamp(15px, 1.2vw + 10px, 18px); font-size: var(--hdr-base); }
  .site-header .brand span { font-size: calc(var(--hdr-base) + 6px); line-height: 1.1; }
  .site-header .nav a,
  .site-header .user-toggle,
  .site-header .chat-icon { font-size: calc(var(--hdr-base) + 1px); }
  .site-header .menu a { font-size: calc(var(--hdr-base) - 1px); }
  .site-header .subbar .subitem { font-size: calc(var(--hdr-base) - 1px); }
  /* Ensure icon button stays vertically centered with larger font */
  .site-header .chat-icon { line-height: 1; }
  /* Prevent wrap jitter if nav grows slightly */
  .site-header .nav { white-space: nowrap; }
  /* Make header containers span full width so brand sits at the far left */
  .site-header > .container,
  .site-header .subbar > .container { max-width: 90%; }
  /* Responsive logo sizing */
  .site-header .logo{ height:64px }
  @media (max-width: 980px){ .site-header .logo{ height:54px } }
  @media (max-width: 520px){ .site-header .logo{ height:46px } }
  @media (max-width: 520px) {
    /* Slightly tighten on very small screens */
    .site-header { --hdr-base: clamp(14px, 3.2vw + 6px, 17px); }
  }
</style>
<header class="site-header">
  <div class="container flex between center">
    <a href="<?= BASE_URL ?>/index.php" class="brand">
      <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Logo" class="logo" />
      <span style="text-shadow:0 1px 1px rgba(0,0,0,.35)">Fpb Network and Power Solutions Services </span>
    </a>
    <button class="nav-toggle" aria-label="Toggle menu" aria-expanded="false">☰</button>
    <nav class="nav">
      <a href="<?= BASE_URL ?>/index.php">Home</a>
      <a href="<?= BASE_URL ?>/service.php">Service</a>
  <?php
    $cartItems = [];
    $cartQty = 0;
    if (is_logged_in()) {
      $cartItems = get_cart();
      $cartQty = cart_totals($cartItems)['qty'];
    }
  ?>
  <div class="cart-nav" style="position:relative;display:inline-block">
    <a href="<?= BASE_URL ?>/cart.php" class="cart-link" style="position:relative;display:inline-flex;align-items:center;gap:6px">Cart <span class="cart-badge" style="display:<?= $cartQty>0?'inline-flex':'none' ?>;background:#ff6b00;color:#fff;font-size:11px;font-weight:700;min-width:20px;height:20px;align-items:center;justify-content:center;border-radius:999px;padding:0 6px;line-height:1;box-shadow:0 2px 6px rgba(0,0,0,.2)"><?= (int)$cartQty; ?></span></a>
    <div class="cart-preview" style="display:none;position:absolute;right:0;top:100%;margin-top:10px;width:320px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px -4px rgba(15,23,42,.15);padding:14px;font-size:13px;color:#475569">
      <?php if ($cartQty===0): ?>
        <div style="text-align:center">
          <div style="width:58px;height:58px;margin:0 auto 8px;background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:26px;color:#0ea5e9">🛒</div>
          <div style="font-weight:600;color:#0f172a">Your cart is empty</div>
          <div style="margin-top:6px"><a href="<?= BASE_URL ?>/index.php#home-products" style="color:#0369a1;text-decoration:none;font-weight:600">Browse products →</a></div>
        </div>
      <?php else: ?>
        <div style="font-weight:800;color:#0f172a;margin:2px 2px 8px">Recently Added Products</div>
        <div>
          <?php $list = array_slice($cartItems, 0, 5); foreach ($list as $ci): ?>
            <div style="display:grid;grid-template-columns:46px 1fr auto;gap:10px;align-items:center;padding:6px 4px;border-radius:8px">
              <img src="<?= h(product_image_url($ci['image'])); ?>" alt="" style="width:46px;height:46px;object-fit:contain;background:#fff;border:1px solid #e5e7eb;border-radius:8px" />
              <div style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;color:#0f172a;font-weight:600;"><?= h($ci['name']); ?></div>
              <div style="text-align:right;color:#0f172a;font-weight:700;"><?= CURRENCY . number_format($ci['price'], 2); ?></div>
            </div>
          <?php endforeach; ?>
          <?php $more = max(0, count($cartItems) - count($list)); if ($more > 0): ?>
            <div style="font-size:12px;color:#334155;margin:4px 2px">+ <?= (int)$more; ?> more in cart</div>
          <?php endif; ?>
        </div>
        <div style="margin-top:10px;text-align:right"><a href="<?= BASE_URL ?>/cart.php" class="btn" style="text-decoration:none">View My Shopping Cart</a></div>
      <?php endif; ?>
    </div>
  </div>
      <?php if (is_logged_in()): ?>
        <div class="user-menu">
          <button class="user-toggle" aria-haspopup="true" aria-expanded="false">Account ▾</button>
          <div class="menu" role="menu">
            <a href="<?= BASE_URL ?>/account.php" role="menuitem">My Account</a>
            <a href="<?= BASE_URL ?>/orders.php" role="menuitem">My Orders</a>
          </div>
        </div>
        <?php if ((current_user()['role'] ?? '') === 'admin'): ?>
          <a href="<?= BASE_URL ?>/admin/index.php">Admin</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/logout.php">Logout</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/signin.php">Sign in</a>
        <a href="<?= BASE_URL ?>/register.php">Register</a>
      <?php endif; ?>
      <?php $isProductPage = basename($_SERVER['SCRIPT_NAME']) === 'product.php'; ?>
      <?php if (!$isProductPage): ?>
        <button class="chat-icon" type="button" title="Chat">✉</button>
      <?php endif; ?>
    </nav>
  </div>
  <div class="subbar">
    <div class="container flex between center" style="gap:12px">
      <div class="subitem">Cash on Delivery available</div>
  <div class="subitem">Promo: 4 items → 5% OFF • 5+ items → FREE Shipping</div>
      <div class="subitem">Support: Mon–Fri</div>
    </div>
  </div>
</header>
<script>
(function(){
  var nav=document.querySelector('.cart-nav'); if(!nav) return; var preview=nav.querySelector('.cart-preview'); if(!preview) return;
  function show(){ preview.style.display='block'; }
  function hide(){ preview.style.display='none'; }
  nav.addEventListener('mouseenter',show); nav.addEventListener('mouseleave',hide);
})();
// Improve Account menu hover: add a tiny intent delay to prevent flicker
(function(){
  var um = document.querySelector('.nav .user-menu'); if(!um) return;
  var menu = um.querySelector('.menu'); var btn = um.querySelector('.user-toggle'); if(!menu||!btn) return;
  var hideT, showT;
  function show(){ clearTimeout(hideT); showT = setTimeout(function(){ menu.style.display='block'; btn.setAttribute('aria-expanded','true'); }, 80); }
  function hide(){ clearTimeout(showT); hideT = setTimeout(function(){ menu.style.display='none'; btn.setAttribute('aria-expanded','false'); }, 120); }
  um.addEventListener('mouseenter', show);
  um.addEventListener('mouseleave', hide);
  // Also keep open on focus within (keyboard navigation)
  um.addEventListener('focusin', show);
  um.addEventListener('focusout', function(e){ if(!um.contains(e.relatedTarget)) hide(); });
})();
// Mobile menu toggle with click; also collapse when clicking a link
(function(){
  var btn=document.querySelector('.nav-toggle'); var nav=document.querySelector('.site-header .nav'); if(!btn||!nav) return;
  function toggle(){ var open=nav.classList.toggle('open'); btn.setAttribute('aria-expanded', open?'true':'false'); }
  btn.addEventListener('click', toggle);
  nav.addEventListener('click', function(e){ if(e.target.tagName==='A'){ nav.classList.remove('open'); btn.setAttribute('aria-expanded','false'); } });
})();
// Enable tap to open account menu on touch devices
(function(){
  var btn=document.querySelector('.nav .user-toggle'); var menu=document.querySelector('.nav .user-menu .menu'); if(!btn||!menu) return;
  btn.addEventListener('click', function(e){ e.preventDefault(); var shown=menu.style.display==='block'; menu.style.display = shown?'none':'block'; this.setAttribute('aria-expanded', shown?'false':'true'); });
  document.addEventListener('click', function(e){ if(!menu.contains(e.target)&&e.target!==btn){ menu.style.display='none'; btn.setAttribute('aria-expanded','false'); } });
})();
// Fixed header on small devices with hide-on-scroll behavior
(function(){
  var hdr=document.querySelector('.site-header'); if(!hdr) return;
  function applyPadding(){ var h=hdr.offsetHeight||58; document.body.style.setProperty('--hdrH', h+'px'); document.body.classList.add('has-fixed-header'); }
  var lastY=window.scrollY||0, ticking=false;
  function onScroll(){ if(window.matchMedia('(max-width: 980px)').matches){
      var y=window.scrollY||0, dir=y>lastY?'down':'up'; lastY=y;
      if(!ticking){ window.requestAnimationFrame(function(){ hdr.classList.toggle('hide', dir==='down' && y>10); document.body.classList.toggle('header-hidden', hdr.classList.contains('hide')); ticking=false; }); ticking=true; }
    } else { hdr.classList.remove('hide'); document.body.classList.remove('header-hidden'); document.body.classList.remove('has-fixed-header'); document.body.style.removeProperty('--hdrH'); }
  }
  applyPadding();
  window.addEventListener('resize', applyPadding, {passive:true});
  window.addEventListener('scroll', onScroll, {passive:true});
  window.addEventListener('load', applyPadding);
  // Observe header size changes (e.g., when subbar hides on small screens)
  if('ResizeObserver' in window){ try{ var ro=new ResizeObserver(applyPadding); ro.observe(hdr); }catch(e){} }
  // Recompute after mobile menu toggle changes height
  var toggle=document.querySelector('.nav-toggle'); if(toggle){ toggle.addEventListener('click', function(){ setTimeout(applyPadding, 50); }); }
})();
</script>
  <script src="<?= BASE_URL ?>/assets/js/script.js?v=20250923"></script>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/chatbot.css?v=20250923">
  <script src="<?= BASE_URL ?>/assets/js/chatbot.js?v=20250927"></script>
