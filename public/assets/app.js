(() => {
  const input = document.querySelector('#image');
  const zone = document.querySelector('[data-upload-zone]');
  const preview = document.querySelector('[data-image-preview]');
  const fileName = document.querySelector('[data-file-name]');
  const form = document.querySelector('[data-upload-form]');
  const submit = document.querySelector('[data-submit-button]');
  let previewUrl = null;

  function showFile(file) {
    if (!file || !input) return;
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type)) {
      if (fileName) fileName.textContent = 'Unsupported type. Choose JPEG, PNG, or WebP.';
      if (preview) { preview.removeAttribute('src'); preview.classList.remove('visible'); }
      input.value = '';
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      if (fileName) fileName.textContent = 'File is larger than 5 MB. Choose a smaller image.';
      if (preview) { preview.removeAttribute('src'); preview.classList.remove('visible'); }
      input.value = '';
      return;
    }
    if (fileName) fileName.textContent = file.name + ' · ' + (file.size / 1024).toFixed(1) + ' KB';
    if (preview) {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      previewUrl = URL.createObjectURL(file);
      preview.src = previewUrl;
      preview.classList.add('visible');
    }
  }

  input?.addEventListener('change', () => showFile(input.files?.[0]));
  zone?.addEventListener('dragover', event => { event.preventDefault(); zone.classList.add('dragover'); });
  zone?.addEventListener('dragleave', () => zone.classList.remove('dragover'));
  zone?.addEventListener('drop', event => {
    event.preventDefault();
    zone.classList.remove('dragover');
    const file = event.dataTransfer?.files?.[0];
    if (!file || !input) return;
    try { input.files = event.dataTransfer.files; } catch (_) { /* Browser may disallow assignment. */ }
    showFile(file);
  });
  form?.addEventListener('submit', () => {
    if (!input?.files?.length) return;
    form.classList.add('is-submitting');
    if (submit) { submit.disabled = true; submit.querySelector('[data-button-label]')?.replaceChildren(document.createTextNode('Analyzing image…')); }
  });
  window.addEventListener('beforeunload', () => { if (previewUrl) URL.revokeObjectURL(previewUrl); });
})();
