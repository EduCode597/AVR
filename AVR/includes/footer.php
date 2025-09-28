  <?php $p = $_SERVER['SCRIPT_NAME'] ?? ''; $showFooter = (substr($p, -10) === '/index.php' && strpos($p, '/admin/') === false); ?>
  <?php if ($showFooter): ?>
  <footer class="site-footer">
      <div class="container footer-grid">
        <details class="f-acc" open>
          <summary class="caps">About Us</summary>
          <div class="content">
            <p style="margin:0">At FpB Network and Power Solutions Services, we specialize in providing high-quality Automatic Voltage Regulators (AVRs), Uninterruptible Power Supply (UPS) systems, Solar Power solutions, and advanced LED lighting technologies that enhance your everyday operations.</p>
          </div>
        </details>
        <details class="f-acc" open>
          <summary class="caps">Links</summary>
          <div class="content">
            <ul class="list">
              <li><a href="<?= BASE_URL ?>/orders.php">My Orders</a></li>
              <li><a href="<?= BASE_URL ?>/service.php">Service</a></li>
            </ul>
          </div>
        </details>
        <details class="f-acc support" open>
          <summary class="caps">Support</summary>
          <div class="content">
            <ul class="list">
              <li><a href="#">Shipping & Delivery</a></li>
              <li><a href="#">Warranty & Returns</a></li>
              <li><a href="#">Privacy Policy</a></li>
            </ul>
          </div>
        </details>
        <details class="f-acc" open>
          <summary class="caps">Services</summary>
          <div class="content">
            <ul class="list" style="list-style:disc;padding-left:18px">
              <li>Elevator and Escalator Parts &amp; Services</li>
              <li>Repair &amp; Servicing</li>
              <li>Design &amp; Estimation</li>
              <li>After Sales Services</li>
            </ul>
          </div>
        </details>
        <details class="f-acc" open>
          <summary class="caps">Contact</summary>
          <div class="content">
            <ul class="list">
              <li><strong>Address:</strong> <a href="https://www.google.com/maps/dir//14.1966131,121.1242696/@14.2049282,121.0931057,14z?entry=ttu&g_ep=EgoyMDI1MDkyMy4wIKXMDSoASAFQAw%3D%3D" target="_blank" rel="noopener">Block 23 Lot 7 Laguna Buenavista Executive Homes, Barandal, Calamba, Philippines</a></li>
              <li><strong>Email:</strong> admin@fnpss.com</li>
              <li><strong>Facebook:</strong> <a href="https://www.facebook.com/profile.php?id=100064105382601" target="_blank" rel="noopener">Fpb Network and Power Solutions Services</a></li>
              <li><strong>Phone:</strong> 0917 836 5017</li>
            </ul>
          </div>
        </details>
      </div>
    <div class="container" style="padding-top:8px;border-top:1px solid rgba(255,255,255,.25);margin-top:12px;text-align:center">
      <p style="margin:8px 0">&copy; <?= date('Y'); ?> AVR Shop. All rights reserved.</p>
    </div>
  </footer>
<?php endif; ?>
  <script>
    (function(){
      function syncFooter(){
        var mobile = window.matchMedia('(max-width: 900px)').matches;
        var acc = document.querySelectorAll('.f-acc'); if(!acc.length) return;
        acc.forEach(function(d){ mobile ? d.removeAttribute('open') : d.setAttribute('open',''); });
      }
      window.addEventListener('load', syncFooter);
      window.addEventListener('resize', syncFooter);
    })();
  </script>
  <script src="<?= BASE_URL ?>/assets/js/script.js?v=20250923"></script>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/chatbot.css?v=20250923">
  <script src="<?= BASE_URL ?>/assets/js/chatbot.js?v=20250927"></script>
</body>
</html>
