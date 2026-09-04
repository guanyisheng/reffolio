<?php
/** @var string $usesPreset */
/** @var string $expiresPreset */
/** @var string $artistHint */
/** @var string $note */
?>
<div class="form-grid">
  <div class="form-row">
    <span class="label-like">可用次数</span>
    <div class="option-chips" role="radiogroup" aria-label="可用次数">
      <label class="option-chip"><input type="radio" name="uses_preset" value="1"<?= invite_preset_checked($usesPreset, '1') ?>><span>1 次</span></label>
      <label class="option-chip"><input type="radio" name="uses_preset" value="3"<?= invite_preset_checked($usesPreset, '3') ?>><span>3 次</span></label>
      <label class="option-chip"><input type="radio" name="uses_preset" value="5"<?= invite_preset_checked($usesPreset, '5') ?>><span>5 次</span></label>
      <label class="option-chip"><input type="radio" name="uses_preset" value="unlimited"<?= invite_preset_checked($usesPreset, 'unlimited') ?>><span>永久</span></label>
    </div>
  </div>
  <div class="form-row">
    <span class="label-like">有效时间</span>
    <div class="option-chips" role="radiogroup" aria-label="有效时间">
      <label class="option-chip"><input type="radio" name="expires_preset" value="1"<?= invite_preset_checked($expiresPreset, '1') ?>><span>1 天</span></label>
      <label class="option-chip"><input type="radio" name="expires_preset" value="3"<?= invite_preset_checked($expiresPreset, '3') ?>><span>3 天</span></label>
      <label class="option-chip"><input type="radio" name="expires_preset" value="5"<?= invite_preset_checked($expiresPreset, '5') ?>><span>5 天</span></label>
      <label class="option-chip"><input type="radio" name="expires_preset" value="never"<?= invite_preset_checked($expiresPreset, 'never') ?>><span>永久</span></label>
    </div>
  </div>
  <div class="form-row">
    <label for="artist_hint">画师备注（仅自己可见）</label>
    <input id="artist_hint" name="artist_hint" type="text" maxlength="128" value="<?= e($artistHint) ?>" placeholder="例如：委托画师小A">
  </div>
  <div class="form-row">
    <label for="note">给画师的说明</label>
    <textarea id="note" name="note" rows="3" placeholder="例如：请上传夏日泳装全身立绘 + 表情差分。"><?= e($note) ?></textarea>
  </div>
</div>
