(function(){
  var BASE=(window.BASE_URL||'').replace(/\/+$/,'');
  var badgeEl=null; function badge(){ return badgeEl || (badgeEl=document.querySelector('.cart-badge')); }
  function post(url, data){ return fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)}).then(r=>r.json()); }
  function flash(msg){
    var n=document.createElement('div');
    n.textContent=msg; n.style.cssText='position:fixed;top:14px;right:14px;background:#0f172a;color:#fff;padding:10px 14px;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.25);font-size:14px;z-index:2000';
    document.body.appendChild(n); setTimeout(()=>{n.style.opacity='0'; n.style.transition='opacity .4s'; setTimeout(()=>n.remove(),400);},2200);
  }
  function updCount(c){ if(badge()){ badge().textContent=c; badge().style.display=c>0?'inline-flex':'none'; } }
  function updPreview(html){
    var nav=document.querySelector('.cart-nav'); if(!nav) return;
    var pv=nav.querySelector('.cart-preview'); if(!pv) return;
    pv.innerHTML=html;
  }
  window.addToCart = function(pid, qty){
    qty = qty && qty>0 ? qty : 1;
    post(BASE+'/cart.php',{action:'add',id:pid,qty:qty}).then(function(res){
      if(res.login){ location.href=BASE+'/signin.php'; return; }
      if(res.ok){ updCount(res.count||0); if(res.preview){ updPreview(res.preview); } flash('Added '+ qty +' item'+(qty>1?'s':'')+' to cart'); }
    });
  };
  window.updateCart = function(pid, qty){
    post(BASE+'/cart.php',{action:'update',id:pid,qty:qty}).then(function(res){
      if(res.login){ location.href=BASE+'/signin.php'; return; }
      if(res.ok){ if(res.count!==undefined) updCount(res.count); location.reload(); }
    });
  };
  window.orderNow = function(pid){ location.href=BASE + '/product.php?id=' + encodeURIComponent(pid); };
})();

// Mobile header auto-hide on scroll (customer site)
(function(){
  var header=document.querySelector('.site-header'); if(!header) return;
  var nav=document.querySelector('.site-header .nav');
  var last=window.scrollY||0, ticking=false; var TH=12; var DESKTOP=981;
  function update(){
    ticking=false;
    if(window.innerWidth>=DESKTOP){ header.classList.remove('hide'); last=window.scrollY||0; return; }
    var y=window.scrollY||0, d=y-last, atTop=y<=0;
    if(atTop){ header.classList.remove('hide'); }
    else if(d>TH){ if(!(nav&&nav.classList.contains('open'))) header.classList.add('hide'); }
    else if(d<-TH){ header.classList.remove('hide'); }
    last=y;
  }
  window.addEventListener('scroll', function(){ if(!ticking){ requestAnimationFrame(update); ticking=true; } }, {passive:true});
  window.addEventListener('resize', update, {passive:true});
  update();
})();
