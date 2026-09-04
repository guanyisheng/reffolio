</main>
  <footer class="site-footer">
    <div class="container">
      <p><?= e(site('site.footer', '角色设定与稿件管理系统 · Reffolio')) ?></p>
    </div>
  </footer>
  <script>window.UPLOAD_MAX_BYTES = <?= (int) UPLOAD_MAX_BYTES ?>;</script>
  <script>window.STORAGE_DRIVER = <?= json_encode(storage_driver(), JSON_UNESCAPED_UNICODE) ?>;</script>
  <div id="upload-progress-overlay" class="upload-progress-overlay" hidden>
    <div class="upload-progress-card" role="dialog" aria-modal="true" aria-labelledby="upload-progress-title">
      <p id="upload-progress-title" class="upload-progress-title">正在上传…</p>
      <div class="upload-progress-track"><div class="upload-progress-bar" id="upload-progress-bar"></div></div>
      <p class="upload-progress-meta" id="upload-progress-meta">0%</p>
      <p class="hint upload-progress-hint">请勿关闭页面，上传完成后会自动跳转。</p>
    </div>
  </div>
  <script src="<?= e(asset_ver('assets/js/app.js')) ?>"></script>
</body>
</html>
