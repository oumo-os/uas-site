<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <script>(function(){var s=location.pathname.replace(/\/+$/,'').split('/').filter(Boolean);var P=/^(index\.html|index|about|programmes|programme|events|event|news|knowledge|article|library|search|ecosystem|members|join|login|dashboard|profile|admin|member|404\.html|gallery|contact)$/;while(s.length>=2&&/^(article|articles|event|events|programme|programmes|poll|polls|resolution|resolutions)$/.test(s[s.length-2])&&/^\d+$/.test(s[s.length-1])){s.pop();s.pop();}while(s.length&&(P.test(s[s.length-1])||/\.[a-z0-9]+$/i.test(s[s.length-1]))){s.pop();}var base=s.length?'/'+s.join('/'):'';var b=document.createElement('base');b.href=base+'/';document.head.appendChild(b);if(!window.UAS_BASE)window.UAS_BASE=base;})();</script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Article — Uganda Astronomical Society</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/base.css?v=20260911-3">
</head>
<body>
  <nav class="nav">
    <div class="container" style="display:flex;align-items:center;width:100%">
      <a href="/" class="nav-brand">UAS</a>
      <div class="nav-links">
        <a href="/" class="nav-link">Home</a>
        <a href="/news" class="nav-link">News</a>
        <a href="/knowledge" class="nav-link">Knowledge</a>
        <a href="/dashboard" class="nav-link">Dashboard</a>
      </div>
      <div class="nav-user" id="navUser"></div>
    </div>
  </nav>

  <main class="container" style="max-width: 720px">
    <div class="dash-header">
      <h1>Submit Article</h1>
      <p class="text-dim">Share observations, research, or educational pieces with the UAS community</p>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Article Submission</span></div>
      <div class="mt-2">
        <div class="form-group">
          <label class="form-label">Title</label>
          <input class="form-input" id="artTitle" placeholder="Article title">
        </div>
        <div class="grid-2 gap-3">
          <div class="form-group">
            <label class="form-label">Category</label>
            <select class="form-select" id="artCat">
              <option value="article">Article</option>
              <option value="observing_report">Observing Report</option>
              <option value="educational">Educational</option>
              <option value="announcement">Announcement</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Tags (comma-separated)</label>
            <input class="form-input" id="artTags" placeholder="e.g. observation, telescope, solar">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Body</label>
          <div class="rte-wrap" id="artBody-wrap"><div class="rte-toolbar" id="artBody-toolbar"></div><div class="rte-editor" id="artBody" contenteditable="true"></div></div>
        </div>
        <div class="form-group">
          <label class="form-label">Cover Image (optional)</label>
          <div class="cover-picker" id="artCover-wrap">
            <img id="artCover-preview" class="cover-preview" alt="Cover preview" style="display:none">
            <input class="form-input" type="file" id="artCover" accept="image/*">
            <input type="hidden" id="artCoverUrl" value="">
            <button type="button" class="btn btn-outline btn-sm cover-remove" id="artCoverRemove" style="display:none">Remove image</button>
            <div class="form-help">JPG, PNG, GIF or WebP. Compressed automatically on upload.</div>
          </div>
        </div>
        <button class="btn btn-primary" onclick="submitArticle()">Submit for Review</button>
        <p class="text-sm text-dim mt-2" id="status"></p>
      </div>
    </div>
  </main>

  <footer class="mt-6 text-center text-dim small">
    <p>Uganda Astronomical Society &middot; Institutional Platform</p>
  </footer>

  <script src="../js/api.js?v=20260911-4"></script>
  <script>
    async function load() {
      try {
        await api.me();
        if (!api.hasCap('articles.submit')) {
          document.getElementById('status').textContent = 'Your account does not yet have submission rights.';
          return;
        }
      } catch(e) { window.location.href = ua('/login'); }
      updateNavUser();
      RichTextEditor.init('artBody-toolbar', 'artBody');
      bindCoverPicker();
    }

    function bindCoverPicker() {
      const fileEl = document.getElementById('artCover');
      const prevEl = document.getElementById('artCover-preview');
      const rmEl = document.getElementById('artCoverRemove');
      fileEl.addEventListener('change', () => {
        const file = fileEl.files[0];
        if (!file) return;
        if (!file.type.startsWith('image/')) { alert('Please choose an image file (JPG, PNG, GIF, WebP).'); fileEl.value = ''; return; }
        if (prevEl.dataset.objUrl) URL.revokeObjectURL(prevEl.dataset.objUrl);
        const objUrl = URL.createObjectURL(file);
        prevEl.dataset.objUrl = objUrl;
        prevEl.src = objUrl;
        prevEl.style.display = 'block';
        rmEl.style.display = '';
      });
      rmEl.addEventListener('click', () => {
        fileEl.value = '';
        document.getElementById('artCoverUrl').value = '';
        if (prevEl.dataset.objUrl) { URL.revokeObjectURL(prevEl.dataset.objUrl); delete prevEl.dataset.objUrl; }
        prevEl.removeAttribute('src');
        prevEl.style.display = 'none';
        rmEl.style.display = 'none';
      });
    }

    async function submitArticle() {
      const title = document.getElementById('artTitle').value;
      const body = RichTextEditor.getHtml('artBody');
      if (!title || !body) { alert('Title and body are required'); return; }
      const statusEl = document.getElementById('status');
      try {
        let imageUrl = document.getElementById('artCoverUrl').value || null;
        const coverFile = document.getElementById('artCover').files[0];
        if (coverFile) {
          statusEl.textContent = 'Uploading cover image...';
          const up = await api.uploadFile(coverFile);
          imageUrl = up.url;
        }
        await api.createArticle({
          title,
          category: document.getElementById('artCat').value,
          body,
          tags: document.getElementById('artTags').value.split(',').map(s => s.trim()).filter(Boolean),
          image_url: imageUrl,
        });
        document.getElementById('status').textContent = 'Submitted for review. You can track it on your dashboard.';
        document.getElementById('artTitle').value = '';
        RichTextEditor.setHtml('artBody', '');
        document.getElementById('artTags').value = '';
        document.getElementById('artCover').value = '';
        document.getElementById('artCoverUrl').value = '';
        const prevEl = document.getElementById('artCover-preview');
        prevEl.removeAttribute('src');
        prevEl.style.display = 'none';
        document.getElementById('artCoverRemove').style.display = 'none';
      } catch(e) { alert(e.error || 'Submission failed'); }
    }

    function esc(s) { return s ? s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }
    function updateNavUser() {
      const el = document.getElementById('navUser');
      el.innerHTML = api._user
        ? `<a href="/dashboard" class="btn btn-outline btn-sm">${esc(api._user.name)}</a>`
        : `<a href="/login" class="btn btn-primary btn-sm">Login</a>`;
    }
    load();
  </script>
</body>
</html>