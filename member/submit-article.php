<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <script>(function(){var s=location.pathname.replace(/\/+$/,'').split('/').filter(Boolean);var P=/^(index\.html|index|about|programmes|programme|events|event|news|knowledge|article|library|search|ecosystem|members|join|login|dashboard|profile|admin|member|404\.html|gallery|contact)$/;while(s.length>=2&&/^(article|event|programme|poll|resolution)$/.test(s[s.length-2])&&/^\d+$/.test(s[s.length-1])){s.pop();s.pop();}while(s.length&&(P.test(s[s.length-1])||/\.[a-z0-9]+$/i.test(s[s.length-1]))){s.pop();}var base=s.length?'/'+s.join('/'):'';var b=document.createElement('base');b.href=base+'/';document.head.appendChild(b);if(!window.UAS_BASE)window.UAS_BASE=base;})();</script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Article — Uganda Astronomical Society</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/base.css">
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
          <textarea class="form-textarea" id="artBody" rows="10" placeholder="Write your article here..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Featured Image URL (optional)</label>
          <input class="form-input" id="artImg" placeholder="https://...">
        </div>
        <button class="btn btn-primary" onclick="submitArticle()">Submit for Review</button>
        <p class="text-sm text-dim mt-2" id="status"></p>
      </div>
    </div>
  </main>

  <footer class="mt-6 text-center text-dim small">
    <p>Uganda Astronomical Society &middot; Institutional Platform</p>
  </footer>

  <script src="../js/api.js"></script>
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
    }

    async function submitArticle() {
      const title = document.getElementById('artTitle').value;
      const body = document.getElementById('artBody').value;
      if (!title || !body) { alert('Title and body are required'); return; }
      try {
        await api.createArticle({
          title,
          category: document.getElementById('artCat').value,
          body,
          tags: document.getElementById('artTags').value.split(',').map(s => s.trim()).filter(Boolean),
          image_url: document.getElementById('artImg').value || null,
        });
        document.getElementById('status').textContent = 'Submitted for review. You can track it on your dashboard.';
        document.getElementById('artTitle').value = '';
        document.getElementById('artBody').value = '';
        document.getElementById('artTags').value = '';
        document.getElementById('artImg').value = '';
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