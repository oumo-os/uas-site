<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <script>(function(){var s=location.pathname.replace(/\/+$/,'').split('/').filter(Boolean);var P=/^(index\.html|index|about|programmes|programme|events|event|news|knowledge|article|library|search|ecosystem|members|join|login|dashboard|profile|admin|member|404\.html|gallery)$/;while(s.length>=2&&/^(article|event|programme|poll|resolution)$/.test(s[s.length-2])&&/^\d+$/.test(s[s.length-1])){s.pop();s.pop();}while(s.length&&(P.test(s[s.length-1])||/\.[a-z0-9]+$/i.test(s[s.length-1]))){s.pop();}var base=s.length?'/'+s.join('/'):'';var b=document.createElement('base');b.href=base+'/';document.head.appendChild(b);if(!window.UAS_BASE)window.UAS_BASE=base;})();</script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resolution Builder — Uganda Astronomical Society</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/base.css">
  <style>
    .change-row { border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem; margin-bottom: 0.75rem; }
    .vote-card { border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem; margin-bottom: 1rem; }
    .vote-tally { display: flex; gap: 1.5rem; font-size: 0.9rem; }
    .vote-tally strong { font-size: 1.3rem; }
    .res-row { border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem; margin-bottom: 0.75rem; }
  </style>
</head>
<body>
  <nav class="nav">
    <div class="container" style="display:flex;align-items:center;width:100%">
      <a href="/" class="nav-brand">UAS</a>
      <div class="nav-links">
        <a href="/" class="nav-link">Home</a>
        <a href="/dashboard" class="nav-link">Dashboard</a>
        <a href="/admin" class="nav-link">Admin</a>
      </div>
      <div class="nav-user" id="navUser"></div>
    </div>
  </nav>

  <main class="container" style="max-width: 760px">
    <div class="dash-header">
      <h1>Governance Resolution Builder</h1>
      <p class="text-dim">Resolutions auto-apply when the last vote meets quorum</p>
    </div>

    <!-- Create Resolution -->
    <div class="card" id="createCard">
      <div class="card-header">
        <span class="card-title">Create Resolution</span>
      </div>
      <div class="mt-2">
        <div class="grid-2 gap-3">
          <div>
            <label class="form-label">Resolution Type</label>
            <select class="form-select" id="resType" onchange="updateChangeFields()">
              <option value="">Select type</option>
              <option value="role_create">Create Role</option>
              <option value="appoint">Appoint Member</option>
              <option value="remove">Remove Member</option>
              <option value="cap_assign">Assign Capability</option>
              <option value="cap_revoke">Revoke Capability</option>
              <option value="programme_create">Create Programme</option>
              <option value="policy_adopt">Adopt Policy</option>
            </select>
          </div>
          <div>
            <label class="form-label">Title</label>
            <input class="form-input" id="resTitle" placeholder="Resolution title">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-textarea" id="resDesc" rows="2" placeholder="What will this resolution enact?"></textarea>
        </div>

        <div id="changeFields" class="mt-2"></div>

        <div class="grid-2 gap-3">
          <div>
            <label class="form-label">Quorum (0 = any vote decides)</label>
            <input type="number" class="form-input" id="resQuorum" value="0" min="0">
          </div>
          <div>
            <label class="form-label">Majority</label>
            <select class="form-select" id="resMajority">
              <option value="simple">Simple Majority</option>
              <option value="two_thirds">2/3 Majority</option>
              <option value="unanimous">Unanimous</option>
            </select>
          </div>
        </div>

        <button class="btn btn-primary mt-3" onclick="createResolution()">Create Resolution</button>
        <p class="text-sm text-dim mt-2" id="createStatus"></p>
      </div>
    </div>

    <!-- Resolutions List -->
    <h3 class="section-title mt-6" style="text-align:left">Resolutions</h3>
    <div id="resolutionsList" class="mt-2"></div>
  </main>

  <footer class="mt-6 text-center text-dim small">
    <p>Uganda Astronomical Society &middot; Institutional Platform</p>
  </footer>

  <script src="../js/api.js"></script>
  <script>
    const memberOptions = [];
    const roleOptions = [];

    async function load() {
      try {
        await api.me();
        if (!api.hasCap('resolutions.create') && !api.hasCap('resolutions.vote')) {
          window.location.href = ua('/dashboard');
          return;
        }
        updateNavUser();
        updateChangeFields();
        await refreshResolutions();
        await Promise.all([loadMembers(), loadRoles()]);
      } catch(e) { window.location.href = ua('/login'); }
    }

    async function loadMembers() {
      try {
        const members = await api.getMembers();
        memberOptions.length = 0;
        members.forEach(m => memberOptions.push({ id: m.user_id, label: m.name + ' (' + m.email + ')' }));
      } catch(e) {}
    }

    async function loadRoles() {
      try {
        const roles = await api.getRoles();
        roleOptions.length = 0;
        roles.forEach(r => roleOptions.push({ id: r.id, label: r.title }));
      } catch(e) {}
    }

    function memberSelect(id) {
      return `<select class="form-select" id="${id}">${memberOptions.map(m => `<option value="${m.id}">${esc(m.label)}</option>`).join('')}</select>`;
    }

    function roleSelect(id) {
      return `<select class="form-select" id="${id}">${roleOptions.map(r => `<option value="${r.id}">${esc(r.label)}</option>`).join('')}</select>`;
    }

    function updateChangeFields() {
      const type = document.getElementById('resType').value;
      const div = document.getElementById('changeFields');
      if (!type) { div.innerHTML = ''; return; }
      const field = (label, html) => `<div class="form-group"><label class="form-label">${label}</label>${html}</div>`;

      switch (type) {
        case 'role_create':
          div.innerHTML = `
            <div class="change-row">
              ${field('Role Title', '<input class="form-input" id="chgTitle" placeholder="e.g. Outreach Coordinator">')}
              ${field('Role Description', '<textarea class="form-textarea" id="chgDesc" rows="2"></textarea>')}
              ${field('Capabilities (comma-separated slugs)', '<input class="form-input" id="chgCaps" placeholder="articles.approve, events.publish">')}
              ${field('Assign to Member', memberSelect('chgUser'))}
            </div>`;
          break;
        case 'appoint':
          div.innerHTML = `
            <div class="change-row">
              ${field('Role', roleSelect('chgRole'))}
              ${field('Member', memberSelect('chgUser'))}
              ${field('Effective To (YYYY-MM-DD, blank = permanent)', '<input type="date" class="form-input" id="chgEffTo">')}
            </div>`;
          break;
        case 'remove':
          div.innerHTML = `
            <div class="change-row">
              ${field('Role', roleSelect('chgRole'))}
              ${field('Member', memberSelect('chgUser'))}
            </div>`;
          break;
        case 'cap_assign':
        case 'cap_revoke':
          div.innerHTML = `
            <div class="change-row">
              ${field('Role', roleSelect('chgRole'))}
              ${field('Capability ID', '<input type="number" class="form-input" id="chgCapId" min="1">')}
            </div>`;
          break;
        case 'programme_create':
          div.innerHTML = `
            <div class="change-row">
              ${field('Programme Title', '<input class="form-input" id="chgTitle">')}
              ${field('Description', '<textarea class="form-textarea" id="chgDesc" rows="2"></textarea>')}
              ${field('Lead', memberSelect('chgUser'))}
            </div>`;
          break;
        case 'policy_adopt':
          div.innerHTML = `
            <div class="change-row">
              ${field('Policy Title', '<input class="form-input" id="chgTitle">')}
              ${field('Policy Text', '<textarea class="form-textarea" id="chgDesc" rows="3"></textarea>')}
            </div>`;
          break;
      }
    }

    function buildChanges() {
      const type = document.getElementById('resType').value;
      const changes = [];
      switch (type) {
        case 'role_create': {
          const caps = (document.getElementById('chgCaps').value || '').split(',').map(s => s.trim()).filter(Boolean);
          const payload = {
            title: document.getElementById('chgTitle').value,
            description: document.getElementById('chgDesc').value,
            capability_ids: caps,
          };
          const uid = document.getElementById('chgUser').value;
          if (uid) payload.assign_to_user_id = parseInt(uid, 10);
          changes.push({ change_type: 'role_create', payload });
          break;
        }
        case 'appoint': {
          changes.push({ change_type: 'appoint', payload: {
            role_id: parseInt(document.getElementById('chgRole').value, 10),
            user_id: parseInt(document.getElementById('chgUser').value, 10),
            effective_to: document.getElementById('chgEffTo').value || null,
          }});
          break;
        }
        case 'remove':
          changes.push({ change_type: 'remove', payload: {
            role_id: parseInt(document.getElementById('chgRole').value, 10),
            user_id: parseInt(document.getElementById('chgUser').value, 10),
          }});
          break;
        case 'cap_assign':
        case 'cap_revoke':
          changes.push({ change_type: type, payload: {
            role_id: parseInt(document.getElementById('chgRole').value, 10),
            capability_id: parseInt(document.getElementById('chgCapId').value, 10),
          }});
          break;
        case 'programme_create':
          changes.push({ change_type: 'programme_create', payload: {
            title: document.getElementById('chgTitle').value,
            description: document.getElementById('chgDesc').value,
            lead_id: parseInt(document.getElementById('chgUser').value, 10) || null,
          }});
          break;
        case 'policy_adopt':
          changes.push({ change_type: 'policy_adopt', payload: {
            title: document.getElementById('chgTitle').value,
            description: document.getElementById('chgDesc').value,
          }});
          break;
      }
      return changes;
    }

    async function createResolution() {
      const type = document.getElementById('resType').value;
      if (!type) { alert('Select a resolution type'); return; }
      const title = document.getElementById('resTitle').value;
      if (!title) { alert('Enter a title'); return; }
      try {
        const body = {
          title,
          description: document.getElementById('resDesc').value,
          type,
          quorum: parseInt(document.getElementById('resQuorum').value, 10) || 0,
          majority: document.getElementById('resMajority').value,
          changes: buildChanges(),
        };
        const res = await api.createResolution(body);
        document.getElementById('createStatus').textContent = 'Created — submit and begin voting.';
        document.getElementById('resTitle').value = '';
        document.getElementById('resDesc').value = '';
        await refreshResolutions();
        const r = document.getElementById('res-' + res.id);
        if (r) r.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } catch(e) { alert(e.error || 'Failed'); }
    }

    async function submitResolution(id) {
      try { await api.submitResolution(id); await refreshResolutions(); }
      catch(e) { alert(e.error || 'Failed'); }
    }

    async function beginVoting(id) {
      try { await api.beginVoting(id); await refreshResolutions(); }
      catch(e) { alert(e.error || 'Failed'); }
    }

    async function castVote(id, value) {
      try { await api.castVote(id, value); await refreshResolutions(); }
      catch(e) { alert(e.error || 'Failed'); }
    }

    async function refreshResolutions() {
      const resolutions = await api.getResolutions().catch(() => []);
      const div = document.getElementById('resolutionsList');
      if (!resolutions.length) { div.innerHTML = '<p class="text-dim">No resolutions yet.</p>'; return; }
      div.innerHTML = resolutions.map(r => `
        <div class="res-row" id="res-${r.id}">
          <div class="flex-between">
            <div>
              <strong>${esc(r.title)}</strong>
              <span class="badge badge-${esc(r.status)}" style="margin-left:0.5rem">${esc(r.status)}</span>
              <span class="text-sm text-dim" style="margin-left:0.5rem">${esc(r.code)}</span>
            </div>
            <span class="text-sm text-dim">${esc(r.proposer_name || '—')}</span>
          </div>
          ${r.description ? `<p class="text-sm text-dim mt-1">${esc(r.description)}</p>` : ''}
          <div class="vote-tally mt-2">
            <span>For <strong class="text-success">${r.votes_for}</strong></span>
            <span>Against <strong class="text-danger">${r.votes_against}</strong></span>
            <span>Abstain <strong>${r.votes_abstain}</strong></span>
            <span>Quorum <strong>${r.quorum || 'any'}</strong></span>
            <span>Majority <strong>${esc(r.majority)}</strong></span>
          </div>
          <div class="mt-2 flex" style="gap:0.5rem;flex-wrap:wrap">
            ${r.status === 'draft' ? `<button class="btn btn-primary btn-sm" onclick="submitResolution(${r.id})">Submit</button>` : ''}
            ${r.status === 'submitted' && api.hasCap('resolutions.manage') ? `<button class="btn btn-primary btn-sm" onclick="beginVoting(${r.id})">Begin Voting</button>` : ''}
            ${r.status === 'voting' && api.hasCap('resolutions.vote') ? `
              <button class="btn btn-success btn-sm" onclick="castVote(${r.id}, 'for')">Vote For</button>
              <button class="btn btn-outline btn-sm" onclick="castVote(${r.id}, 'against')">Vote Against</button>
              <button class="btn btn-outline btn-sm" onclick="castVote(${r.id}, 'abstain')">Abstain</button>
            ` : ''}
            ${r.status === 'applied' ? `<span class="text-sm text-success">Auto-applied ${esc(r.applied_at)}</span>` : ''}
            ${r.status === 'failed' ? `<span class="text-sm text-danger">Defeated</span>` : ''}
          </div>
        </div>
      `).join('');
    }

    function esc(s) { return s ? s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }
    function updateNavUser() {
      const el = document.getElementById('navUser');
      el.innerHTML = api._user
        ? `<a href="/dashboard" class="btn btn-outline btn-sm">${esc(api._user.name)}</a>`
        : `<a href="/login" class="btn btn-primary btn-sm">Login</a>`;
    }

    load();
    setInterval(() => { if (api._user) refreshResolutions().catch(() => {}); }, 10000);
  </script>
</body>
</html>