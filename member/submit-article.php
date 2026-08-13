<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Article — Uganda Astronomical Society</title>
  <link rel="stylesheet" href="/uas/css/base.css">
</head>
<body>
  <nav class="nav">
    <a href="/" class="nav-brand">Uganda Astronomical Society</a>
    <div class="nav-links">
      <a href="/" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">Home</a>
      <a href="/about" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : '' ?>">About</a>
      <a href="/programmes" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'programmes.php' ? 'active' : '' ?>">Programmes</a>
      <a href="/events" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'events.php' ? 'active' : '' ?>">Events</a>
      <a href="/dashboard" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
      <?php if (!is_logged_in()): ?>
        <a href="/auth/login" class="nav-link">Login</a>
      <?php endif; ?>
    </div>
    <div class="nav-user">
      <?= isset($_SESSION['user_id']) ? "Welcome, {$_SESSION['user_name']}" : 'Login' ?> |
      <a href="/auth/logout">Logout</a>
    </div>
  </nav>

  <main class="container">
    <section class="hero">
      <div class="max-w-prose">
        <h1>Submit Article</h1>
        <p class="text-dim">Share your astronomical observations, research, or educational pieces with the UAS community</p>
      </div>
    </section>

    <section class="mt-6">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Article Submission Form</span>
        </div>
        
        <form action="/articles" method="POST" class="flex flex-col gap-4">
          <input type="hidden" name="route" value="submit">
          
          <div class="grid-2 gap-3">
            <div>
              <label class="form-label">Title</label>
              <input type="text" name="title" class="form-input" required>
            </div>
            <div>
              <label class="form-label">Category</label>
              <select name="category" class="form-select">
                <option value="article">Article</option>
                <option value="observing_report">Observing Report</option>
                <option value="educational">Educational Piece</option>
                <option value="project_report">Project Report</option>
                <option value="announcement">Announcement</option>
                <option value="paper">Paper</option>
              </select>
            </div>
          </div>
          
          <div class="form-group">
            <label class="form-label">Body</label>
            <textarea name="body" class="form-textarea" rows="8" placeholder="Write your article here..." required></textarea>
          </div>
          
          <div class="grid-2 gap-3">
            <div>
              <label class="form-label">Tags (comma-separated)</label>
              <input type="text" name="tags" class="form-input" placeholder="e.g. observation, telescope, solar">
            </div>
            <div>
              <label class="form-label">Image URL</label>
              <input type="text" name="image_url" class="form-input" placeholder="Optional featured image">
            </div>
          </div>
          
          <div class="form-group">
            <label class="form-label">Approver Role</label>
            <p class="text-dim small">Select which role should review this article</p>
            <select name="approver_role_id" class="form-select">
              <?php
              // List available roles
              $stmt = db()->prepare('SELECT * FROM roles WHERE status = "active" ORDER BY title');
              $stmt->execute();
              foreach ($stmt->fetchAll() as $role): 
              ?>
                <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <button type="submit" class="btn btn-primary">Submit Article</button>
          <button type="button" class="btn btn-outline" onclick="window.history.back()">Cancel</button>
        </form>
      </div>
    </section>
  </main>

  <footer class="mt-6 text-center text-dim small">
    <p>Uganda Astronomical Society · Institutional Platform</p>
  </footer>

  <script>
    document.querySelectorAll('.nav-link').forEach(link => {
      if (link.href === window.location.href) link.classList.add('active');
    });
  </script>
</body>
</html>