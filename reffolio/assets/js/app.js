(() => {
  "use strict";

  // Theme (light / dark)
  const themeToggle = document.getElementById("theme-toggle");

  function currentTheme() {
    return document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light";
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute("data-theme", theme === "dark" ? "dark" : "light");
    try {
      localStorage.setItem("theme", theme === "dark" ? "dark" : "light");
    } catch (e) {}
    if (themeToggle) {
      themeToggle.setAttribute("aria-label", theme === "dark" ? "切换到白天模式" : "切换到黑夜模式");
    }
  }

  if (themeToggle) {
    themeToggle.setAttribute("aria-label", currentTheme() === "dark" ? "切换到白天模式" : "切换到黑夜模式");
    themeToggle.addEventListener("click", () => {
      applyTheme(currentTheme() === "dark" ? "light" : "dark");
    });
  }

  // Mobile nav
  const navToggle = document.getElementById("nav-toggle");
  const siteNav = document.getElementById("site-nav");
  if (navToggle && siteNav) {
    navToggle.addEventListener("click", () => {
      const open = siteNav.classList.toggle("is-open");
      navToggle.setAttribute("aria-expanded", open ? "true" : "false");
      navToggle.setAttribute("aria-label", open ? "关闭菜单" : "打开菜单");
    });
    siteNav.querySelectorAll("a").forEach((a) => {
      a.addEventListener("click", () => {
        siteNav.classList.remove("is-open");
        navToggle.setAttribute("aria-expanded", "false");
      });
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        siteNav.classList.remove("is-open");
        navToggle.setAttribute("aria-expanded", "false");
      }
    });
  }

  // 可选入场动画（默认可见，不依赖 JS）
  const revealEls = document.querySelectorAll(".char-tile, .work-item, .image-card");
  revealEls.forEach((el, i) => {
    el.classList.add("reveal", "reveal-animate");
    el.style.transitionDelay = `${Math.min(i * 0.03, 0.2)}s`;
  });
  if ("IntersectionObserver" in window) {
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.05, rootMargin: "0px 0px -10px 0px" }
    );
    document.querySelectorAll(".reveal-animate").forEach((el) => io.observe(el));
  } else {
    document.querySelectorAll(".reveal-animate").forEach((el) => el.classList.add("is-visible"));
  }

  // Lightbox
  document.querySelectorAll("[data-lightbox]").forEach((el) => {
    el.addEventListener("click", (e) => {
      e.preventDefault();
      const src = el.getAttribute("href") || el.dataset.lightbox;
      const box = document.getElementById("lightbox");
      const img = document.getElementById("lightbox-img");
      if (!box || !img || !src) return;
      img.src = src;
      box.classList.add("open");
    });
  });

  const lightbox = document.getElementById("lightbox");
  if (lightbox) {
    lightbox.addEventListener("click", (e) => {
      if (e.target === lightbox || e.target.classList.contains("lightbox-close")) {
        lightbox.classList.remove("open");
        const img = document.getElementById("lightbox-img");
        if (img) img.removeAttribute("src");
      }
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") lightbox.classList.remove("open");
    });
  }

  // Select all checkboxes
  const selectAll = document.getElementById("select-all");
  if (selectAll) {
    selectAll.addEventListener("change", () => {
      document.querySelectorAll('input[name="image_ids[]"]').forEach((cb) => {
        cb.checked = selectAll.checked;
      });
    });
  }

  // Upload wizard
  const wizard = document.getElementById("upload-wizard");
  if (wizard) {
    const panels = [...wizard.querySelectorAll(".wizard-panel")];
    const steps = [...wizard.querySelectorAll(".wizard-step")];
    let current = 0;

    const show = (index) => {
      current = Math.max(0, Math.min(index, panels.length - 1));
      panels.forEach((p, i) => p.classList.toggle("active", i === current));
      steps.forEach((s, i) => s.classList.toggle("active", i === current));
    };

    wizard.querySelectorAll("[data-next]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const panel = panels[current];
        if (panel && !validatePanel(panel)) return;
        show(current + 1);
        if (current === panels.length - 1) buildRemarkFields();
      });
    });

    wizard.querySelectorAll("[data-prev]").forEach((btn) => {
      btn.addEventListener("click", () => show(current - 1));
    });

    const fileInput = document.getElementById("work-images");
    const preview = document.getElementById("image-preview");
    if (fileInput && preview) {
      fileInput.addEventListener("change", () => {
        if (!validateInputFileSizes(fileInput)) {
          fileInput.value = "";
          preview.innerHTML = "";
          return;
        }
        preview.innerHTML = "";
        [...fileInput.files].forEach((file) => {
          const row = document.createElement("div");
          row.className = "preview-item";
          const img = document.createElement("img");
          img.alt = file.name;
          img.src = URL.createObjectURL(file);
          const meta = document.createElement("div");
          meta.innerHTML = `<strong>${escapeHtml(file.name)}</strong><p class="hint">${formatBytes(file.size)}</p>`;
          row.append(img, meta);
          preview.appendChild(row);
        });
      });
    }

    function buildRemarkFields() {
      const remarks = document.getElementById("remark-fields");
      const input = document.getElementById("work-images");
      if (!remarks || !input) return;
      remarks.innerHTML = "";
      [...input.files].forEach((file, i) => {
        const wrap = document.createElement("div");
        wrap.className = "preview-item";
        const img = document.createElement("img");
        img.alt = file.name;
        img.src = URL.createObjectURL(file);
        const fields = document.createElement("div");
        fields.className = "form-grid";
        fields.innerHTML = `
          <div class="form-row">
            <label>图片 ${i + 1} 名称</label>
            <input type="text" name="image_names[]" value="${escapeAttr(file.name.replace(/\.[^.]+$/, ""))}" maxlength="255">
          </div>
          <div class="form-row">
            <label>备注</label>
            <textarea name="image_descriptions[]" rows="2" placeholder="例如：全身立绘 / 表情差分"></textarea>
          </div>`;
        wrap.append(img, fields);
        remarks.appendChild(wrap);
      });
    }

    function validatePanel(panel) {
      const required = panel.querySelectorAll("[required]");
      for (const el of required) {
        if (!el.checkValidity()) {
          el.reportValidity();
          return false;
        }
      }
      if (panel.dataset.step === "images") {
        const input = document.getElementById("work-images");
        if (!input || !input.files.length) {
          alert("请至少上传一张图片。");
          return false;
        }
        if (!validateInputFileSizes(input)) return false;
      }
      return true;
    }
  }

  // Character create: multi-image remarks
  const charImages = document.getElementById("character-images");
  const charRemarks = document.getElementById("character-remark-fields");
  if (charImages && charRemarks) {
    charImages.addEventListener("change", () => {
      if (!validateInputFileSizes(charImages)) {
        charImages.value = "";
        charRemarks.innerHTML = "";
        return;
      }
      charRemarks.innerHTML = "";
      [...charImages.files].forEach((file, i) => {
        const wrap = document.createElement("div");
        wrap.className = "preview-item";
        const img = document.createElement("img");
        img.alt = file.name;
        img.src = URL.createObjectURL(file);
        const fields = document.createElement("div");
        fields.className = "form-grid";
        fields.innerHTML = `
          <div class="form-row">
            <label>图片 ${i + 1} 名称</label>
            <input type="text" name="image_names[]" value="${escapeAttr(suggestName(file.name, i))}" maxlength="255">
          </div>
          <div class="form-row">
            <label>备注</label>
            <textarea name="image_descriptions[]" rows="2" placeholder="例如：正面设定图 / 尾巴细节"></textarea>
          </div>`;
        wrap.append(img, fields);
        charRemarks.appendChild(wrap);
      });
    });
  }

  function suggestName(filename, index) {
    const base = filename.replace(/\.[^.]+$/, "");
    return base || `主设图 ${index + 1}`;
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function escapeAttr(str) {
    return escapeHtml(str).replace(/'/g, "&#39;");
  }

  function formatBytes(n) {
    if (n < 1024) return n + " B";
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + " KB";
    return (n / (1024 * 1024)).toFixed(1) + " MB";
  }

  function getUploadMaxBytes() {
    return typeof window.UPLOAD_MAX_BYTES === "number" ? window.UPLOAD_MAX_BYTES : 50 * 1024 * 1024;
  }

  function validateInputFileSizes(input) {
    const max = getUploadMaxBytes();
    for (const file of input.files || []) {
      if (file.size > max) {
        alert(`「${file.name}」超过单张最大限制（${formatBytes(max)}）`);
        return false;
      }
    }
    return true;
  }

  function formHasFiles(form) {
    return [...form.querySelectorAll('input[type="file"]')].some(
      (inp) => inp.files && inp.files.length > 0
    );
  }

  function validateFormFileSizes(form) {
    for (const input of form.querySelectorAll('input[type="file"]')) {
      if (!validateInputFileSizes(input)) return false;
    }
    return true;
  }

  function showUploadProgress(loaded, total) {
    const overlay = document.getElementById("upload-progress-overlay");
    const bar = document.getElementById("upload-progress-bar");
    const meta = document.getElementById("upload-progress-meta");
    if (!overlay || !bar || !meta) return;
    overlay.hidden = false;
    let pct = 0;
    if (total && total > 0) {
      pct = Math.min(100, Math.round((loaded / total) * 100));
      meta.textContent = `${pct}% · ${formatBytes(loaded)} / ${formatBytes(total)}`;
    } else {
      meta.textContent = `已发送 ${formatBytes(loaded)}…`;
      pct = loaded > 0 ? Math.min(92, 15 + Math.round(loaded / (512 * 1024))) : 0;
    }
    bar.style.width = `${pct}%`;
  }

  function hideUploadProgress() {
    const overlay = document.getElementById("upload-progress-overlay");
    if (overlay) overlay.hidden = true;
    const bar = document.getElementById("upload-progress-bar");
    if (bar) bar.style.width = "0%";
  }

  function isCosDirectUpload() {
    return window.STORAGE_DRIVER === "cos";
  }

  function collectFormFiles(form) {
    const list = [];
    form.querySelectorAll('input[type="file"]').forEach((input) => {
      for (const file of input.files || []) {
        list.push({ input, file });
      }
    });
    return list;
  }

  function getCsrfToken(form) {
    const el = form.querySelector('[name="csrf_token"]');
    return el ? el.value : "";
  }

  function setProgressTitle(text) {
    const title = document.getElementById("upload-progress-title");
    if (title) title.textContent = text;
  }

  function putFileToCos(url, file, contentType) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open("PUT", url);
      xhr.setRequestHeader("Content-Type", contentType || file.type || "application/octet-stream");
      xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          resolve();
          return;
        }
        const body = (xhr.responseText || "").trim().slice(0, 500);
        reject(new Error("COS 上传失败 HTTP " + xhr.status + (body ? "：\n" + body : "")));
      };
      xhr.onerror = () => {
        reject(new Error("COS 直传网络错误。若浏览器控制台有 CORS 报错，请在 COS 桶配置跨域（允许 PUT）。"));
      };
      xhr.send(file);
    });
  }

  async function readJsonResponse(res) {
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch {
      return {
        ok: false,
        message: text.trim().slice(0, 3000) || ("HTTP " + res.status + (res.statusText ? " " + res.statusText : "")),
      };
    }
  }

  async function presignCosUploads(form, files) {
    const context = form.dataset.cosContext || "";
    if (!context) throw new Error("缺少上传上下文配置。");

    const fd = new FormData();
    fd.append("csrf_token", getCsrfToken(form));
    fd.append("context", context);
    fd.append(
      "files",
      JSON.stringify(files.map(({ file }) => ({
        name: file.name,
        size: file.size,
        type: file.type || "application/octet-stream",
      })))
    );

    if (form.dataset.cosWorkId) fd.append("work_id", form.dataset.cosWorkId);
    if (form.dataset.cosCharacterId) fd.append("character_id", form.dataset.cosCharacterId);

    const tokenInput = form.querySelector('[name="token"]');
    if (tokenInput && tokenInput.value) fd.append("invite_token", tokenInput.value);

    const res = await fetch("/cos_presign.php", { method: "POST", body: fd, credentials: "same-origin" });
    const data = await readJsonResponse(res);
    if (!data.ok) throw new Error(data.message || ("获取 COS 上传地址失败（HTTP " + res.status + "）"));
    if (!Array.isArray(data.uploads) || data.uploads.length !== files.length) {
      throw new Error("预签名结果数量不匹配。");
    }
    return data.uploads;
  }

  async function uploadFilesToCos(form, files, onProgress) {
    const uploads = await presignCosUploads(form, files);
    const totalBytes = files.reduce((sum, item) => sum + item.file.size, 0);
    let loadedBytes = 0;
    const successful = [];

    for (let i = 0; i < uploads.length; i++) {
      const upload = uploads[i];
      const file = files[i].file;
      try {
        await putFileToCos(upload.upload_url, file, upload.content_type);
        loadedBytes += file.size;
        if (onProgress) onProgress(loadedBytes, totalBytes);
        successful.push(upload);
      } catch (err) {
        if (successful.length > 0) {
          const partialErr = new Error(err && err.message ? err.message : String(err));
          partialErr.partial = true;
          partialErr.uploads = successful;
          partialErr.failedFrom = i + 1;
          throw partialErr;
        }
        throw err;
      }
    }
    return uploads;
  }

  function supportsPartialUpload(context) {
    return ["work_create", "work_append", "character_create", "character_append", "artist_work"].includes(context);
  }

  function trimFormArrayFields(fd, name, count) {
    const values = fd.getAll(name);
    fd.delete(name);
    values.slice(0, count).forEach((value) => fd.append(name, value));
  }

  async function submitPartialCosUpload(form, uploads, failedFrom, errorMessage) {
    const submitBtns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    const submitFd = new FormData(form);

    form.querySelectorAll('input[type="file"]').forEach((input) => {
      submitFd.delete(input.name);
    });

    submitFd.set("upload_partial", "1");
    submitFd.set("upload_failed_from", String(failedFrom));
    submitFd.set("upload_error_detail", errorMessage || "上传中断");

    uploads.forEach((upload) => submitFd.append("cos_tokens[]", upload.token));
    trimFormArrayFields(submitFd, "image_names[]", uploads.length);
    trimFormArrayFields(submitFd, "image_descriptions[]", uploads.length);

    setProgressTitle("正在保存已上传的图片…");
    showUploadProgress(1, 1);
    const xhr = await submitFormData(form, submitFd);
    hideUploadProgress();
    submitBtns.forEach((b) => { b.disabled = false; });
    handleSubmitResponse(xhr, form);
  }

  function extractErrorFromResponse(xhr) {
    const text = xhr.responseText || "";
    if (text.includes("<html") || text.includes("<!DOCTYPE")) {
      const doc = new DOMParser().parseFromString(text, "text/html");
      const err = doc.querySelector(".flash-error");
      if (err) return err.textContent.trim();
    }
    const trimmed = text.trim();
    if (trimmed) return trimmed.slice(0, 3000);
    if (xhr.status >= 400) {
      return "HTTP " + xhr.status + (xhr.statusText ? " " + xhr.statusText : "");
    }
    return "";
  }

  function showPageError(message, form) {
    if (!message) return;
    let box = document.getElementById("inline-form-error");
    if (!box) {
      box = document.createElement("div");
      box.id = "inline-form-error";
      box.className = "flash flash-error reveal";
      box.setAttribute("role", "alert");
      const anchor =
        form?.closest(".container") ||
        document.querySelector(".site-main .container") ||
        document.querySelector(".site-main");
      if (anchor) {
        anchor.insertBefore(box, anchor.firstChild);
      } else {
        document.body.prepend(box);
      }
    }
    box.textContent = message;
    box.hidden = false;
    box.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  function submitFormData(form, submitFd) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open(form.method || "POST", form.getAttribute("action") || window.location.href);
      xhr.onload = () => resolve(xhr);
      xhr.onerror = () => reject(new Error("网络请求失败，无法连接服务器。"));
      xhr.send(submitFd);
    });
  }

  function handleSubmitResponse(xhr, form) {
    const finalUrl = xhr.responseURL || window.location.href;
    if (finalUrl.split("#")[0] !== window.location.href.split("#")[0]) {
      window.location.href = finalUrl;
      return;
    }
    const errMsg = extractErrorFromResponse(xhr);
    if (errMsg) {
      showPageError(errMsg, form);
      return;
    }
    window.location.reload();
  }

  async function submitViaCosDirect(form) {
    const submitBtns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    submitBtns.forEach((b) => { b.disabled = true; });

    try {
      const context = form.dataset.cosContext || "";
      const allFiles = collectFormFiles(form);
      const submitFd = new FormData(form);

      if (allFiles.length > 0) {
        setProgressTitle("正在直传 COS…");
        showUploadProgress(0, allFiles.reduce((s, f) => s + f.file.size, 0));

        const uploads = await uploadFilesToCos(form, allFiles, showUploadProgress);

        allFiles.forEach(({ input }) => {
          submitFd.delete(input.name);
        });

        if (context === "site_logo") {
          submitFd.set("cos_logo_token", uploads[0].token);
        } else {
          uploads.forEach((u) => submitFd.append("cos_tokens[]", u.token));
        }
      }

      setProgressTitle("正在保存信息…");
      showUploadProgress(1, 1);
      const xhr = await submitFormData(form, submitFd);
      hideUploadProgress();
      handleSubmitResponse(xhr, form);
    } catch (err) {
      hideUploadProgress();
      const context = form.dataset.cosContext || "";
      if (err && err.partial && err.uploads && err.uploads.length > 0 && supportsPartialUpload(context)) {
        try {
          await submitPartialCosUpload(form, err.uploads, err.failedFrom, err.message);
          return;
        } catch (submitErr) {
          showPageError(submitErr && submitErr.message ? submitErr.message : String(submitErr), form);
          return;
        }
      }
      showPageError(err && err.message ? err.message : String(err), form);
    } finally {
      submitBtns.forEach((b) => { b.disabled = false; });
    }
  }

  function submitFormWithProgress(form) {
    const submitBtns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    submitBtns.forEach((b) => { b.disabled = true; });

    const xhr = new XMLHttpRequest();
    xhr.open(form.method || "POST", form.getAttribute("action") || window.location.href);
    xhr.onload = () => {
      hideUploadProgress();
      submitBtns.forEach((b) => { b.disabled = false; });
      handleSubmitResponse(xhr, form);
    };
    xhr.onerror = () => {
      hideUploadProgress();
      submitBtns.forEach((b) => { b.disabled = false; });
      showPageError("网络请求失败。HTTP 状态：" + (xhr.status || "未知") + "。请检查网络或服务器日志。", form);
    };
    xhr.upload.onprogress = (e) => {
      setProgressTitle("正在上传到服务器…");
      showUploadProgress(e.loaded, e.lengthComputable ? e.total : null);
    };
    setProgressTitle("正在上传到服务器…");
    showUploadProgress(0, null);
    xhr.send(new FormData(form));
  }

  document.querySelectorAll("form[data-upload-progress]").forEach((form) => {
    form.addEventListener("submit", (e) => {
      if (!formHasFiles(form)) return;
      if (!validateFormFileSizes(form)) {
        e.preventDefault();
        return;
      }
      e.preventDefault();
      if (isCosDirectUpload()) {
        submitViaCosDirect(form);
      } else {
        submitFormWithProgress(form);
      }
    });
  });

  // Copy share link
  document.querySelectorAll("[data-copy]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const text = btn.getAttribute("data-copy");
      try {
        await navigator.clipboard.writeText(text);
        const old = btn.textContent;
        btn.textContent = "已复制";
        setTimeout(() => (btn.textContent = old), 1500);
      } catch {
        prompt("复制链接：", text);
      }
    });
  });
})();
