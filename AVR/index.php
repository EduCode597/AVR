<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Home';
// Fetch products including specification; we'll show first 15 in a 3x5 grid
$products = db()->query('SELECT id, sku, name, price, short_description, specification, image, stock, type FROM products ORDER BY created_at DESC')->fetchAll();
// Lightweight public JSON for live refresh (first 15 active products)
if (isset($_GET['json']) && $_GET['json'] === 'products') {
  header('Content-Type: application/json');
  $rows = db()->query('SELECT id, name, price, image, stock, type FROM products WHERE is_active=1 ORDER BY created_at DESC')->fetchAll();
  $out = [];
  foreach ($rows as $r) {
    $imgs = get_product_images($r); if(!$imgs){ $imgs=[product_image_url($r['image']??'')]; }
    $out[] = [
      'id'=>(int)$r['id'], 'name'=>$r['name'], 'price'=>(float)$r['price'], 'stock'=>(int)$r['stock'], 'type'=>$r['type'], 'images'=>$imgs
    ];
    if (count($out) >= 15) break;
  }
  echo json_encode(['products'=>$out]);
  exit;
}
include __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container">
    <h1>Automatic Voltage Regulators, UPS and TVSS</h1>
    <p>Quality power products. Cash on Delivery (COD) available.</p>
  </div>
</section>

<section class="section">
  <div class="container wide">
    <div class="ads" id="ads">
      <div class="slides">
        <img class="adimg active" id="adimgA" src="<?= BASE_URL ?>/assets/images/bg/1.jpg" alt="Ad slide 1">
        <img class="adimg" id="adimgB" src="<?= BASE_URL ?>/assets/images/bg/2.jpg" alt="Ad slide 2" aria-hidden="true">
      </div>
      <div class="cta">
        <a class="shop-btn" href="#home-products" aria-label="Shop products now">Shop Now</a>
      </div>
      <div class="nav">
        <button type="button" onclick="adsPrev()">‹</button>
        <button type="button" onclick="adsNext()">›</button>
      </div>
    </div>
    <h2>Latest Products</h2>
    <div class="grid products home-grid" id="home-products">
      <?php $count=0; foreach ($products as $p): if ($count++>=15) break; ?>
        <article class="card" data-id="<?= (int)$p['id']; ?>">
          <a href="<?= BASE_URL ?>/product.php?id=<?= (int)$p['id']; ?>" aria-label="View <?= h($p['name']); ?>">
            <img src="<?= h(product_image_url($p['image'])); ?>" alt="<?= h($p['name']); ?>" />
          </a>
          <div class="p">
          <h3 style="margin:.25rem 0 0; font-size:16px; text-align:center;"><?= h($p['name']); ?></h3>
            <div class="price"><?= CURRENCY . number_format($p['price'], 2); ?></div>
            <?php
              $spec = trim((string)($p['specification'] ?? $p['short_description'] ?? ''));
              $sold = product_sold_count((int)$p['id']);
            ?>
            <div class="details" style="white-space:pre-line;">
              <?php if ($spec !== ''): ?>
                <?= nl2br(h($spec)); ?>
              <?php endif; ?>
              <div style="margin-top:6px;display:flex;gap:10px;flex-wrap:wrap;color:#334155">
                <span>Stock: <strong><?= (int)$p['stock']; ?></strong></span>
                <span>Sold: <strong><?= (int)$sold; ?></strong></span>
              </div>
            </div>
            <?php $inStock = (int)$p['stock'] > 0; ?>
            <div style="margin-top:8px; display:flex; gap:8px;">
              <button class="btn" <?= $inStock? 'onclick="addToCart(' . (int)$p['id'] . ')"' : 'disabled style="opacity:.6;cursor:not-allowed"' ?>>ADD TO CART</button>
              <button class="btn order" <?= $inStock? 'onclick="orderNow(' . (int)$p['id'] . ')"' : 'disabled style="opacity:.6;cursor:not-allowed"' ?>>ORDER NOW</button>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
  (function(){
    var imgs=[
      '<?= BASE_URL ?>/assets/images/bg/1.jpg',
      '<?= BASE_URL ?>/assets/images/bg/2.jpg',
      '<?= BASE_URL ?>/assets/images/bg/3.jpg'
    ];
    var i=0,
        a=document.getElementById('adimgA'),
        b=document.getElementById('adimgB');

    // Preload for quality and no flicker
    imgs.forEach(function(src){ var im=new Image(); im.src=src; });

    function show(next){
      var incoming = a.classList.contains('active') ? b : a;
      var outgoing = a.classList.contains('active') ? a : b;
      incoming.src = imgs[next];
      // crossfade
      incoming.classList.add('active');
      outgoing.classList.remove('active');
      i = next;
    }
    window.adsNext=function(){ show((i+1)%imgs.length); };
    window.adsPrev=function(){ show((i-1+imgs.length)%imgs.length); };

    // Auto-rotate slideshow with pause on hover
    var ads=document.getElementById('ads');
    var timer=setInterval(window.adsNext, 4000);
    if(ads){
      ads.addEventListener('mouseenter', function(){ clearInterval(timer); });
      ads.addEventListener('mouseleave', function(){ timer=setInterval(window.adsNext, 4000); });
    }
    // Hide any floating Order Assistant button on the homepage
    function hideAssistant(){
      var sels=['#order-assistant','.order-assistant','.orderassistant','[data-order-assistant]'];
      sels.forEach(function(s){ document.querySelectorAll(s).forEach(function(el){ el.style.display='none'; }); });
      Array.from(document.querySelectorAll('a,button,div,span')).forEach(function(el){
        if(/order\s*assistant/i.test(el.textContent||'')) { el.style.display='none'; }
      });
    }
    if(document.readyState!=='loading') hideAssistant(); else document.addEventListener('DOMContentLoaded', hideAssistant);
  })();
  // Live refresh: update first 15 product cards (name, price, stock, image) without reload
  (function(){
    var grid = document.getElementById('home-products'); if(!grid) return;
    var endpoint = '<?= BASE_URL ?>/index.php?json=products';
    function fmt(n){ try{ return Number(n).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }catch(e){ return n; } }
    function refresh(){
      fetch(endpoint, {cache:'no-store'}).then(function(r){ return r.json(); }).then(function(j){
        if(!j || !Array.isArray(j.products)) return;
        var top = j.products.slice(0, 15);
        top.forEach(function(it){
          var card = grid.querySelector('[data-id="'+it.id+'"]'); if(!card) return;
          var title = card.querySelector('h3'); if(title) title.textContent = it.name || '';
          var price = card.querySelector('.price'); if(price) price.textContent = '<?= CURRENCY ?>' + fmt(it.price||0);
          var img = card.querySelector('img'); if(img){ var u = (Array.isArray(it.images) && it.images[0]) ? it.images[0] : img.getAttribute('src'); if(u && img.getAttribute('src')!==u){ img.setAttribute('src', u); img.setAttribute('alt', it.name || ''); } }
          var stockStrong = card.querySelector('.details div span:first-child strong'); if(stockStrong) stockStrong.textContent = (it.stock!=null? it.stock : stockStrong.textContent);
        });
      }).catch(function(){ /* ignore */ });
    }
    setInterval(refresh, 10000);
    if(document.visibilityState === 'visible'){ setTimeout(refresh, 1500); }
    document.addEventListener('visibilitychange', function(){ if(document.visibilityState==='visible') refresh(); });
  })();
  </script>
