<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_admin();
$error = '';

$cfg = storage_config();
$cos = $cfg['cos'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        save_storage_settings([
            'driver'             => $_POST['driver'] ?? 'local',
            'secret_id'          => $_POST['secret_id'] ?? '',
            'secret_key'         => $_POST['secret_key'] ?? '',
            'bucket'             => $_POST['bucket'] ?? '',
            'region'             => $_POST['region'] ?? '',
            'acl'                => $_POST['acl'] ?? 'private',
            'domain'             => $_POST['domain'] ?? '',
            'scheme'             => $_POST['scheme'] ?? 'https',
            'prefix'             => $_POST['prefix'] ?? '',
            'signed_url_expires' => $_POST['signed_url_expires'] ?? 7200,
            'cdn_domain'         => $_POST['cdn_domain'] ?? '',
        ]);
        // 清静态缓存：重新加载页面即可
        flash('success', '存储设置已保存。');
        redirect('/settings.php');
    } catch (InvalidArgumentException $e) {
        $error = user_error_message($e);
        $cfg['driver'] = ($_POST['driver'] ?? 'local') === 'cos' ? 'cos' : 'local';
        $cos = array_merge($cos, [
            'secret_id'          => trim((string) ($_POST['secret_id'] ?? '')),
            'bucket'             => trim((string) ($_POST['bucket'] ?? '')),
            'region'             => trim((string) ($_POST['region'] ?? '')),
            'acl'                => trim((string) ($_POST['acl'] ?? 'private')),
            'domain'             => trim((string) ($_POST['domain'] ?? '')),
            'scheme'             => trim((string) ($_POST['scheme'] ?? 'https')),
            'prefix'             => trim((string) ($_POST['prefix'] ?? '')),
            'signed_url_expires' => (int) ($_POST['signed_url_expires'] ?? 7200),
            'cdn_domain'         => trim((string) ($_POST['cdn_domain'] ?? '')),
        ]);
    } catch (Throwable $e) {
        $error = user_error_message($e);
    }
}

$hasSecretKey = (string) get_setting('storage.cos.secret_key', '') !== '';

render_header('存储设置');
?>
<div class="container">
  <h1 class="page-title">存储设置</h1>
  <p class="page-lead">选择本地上传或腾讯云 COS。腾讯云相关参数需自行填写，不会预置。</p>

  <?php if ($error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" class="wizard-panel active" style="display:block;max-width:720px;">
    <?= csrf_field() ?>

    <div class="form-grid">
      <div class="form-row">
        <label for="driver">存储方式</label>
        <select id="driver" name="driver">
          <option value="local" <?= ($cfg['driver'] ?? 'local') === 'local' ? 'selected' : '' ?>>本地存储（uploads/）</option>
          <option value="cos" <?= ($cfg['driver'] ?? '') === 'cos' ? 'selected' : '' ?>>腾讯云 COS</option>
        </select>
      </div>
    </div>

    <h2 class="section-title" style="margin-top:1.75rem;">腾讯云 COS 参数</h2>
    <p class="hint" style="margin-top:-0.5rem;margin-bottom:1rem;">以下全部由你自行填写。可在腾讯云控制台 → 对象存储 / 访问管理 中查看。</p>

    <div class="form-grid">
      <div class="form-row">
        <label for="secret_id">SecretId（API）</label>
        <input id="secret_id" name="secret_id" type="text" autocomplete="off"
               value="<?= e($cos['secret_id'] ?? '') ?>"
               placeholder="在「访问管理 → API 密钥」创建/查看">
      </div>

      <div class="form-row">
        <label for="secret_key">SecretKey（SK）</label>
        <input id="secret_key" name="secret_key" type="password" autocomplete="new-password"
               value=""
               placeholder="<?= $hasSecretKey ? '已保存，留空表示不修改' : '请填写 SecretKey' ?>">
        <span class="hint"><?= $hasSecretKey ? '已保存过密钥；若要更换请重新输入，留空则保持不变。' : '首次启用 COS 必须填写。' ?></span>
      </div>

      <div class="form-row">
        <label for="bucket">存储桶名称</label>
        <input id="bucket" name="bucket" type="text"
               value="<?= e($cos['bucket'] ?? '') ?>"
               placeholder="例如：bucketname-1250000000">
      </div>

      <div class="form-row">
        <label for="region">地域</label>
        <input id="region" name="region" type="text"
               value="<?= e($cos['region'] ?? '') ?>"
               placeholder="例如：ap-chengdu / ap-guangzhou / ap-shanghai">
      </div>

      <div class="form-row">
        <label for="acl">访问权限</label>
        <select id="acl" name="acl">
          <option value="private" <?= ($cos['acl'] ?? 'private') === 'private' ? 'selected' : '' ?>>私有读写（推荐）</option>
          <option value="public-read" <?= ($cos['acl'] ?? '') === 'public-read' ? 'selected' : '' ?>>公有读私有写</option>
        </select>
      </div>

      <div class="form-row">
        <label for="domain">存储桶域名</label>
        <input id="domain" name="domain" type="text"
               value="<?= e($cos['domain'] ?? '') ?>"
               placeholder="例如：bucketname-1250000000.cos.ap-chengdu.myqcloud.com">
        <span class="hint">不含 https://</span>
      </div>

      <div class="form-row">
        <label for="prefix">对象键前缀（可选）</label>
        <input id="prefix" name="prefix" type="text"
               value="<?= e($cos['prefix'] ?? '') ?>"
               placeholder="例如：my-bucket/">
        <span class="hint">用于与桶内其他文件隔离，建议以 / 结尾</span>
      </div>

      <div class="form-row">
        <label for="cdn_domain">CDN 加速域名（可选）</label>
        <input id="cdn_domain" name="cdn_domain" type="text"
               value="<?= e($cos['cdn_domain'] ?? '') ?>"
               placeholder="例如：img.example.com">
      </div>

      <div class="form-row">
        <label for="scheme">访问协议</label>
        <select id="scheme" name="scheme">
          <option value="https" <?= ($cos['scheme'] ?? 'https') === 'https' ? 'selected' : '' ?>>https</option>
          <option value="http" <?= ($cos['scheme'] ?? '') === 'http' ? 'selected' : '' ?>>http</option>
        </select>
      </div>

      <div class="form-row">
        <label for="signed_url_expires">私有桶临时链接有效期（秒）</label>
        <input id="signed_url_expires" name="signed_url_expires" type="text"
               value="<?= e((string) ($cos['signed_url_expires'] ?? 7200)) ?>"
               placeholder="7200">
      </div>
    </div>

    <div id="cos-test-result" class="flash" style="display:none;margin-top:1rem;" role="status"></div>

    <div class="btn-row">
      <button class="btn btn-primary" type="submit">保存设置</button>
      <button class="btn btn-ghost" type="button" id="btn-test-cos">测试连接</button>
      <a class="btn btn-ghost" href="/characters.php">返回</a>
    </div>
  </form>
</div>
<script>
(() => {
  const btn = document.getElementById("btn-test-cos");
  const result = document.getElementById("cos-test-result");
  const form = btn && btn.closest("form");
  if (!btn || !result || !form) return;

  btn.addEventListener("click", async () => {
    btn.disabled = true;
    const old = btn.textContent;
    btn.textContent = "测试中…";
    result.style.display = "block";
    result.className = "flash flash-info";
    result.textContent = "正在上传测试文件到 COS…";

    try {
      const fd = new FormData(form);
      const res = await fetch("/settings_test_cos.php", { method: "POST", body: fd, credentials: "same-origin" });
      const text = await res.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch {
        data = { ok: false, message: text.trim().slice(0, 3000) || ("HTTP " + res.status + (res.statusText ? " " + res.statusText : "")) };
      }
      result.className = "flash " + (data.ok ? "flash-success" : "flash-error");
      result.textContent = data.message || (data.ok ? "成功" : ("失败（HTTP " + res.status + "）"));
      if (data.ok && data.object) {
        result.textContent += "（" + data.object + "）";
      }
    } catch (err) {
      result.className = "flash flash-error";
      result.textContent = "请求失败：" + (err && err.message ? err.message : "网络错误");
    } finally {
      btn.disabled = false;
      btn.textContent = old;
    }
  });
})();
</script>
<?php render_footer(); ?>