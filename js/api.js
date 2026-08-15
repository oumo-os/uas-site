// UAS Institutional Platform — API Client
// Thin wrapper around fetch with auth, JSON, and error handling

// App base path: "/uas" when served under a subdirectory, "" at domain root.
// Derived from the script's own URL so it works anywhere.
(function () {
  let base = '';
  try {
    const src = (document.currentScript && document.currentScript.src) || '';
    const m = src.match(/^https?:\/\/[^/]+(\/[^?]*)?\/js\/api\.js/);
    if (m) base = (m[1] || '').replace(/\/$/, '');
  } catch (e) {}
  window.UAS_BASE = base;
  window.API_BASE = window.location.origin + base + '/api';
  window.ua = function (path) { return base + path; };
})();

const API_BASE = window.API_BASE;

const api = {
  _token: null,
  _user: null,
  _capabilities: [],

  async request(path, opts = {}) {
    const url = API_BASE + path;
    const headers = { 'Content-Type': 'application/json' };
    const res = await fetch(url, {
      method: opts.method || 'GET',
      headers,
      body: opts.body ? JSON.stringify(opts.body) : undefined,
      credentials: 'same-origin',
    });
    const data = await res.json();
    if (!res.ok) throw { status: res.status, ...data };
    return data;
  },

  // Auth
  async login(email, password) {
    const data = await this.request('/auth/login', {
      method: 'POST',
      body: { email, password },
    });
    this._user = data.user;
    this._capabilities = data.capabilities;
    return data;
  },

  async register(name, email, password, extra = {}) {
    const data = await this.request('/auth/register', {
      method: 'POST',
      body: { name, email, password, ...extra },
    });
    this._user = data.user;
    return data;
  },

  async me() {
    const data = await this.request('/auth/me');
    this._user = data.user;
    this._capabilities = data.capabilities;
    return data;
  },

  async logout() {
    await this.request('/auth/logout', { method: 'POST' });
    this._user = null;
    this._capabilities = [];
  },

  hasCap(cap) {
    return this._capabilities.includes(cap);
  },

  // Members
  async getMembers() { return this.request('/members'); },
  async getMember(id) { return this.request('/members/' + id); },
  async approveMember(userId, status) { return this.request('/members', { method: 'POST', body: { user_id: userId, status } }); },

  // Roles
  async getRoles() { return this.request('/roles'); },
  async createRole(data) { return this.request('/roles', { method: 'POST', body: data }); },
  async getRoleCapabilities(roleId) { return this.request('/roles/' + roleId + '/capabilities'); },

  // Capabilities
  async getCapabilities() { return this.request('/capabilities'); },

  // Authority
  async getAuthorityTrace(userId) { return this.request('/authority/' + userId); },

  // Resolutions
  async getResolutions(filters = {}) {
    const qs = new URLSearchParams(filters).toString();
    return this.request('/resolutions' + (qs ? '?' + qs : ''));
  },
  async getResolution(id) { return this.request('/resolutions/' + id); },
  async createResolution(data) { return this.request('/resolutions', { method: 'POST', body: data }); },
  async submitResolution(id) { return this.request('/resolutions/' + id + '/submit', { method: 'POST' }); },
  async beginVoting(id) { return this.request('/resolutions/' + id + '/begin-voting', { method: 'POST' }); },
  async castVote(id, value, rationale) {
    return this.request('/resolutions/' + id + '/vote', { method: 'POST', body: { value, rationale } });
  },

  // Programmes
  async getProgrammes() { return this.request('/programmes'); },
  async createProgramme(data) { return this.request('/programmes', { method: 'POST', body: data }); },

  // Projects
  async getProjects() { return this.request('/projects'); },
  async createProject(data) { return this.request('/projects', { method: 'POST', body: data }); },

  // Events
  async getEvents() { return this.request('/events'); },
  async createEvent(data) { return this.request('/events', { method: 'POST', body: data }); },
  async approveEvent(id) { return this.request('/events/' + id + '/approve', { method: 'POST' }); },
  async publishEvent(id) { return this.request('/events/' + id + '/publish', { method: 'POST' }); },
  async rsvpEvent(id) { return this.request('/events/' + id + '/rsvp', { method: 'POST' }); },
  async cancelRsvp(id) { return this.request('/events/' + id + '/rsvp', { method: 'DELETE' }); },
  async getEventRsvps(id) { return this.request('/events/' + id + '/rsvps'); },

  // Articles
  async getArticles() { return this.request('/articles'); },
  async createArticle(data) { return this.request('/articles', { method: 'POST', body: data }); },
  async approveArticle(id) { return this.request('/articles/' + id + '/approve', { method: 'POST' }); },
  async rejectArticle(id, reason) { return this.request('/articles/' + id + '/reject', { method: 'POST', body: { reason } }); },
  async publishArticle(id) { return this.request('/articles/' + id + '/publish', { method: 'POST' }); },

  // Documents
  async getDocuments() { return this.request('/documents'); },
  async uploadDocument(data) { return this.request('/documents', { method: 'POST', body: data }); },

  // Finance
  async getFinance() { return this.request('/finance'); },
  async recordFinance(data) { return this.request('/finance', { method: 'POST', body: data }); },

  // Assignments
  async getAssignments() { return this.request('/assignments'); },
  async createAssignment(data) { return this.request('/assignments', { method: 'POST', body: data }); },
  async getMyAssignments() { return this.request('/assignments/mine'); },
  async startAssignment(id) { return this.request('/assignments/' + id + '/start', { method: 'POST' }); },
  async submitAssignment(id, evidence) { return this.request('/assignments/' + id + '/submit', { method: 'POST', body: { evidence } }); },
  async completeAssignment(id) { return this.request('/assignments/' + id + '/complete', { method: 'POST' }); },
  async changePassword(data) { return this.request('/auth/password', { method: 'PUT', body: data }); },

  // Calendar
  async getCalendar() { return this.request('/calendar'); },
  async createCalendarItem(data) { return this.request('/calendar', { method: 'POST', body: data }); },

  // Dashboard
  async getDashboard() { return this.request('/dashboard'); },

  // Pending
  async getPending() { return this.request('/pending'); },

  // Profile
  async getProfile() { return this.request('/profile'); },
  async updateProfile(data) { return this.request('/profile', { method: 'PUT', body: data }); },

  // Public
  async getPublicArticles() { return this.request('/public/articles'); },
  async getPublicEvents() { return this.request('/public/events'); },
  async getPublicProgrammes() { return this.request('/public/programmes'); },
  async getPublicProgramme(id) { return this.request('/public/programmes/' + id); },
  async getPublicDocuments() { return this.request('/public/documents'); },

  // Partners & Links
  async getPartners() { return this.request('/partners'); },
  async createPartner(data) { return this.request('/partners', { method: 'POST', body: data }); },
  async deletePartner(id) { return this.request('/partners/' + id, { method: 'DELETE' }); },
  async getLinks() { return this.request('/links'); },
  async createLink(data) { return this.request('/links', { method: 'POST', body: data }); },
  async deleteLink(id) { return this.request('/links/' + id, { method: 'DELETE' }); },

  // Audit
  async getAuditLog(filters = {}) {
    const qs = new URLSearchParams(filters).toString();
    return this.request('/audit' + (qs ? '?' + qs : ''));
  },
};

// --- Theme toggle (dark/light) ---
(function () {
  const saved = localStorage.getItem('uas-theme');
  if (saved) document.documentElement.setAttribute('data-theme', saved);

  function addToggle() {
    const hosts = [document.querySelector('.nav-user')];
    hosts.forEach(host => {
      if (!host || host.querySelector('.theme-toggle')) return;
      const btn = document.createElement('button');
      btn.className = 'theme-toggle btn btn-outline btn-sm';
      btn.type = 'button';
      btn.title = 'Toggle light/dark mode';
      btn.textContent = document.documentElement.getAttribute('data-theme') === 'light' ? '☀️' : '🌙';
      btn.onclick = () => {
        const next = document.documentElement.getAttribute('data-theme') === 'light' ? '' : 'light';
        if (next) document.documentElement.setAttribute('data-theme', next);
        else document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('uas-theme', next);
        btn.textContent = next === 'light' ? '☀️' : '🌙';
      };
      host.appendChild(btn);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', addToggle);
  else addToggle();
})();

// --- Brand emblem ---
// Adds the UAS emblem to every .nav-brand (favicon is declared statically per page).
(function () {
  function init() {
    const brand = document.querySelector('.nav-brand');
    if (brand && !brand.querySelector('img')) {
      const img = document.createElement('img');
      img.src = ua('/img/uas-emblem.png');
      img.alt = 'UAS emblem';
      img.className = 'nav-logo';
      brand.prepend(img);
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

// --- Mobile nav (hamburger) ---
// Injects a burger button into every .nav on small screens; no per-page edits.
(function () {
  function init() {
    const nav = document.querySelector('.nav');
    if (!nav) return;
    const container = nav.querySelector('.container');
    const links = nav.querySelector('.nav-links');
    const target = container || nav;
    if (!links || target.querySelector('.nav-burger')) return;
    const burger = document.createElement('button');
    burger.className = 'nav-burger';
    burger.type = 'button';
    burger.setAttribute('aria-label', 'Toggle navigation');
    burger.innerHTML = '&#9776;';
    burger.onclick = () => nav.classList.toggle('open');
    target.appendChild(burger);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

// --- Notification bell ---
// Injects a bell with unread badge + dropdown into every .nav for logged-in users.
(function () {
  const state = { items: [], unread: 0, open: false, loaded: false };

  function fmtTime(iso) {
    const d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d)) return '';
    const s = (Date.now() - d.getTime()) / 1000;
    if (s < 60) return 'just now';
    if (s < 3600) return Math.floor(s / 60) + 'm ago';
    if (s < 86400) return Math.floor(s / 3600) + 'h ago';
    if (s < 604800) return Math.floor(s / 86400) + 'd ago';
    return d.toLocaleDateString();
  }

  function render() {
    const panel = document.getElementById('uas-bell-panel');
    if (!panel) return;
    let list = panel.querySelector('.bell-items');
    if (!list) {
      list = document.createElement('div');
      list.className = 'bell-items';
      panel.appendChild(list);
    }
    if (!state.items.length) {
      list.innerHTML = '<div class="bell-empty">You have no notifications.</div>';
    } else {
      list.innerHTML = state.items.map(function (n) {
        const href = n.link ? ua(n.link) : null;
        const inner = '<div class="bell-item-title' + (n.is_read ? '' : ' unread') + '">' + escapeHtml(n.title) + '</div>'
          + (n.body ? '<div class="bell-item-body">' + escapeHtml(n.body) + '</div>' : '')
          + '<div class="bell-item-time">' + fmtTime(n.created_at) + '</div>';
        return '<a class="bell-item' + (n.is_read ? '' : ' unread') + '"'
          + (href ? ' href="' + href + '"' : '') + ' data-id="' + n.id + '">' + inner + '</a>';
      }).join('');
    }
    const badge = document.getElementById('uas-bell-badge');
    if (badge) {
      badge.textContent = state.unread > 99 ? '99+' : String(state.unread);
      badge.style.display = state.unread ? 'inline-block' : 'none';
    }
  }

  function markRead(id) {
    fetch(API_BASE + '/notifications/' + id + '/read', { method: 'POST' }).catch(function () {});
    state.items.forEach(function (n) { if (n.id === id) n.is_read = 1; });
    state.unread = Math.max(0, state.unread - 1);
    render();
  }

  function markAllRead() {
    fetch(API_BASE + '/notifications/read-all', { method: 'POST' }).catch(function () {});
    state.items.forEach(function (n) { n.is_read = 1; });
    state.unread = 0;
    render();
  }

  function toggle(ev) {
    ev.stopPropagation();
    const panel = document.getElementById('uas-bell-panel');
    if (!panel) return;
    state.open = !state.open;
    panel.style.display = state.open ? 'block' : 'none';
    if (state.open && !state.loaded) {
      state.loaded = true;
      fetch(API_BASE + '/notifications')
        .then(function (r) { if (!r.ok) throw new Error('unauth'); return r.json(); })
        .then(function (d) { state.items = d.items || []; state.unread = d.unread || 0; render(); })
        .catch(function () { document.getElementById('uas-bell-wrap').style.display = 'none'; });
    }
  }

  function init() {
    const nav = document.querySelector('.nav');
    if (!nav) return;
    const container = nav.querySelector('.container');
    const target = container || nav;
    if (target.querySelector('#uas-bell-wrap')) return;
    const wrap = document.createElement('div');
    wrap.id = 'uas-bell-wrap';
    wrap.className = 'nav-bell';
    wrap.innerHTML = '<button class="bell-btn" type="button" aria-label="Notifications">&#128276;'
      + '<span class="bell-badge" id="uas-bell-badge" style="display:none"></span></button>'
      + '<div class="bell-panel" id="uas-bell-panel"></div>';
    wrap.querySelector('.bell-btn').onclick = toggle;
    wrap.querySelector('#uas-bell-panel').onclick = function (ev) {
      const item = ev.target.closest('.bell-item');
      if (item) { ev.preventDefault(); markRead(Number(item.dataset.id)); if (item.getAttribute('href')) location.href = item.getAttribute('href'); }
    };
    target.appendChild(wrap);
    document.addEventListener('click', function (ev) {
      if (state.open && !wrap.contains(ev.target)) { state.open = false; document.getElementById('uas-bell-panel').style.display = 'none'; }
    });
    const readAll = document.createElement('button');
    readAll.type = 'button';
    readAll.className = 'bell-read-all';
    readAll.textContent = 'Mark all as read';
    readAll.onclick = markAllRead;
    wrap.querySelector('#uas-bell-panel').appendChild(readAll);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

// --- Base-path link rewriting ---
// Prefixes internal absolute URLs ("/programmes") with the app base ("/uas").
// Watches the DOM so links added later by scripts are rewritten too.
(function () {
  if (!window.UAS_BASE) return;

  function rewrite(el) {
    const tag = el.tagName;
    let attr = null;
    if (tag === 'A' || tag === 'LINK') attr = 'href';
    else if (tag === 'FORM') attr = 'action';
    else if (tag === 'SCRIPT' || tag === 'IMG') attr = 'src';
    if (!attr) return;
    const v = el.getAttribute(attr);
    if (!v || v.indexOf('://') !== -1 || v.startsWith('//') || v.startsWith('#')
      || v.startsWith('mailto:') || v.startsWith('tel:') || v.startsWith('javascript:')
      || v.startsWith('data:')) return;
    if (v.startsWith('/')) {
      if (v === window.UAS_BASE || v.startsWith(window.UAS_BASE + '/')) return;
      el.setAttribute(attr, window.UAS_BASE + v);
    }
  }

  function scan(root) {
    (root.querySelectorAll ? root.querySelectorAll('a[href], form[action], img[src], link[href], script[src]') : []).forEach(rewrite);
  }

  scan(document);
  if (window.MutationObserver) {
    const mo = new MutationObserver(muts => {
      muts.forEach(m => m.addedNodes.forEach(n => { if (n.nodeType === 1) scan(n); }));
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });
  }
})();
