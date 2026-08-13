<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resolution Builder — Uganda Astronomical Society</title>
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
        <h1>Governance Resolution Builder</h1>
        <p class="text-dim">Create board resolutions that automatically enact system changes when approved</p>
      </div>
    </section>

    <section class="mt-6">
      <?php
      require_once __DIR__ . '/api/config.php';
      require_once __DIR__ . '/auth.php';
      require_once __DIR__ . '/rbac.php';
      require_once __DIR__ . '/governance.php';
      
      if (!is_logged_in()) json_error('Authentication required', 401);
      
      $user = require_login();
      $caps = user_capabilities($user['id']);
      ?>
      
      <div class="card">
        <div class="card-header">
          <span class="card-title">Create Resolution</span>
        </div>
        
        <form id="resolutionForm" method="POST" action="/resolutions" class="flex flex-col gap-4">
          <input type="hidden" name="route" value="create">
          
          <div class="grid-2 gap-3">
            <div>
              <label class="form-label">Resolution Code</label>
              <input type="text" name="code" class="form-input" value="UAS-BRD-2026-" readonly>
            </div>
            <div>
              <label class="form-label">Resolution Type</label>
              <select name="type" class="form-select" onchange="updateChangeFields()">
                <option value="">Select type</option>
                <option value="role_create">Create Role</option>
                <option value="cap_assign">Assign Capability</option>
                <option value="cap_revoke">Revoke Capability</option>
                <option value="appoint">Appoint Member</option>
                <option value="remove">Remove Member</option>
                <option value="programme_create">Create Programme</option>
                <option value="policy_adopt">Adopt Policy</option>
              </select>
            </div>
          </div>
          
          <div id="changeFields" class="mt-3">
            <!-- Dynamic fields will be injected by JS -->
          </div>
          
          <div class="grid-2 gap-3">
            <div>
              <label class="form-label">Title</label>
              <input type="text" name="title" class="form-input" required>
            </div>
            <div>
              <label class="form-label">Description</label>
              <textarea name="description" class="form-textarea" rows="2" placeholder="Describe the resolution"></textarea>
            </div>
          </div>
          
          <div class="grid-2 gap-3">
            <div>
              <label class="form-label">Quorum</label>
              <input type="number" name="quorum" class="form-input" value="0" min="0" placeholder="0 = simple majority">
            </div>
            <div>
              <label class="form-label">Majority</label>
              <select name="majority" class="form-select">
                <option value="simple">Simple Majority</option>
                <option value="two_thirds">2/3 Majority</option>
                <option value="unanimous">Unanimous</option>
              </select>
            </div>
          </div>
          
          <div class="grid-2 gap-3">
            <div>
              <label class="form-label">Voting Deadline</label>
              <input type="datetime-local" name="voting_deadline" class="form-input">
            </div>
            <div>
              <label class="form-label">Proposed By</label>
              <input type="hidden" name="proposed_by" value="<?= $user['id'] ?>">
              <span class="text-dim"><?= htmlspecialchars($user['name']) ?></span>
            </div>
          </div>
          
          <div>
            <label class="form-label">Changes</label>
            <p class="text-sm text-dim mb-1">Select the system changes this resolution will enact:</p>
            <div id="changesSummary" class="flex flex-col gap-2 small text-dim"></div>
          </div>
          
          <button type="submit" class="btn btn-primary">Create Resolution</button>
        </form>
      </div>
      
      <!-- Voting Interface -->
      <?php
      if (isset($_GET['resolution'])) {
        $resId = (int) $_GET['resolution'];
        $res = get_resolution($resId);
        if ($res && $res['status'] === 'voting') {
      ?>
      <div class="card mt-6">
        <div class="card-header">
          <span class="card-title">Voting: <?= htmlspecialchars($res['title']) ?></span>
          <span class="badge badge-voting">Voting Open</span>
        </div>
        
        <div class="flex flex-between items-center gap-3 mb-3">
          <div>
            <p>For: <strong><?= $res['votes_for'] ?></strong></p>
            <p>Against: <strong><?= $res['votes_against'] ?></strong></p>
            <p>Abstain: <strong><?= $res['votes_abstain'] ?></strong></p>
          </div>
          <p>Quorum Required: <strong><?= $res['quorum'] ?: 'Simple majority' ?></strong></p>
        </div>
        
        <div class="grid-2 gap-3">
          <div>
            <button onclick="castVote('for')" class="btn btn-success btn-sm">Vote For</button>
            <p class="text-dim mt-1">Yes</p>
          </div>
          <div>
            <button onclick="castVote('against')" class="btn btn-outline btn-sm">Vote Against</button>
            <p class="text-dim mt-1">No</p>
          </div>
        </div>
        
        <p class="text-dim mt-3">Voting deadline: <?= $res['voting_deadline'] ? date('M j, Y H:i', strtotime($res['voting_deadline'])) : 'No deadline' ?></p>
        
        <p class="text-dim mt-3" id="quorumStatus">
          <?php
          $total = $res['votes_for'] + $res['votes_against'] + $res['votes_abstain'];
          $quorum = (int) $res['quorum'];
          if ($quorum > 0 && $total < $quorum) {
            $need = $quorum - $total;
            echo "Need {$need} more votes to meet quorum";
          } elseif ($quorum > 0) {
            echo "Quorum met ({$total} votes cast)";
          } else {
            echo "No quorum requirement (simple majority)";
          }
          ?>
        </p>
        
        <p class="text-dim mt-3" id="outcomeStatus"></p>
      </div>
      <?php
        }
      }
      ?>
    </section>
  </main>

  <footer class="mt-6 text-center text-dim small">
    <p>Uganda Astronomical Society · Institutional Platform</p>
  </footer>

  <script>
    // Resolution type → change fields mapper
    const changeFieldTemplates = {
      'role_create': () => `
        <div class="form-group">
          <label class="form-label">Role Title</label>
          <input type="text" name="changes[0][payload][title]" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Role Description</label>
          <textarea name="changes[0][payload][description]" class="form-textarea" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Capability IDs (comma-separated)</label>
          <input type="text" name="changes[0][payload][capability_ids]" class="form-input" placeholder="e.g. articles.approve,events.publish">
        </div>
        <div class="form-group">
          <label class="form-label">Assign To User ID</label>
          <input type="number" name="changes[0][payload][assign_to_user_id]" class="form-input">
        </div>
      `,
      'cap_assign': () => `
        <div class="form-group">
          <label class="form-label">Role ID</label>
          <input type="number" name="changes[0][payload][role_id]" class="form-input" min="1">
        </div>
        <div class="form-group">
          <label class="form-label">Capability ID</label>
          <input type="number" name="changes[0][payload][capability_id]" class="form-input" min="1">
        </div>
      `,
      'cap_revoke': () => `
        <div class="form-group">
          <label class="form-label">Role ID</label>
          <input type="number" name="changes[0][payload][role_id]" class="form-input" min="1">
        </div>
        <div class="form-group">
          <label class="form-label">Capability ID</label>
          <input type="number" name="changes[0][payload][capability_id]" class="form-input" min="1">
        </div>
      `,
      'appoint': () => `
        <div class="form-group">
          <label class="form-label">Role ID</label>
          <input type="number" name="changes[0][payload][role_id]" class="form-input" min="1">
        </div>
        <div class="form-group">
          <label class="form-label">User ID</label>
          <input type="number" name="changes[0][payload][user_id]" class="form-input" min="1">
        </div>
        <div class="form-group">
          <label class="form-label">Effective To (YYYY-MM-DD, leave blank for permanent)</label>
          <input type="date" name="changes[0][payload][effective_to]" class="form-input">
        </div>
      `,
      'remove': () => `
        <div class="form-group">
          <label class="form-label">Role ID</label>
          <input type="number" name="changes[0][payload][role_id]" class="form-input" min="1">
        </div>
        <div class="form-group">
          <label class="form-label">User ID</label>
          <input type="number" name="changes[0][payload][user_id]" class="form-input" min="1">
        </div>
      `,
      'programme_create': () => `
        <div class="form-group">
          <label class="form-label">Programme Title</label>
          <input type="text" name="changes[0][payload][title]" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="changes[0][payload][description]" class="form-textarea" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Lead User ID</label>
          <input type="number" name="changes[0][payload][lead_id]" class="form-input" min="1">
        </div>
      `,
      'policy_adopt': () => `
        <div class="form-group">
          <label class="form-label">Policy Title</label>
          <input type="text" name="changes[0][payload][title]" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-law">Policy Description</label>
          <textarea name="changes[0][payload][description]" class="form-textarea" rows="3"></textarea>
        </div>
      `
    };
    
    function updateChangeFields() {
      const type = document.querySelector('select[name="type"]').value;
      const container = document.getElementById('changeFields');
      const summary = document.getElementById('changesSummary');
      
      if (!type || !changeFieldTemplates[type]) {
        container.innerHTML = '<p class="text-dim">Select a resolution type to see change fields</p>';
        summary.innerHTML = '';
        return;
      }
      
      container.innerHTML = changeFieldTemplates[type]();
      summary.innerHTML = '<p class="text-sm"><strong>This resolution will:</strong></p>';
    }
    
    // Initial render
    updateChangeFields();
    
    function castVote(value) {
      if (!confirm(`Cast vote: ${value}`)) return;
      // In a real app, this would API-call cast_vote
      alert(`Vote recorded: ${value}`);
    }
  </script>
</body>
</html>