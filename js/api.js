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

// SVG Icon system
const ICONS = {
  user:      '<svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
  calendar:  '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
  clock:     '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  mapPin:    '<svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
  users:     '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  file:      '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
  link:      '<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
  mail:      '<svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
  building:  '<svg viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="9" y1="6" x2="9" y2="6.01"/><line x1="15" y1="6" x2="15" y2="6.01"/><line x1="9" y1="10" x2="9" y2="10.01"/><line x1="15" y1="10" x2="15" y2="10.01"/><line x1="9" y1="14" x2="9" y2="14.01"/><line x1="15" y1="14" x2="15" y2="14.01"/><line x1="9" y1="22" x2="9" y2="18"/><line x1="15" y1="22" x2="15" y2="18"/><line x1="9" y1="18" x2="15" y2="18"/></svg>',
  briefcase: '<svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
  handshake: '<svg viewBox="0 0 24 24"><path d="M11 17a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1 1 1 0 0 1 1-1h4a1 1 0 0 1 1 1z"/><path d="M17 17a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1 1 1 0 0 1 1-1h4a1 1 0 0 1 1 1z"/><path d="M9 17h6"/><path d="M12 17v4"/><path d="M8 5l4-3 4 3"/><path d="M8 5v6a4 4 0 0 0 8 0V5"/></svg>',
  telescope: '<svg viewBox="0 0 24 24"><path d="M6 21l6-6"/><path d="M21 3l-9 9"/><path d="M15 3h6v6"/><circle cx="18" cy="6" r="3"/></svg>',
  target:    '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
  rocket:    '<svg viewBox="0 0 24 24"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>',
  thumbsUp:  '<svg viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3H14z"/><path d="M7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>',
  thumbsDown:'<svg viewBox="0 0 24 24"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3H10z"/><path d="M17 2h3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-3"/></svg>',
  pause:     '<svg viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>',
  bell:      '<svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
};

function icon(name, cls) {
  const svg = ICONS[name] || '';
  return '<span class="icon-inline' + (cls ? ' ' + cls : '') + '">' + svg + '</span>';
}

const api = {
  _token: null,
  _user: null,
  _capabilities: [],

  _setCaps(data) {
    this._capabilities = (data.capabilities || []).map(c => typeof c === 'string' ? c : c.slug).filter(Boolean);
  },

  async request(path, opts = {}) {
    const url = API_BASE + path;
    const headers = { 'Content-Type': 'application/json' };
    const res = await fetch(url, {
      method: opts.method || 'GET',
      headers,
      body: opts.body ? JSON.stringify(opts.body) : undefined,
      credentials: 'same-origin',
    });
    let data;
    try { data = await res.json(); } catch (jsonErr) {
      const text = await res.text().catch(() => '');
      throw { status: res.status, error: text.slice(0, 200) || 'Invalid server response' };
    }
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
    this._setCaps(data);
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
    this._setCaps(data);
    return data;
  },

  async logout() {
    await this.request('/auth/logout', { method: 'POST' });
    this._user = null;
    this._capabilities = [];
  },

  async loginAs(userId) {
    const data = await this.request('/admin/login-as', { method: 'POST', body: { user_id: userId } });
    this._user = data.user;
    this._setCaps(data);
    this._roles = data.roles || [];
    return data;
  },

  hasCap(cap) {
    return this._capabilities.includes(cap);
  },

  currentUser() {
    return this._user || {};
  },

  // Members
  async getMembers() { return this.request('/members'); },
  async getMembersGrouped() { return this.request('/members/grouped'); },
  async getMember(id) { return this.request('/members/' + id); },
  async approveMember(userId, status) { return this.request('/members', { method: 'POST', body: { user_id: userId, status } }); },

  // Roles
  async getRoles() { return this.request('/roles'); },
  async getRole(id) { return this.request('/roles/' + id); },
  async createRole(data) { return this.request('/roles', { method: 'POST', body: data }); },
  async updateRole(id, data) { return this.request('/roles/' + id, { method: 'PUT', body: data }); },
  async deleteRole(id) { return this.request('/roles/' + id, { method: 'DELETE' }); },
  async getRoleCapabilities(roleId) { return this.request('/roles/' + roleId + '/capabilities'); },
  async addRoleCapability(roleId, capSlug, scopeType, scopeId) {
    return this.request('/roles/' + roleId + '/capabilities', { method: 'POST', body: { capability: capSlug, scope_type: scopeType, scope_id: scopeId } });
  },
  async removeRoleCapability(roleId, capId) {
    return this.request('/roles/' + roleId + '/capabilities/' + capId, { method: 'DELETE' });
  },
  async getRoleUsers(roleId) { return this.request('/roles/' + roleId + '/users'); },
  async assignRoleToUser(roleId, userId, effectiveTo) {
    return this.request('/roles/' + roleId + '/users', { method: 'POST', body: { user_id: userId, effective_to: effectiveTo } });
  },
  async bulkAssignRole(roleId, userIds, effectiveTo) {
    return this.request('/roles/' + roleId + '/users/bulk', { method: 'POST', body: { user_ids: userIds, effective_to: effectiveTo } });
  },
  async revokeRoleFromUser(roleId, userId) {
    return this.request('/roles/' + roleId + '/users/' + userId, { method: 'DELETE' });
  },

  // Users (admin)
  async getUsers() { return this.request('/users'); },
  async getUserRoles(userId) { return this.request('/users/' + userId + '/roles'); },

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

  // Resolution Comments
  async getResolutionComments(id) { return this.request('/resolutions/' + id + '/comments'); },
  async addResolutionComment(id, body, parentId) {
    return this.request('/resolutions/' + id + '/comments', { method: 'POST', body: { body, parent_id: parentId } });
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

  // File Upload
  async uploadFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    const url = API_BASE + '/upload';
    const res = await fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' });
    const data = await res.json();
    if (!res.ok) throw { status: res.status, ...data };
    return data;
  },

  // Finance
  async getFinance(params) { const qs = params ? '?' + new URLSearchParams(params).toString() : ''; return this.request('/finance' + qs); },
  async recordFinance(data) { return this.request('/finance', { method: 'POST', body: data }); },
  async updateFinance(id, data) { return this.request('/finance/' + id, { method: 'PUT', body: data }); },
  async deleteFinance(id) { return this.request('/finance/' + id, { method: 'DELETE' }); },

  // Budget Items
  async getBudgetItems() { return this.request('/budget-items'); },
  async getBudgetItem(id) { return this.request('/budget-items/' + id); },
  async createBudgetItem(data) { return this.request('/budget-items', { method: 'POST', body: data }); },
  async updateBudgetItem(id, data) { return this.request('/budget-items/' + id, { method: 'PUT', body: data }); },
  async deleteBudgetItem(id) { return this.request('/budget-items/' + id, { method: 'DELETE' }); },

  // Working Groups
  async getWorkingGroups() { return this.request('/working-groups'); },
  async getWorkingGroupsWithMembers() { return this.request('/working-groups/with-members'); },
  async createWorkingGroup(data) { return this.request('/working-groups', { method: 'POST', body: data }); },
  async getWorkingGroupMembers(groupId) { return this.request('/working-groups/' + groupId + '/members'); },
  async addWorkingGroupMember(groupId, userId) {
    return this.request('/working-groups/' + groupId + '/members', { method: 'POST', body: { user_id: userId } });
  },
  async updateWorkingGroup(id, data) { return this.request('/working-groups/' + id, { method: 'PUT', body: data }); },
  async deleteWorkingGroup(id) { return this.request('/working-groups/' + id, { method: 'DELETE' }); },
  async removeWorkingGroupMember(groupId, userId) {
    return this.request('/working-groups/' + groupId + '/members/' + userId, { method: 'DELETE' });
  },

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
  async getActivity(limit) { return this.request('/activity' + (limit ? '?limit=' + limit : '')); },

  // Pending
  async getPending(filters = {}) {
    const qs = new URLSearchParams(filters).toString();
    return this.request('/pending' + (qs ? '?' + qs : ''));
  },

  // Profile
  async getProfile() { return this.request('/profile'); },
  async updateProfile(data) { return this.request('/profile', { method: 'PUT', body: data }); },

  // Public
  async getPublicArticles() { return this.request('/public/articles'); },
  async getPublicEvents() { return this.request('/public/events'); },
  async getPublicProgrammes() { return this.request('/public/programmes'); },
  async getPublicProgramme(id) { return this.request('/public/programmes/' + id); },
  async getPublicDocuments() { return this.request('/public/documents'); },
  async getFinanceSummary(groupBy, dateParams) { const params = {}; if (groupBy) params.group_by = groupBy; if (dateParams?.from) params.date_from = dateParams.from; if (dateParams?.to) params.date_to = dateParams.to; const qs = Object.keys(params).length ? '?' + new URLSearchParams(params).toString() : ''; return this.request('/finance/summary' + qs); },
  async getMemberDocuments() { return this.request('/member/documents'); },
  async getMemberAssignments() { return this.request('/member/assignments'); },
  async getMemberCalendar() { return this.request('/member/calendar'); },

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

// --- FormBuilder: shared form rendering with validation ---
// Field types: text, email, password, number, date, datetime-local, textarea, select, multiselect, rich-text, file, hidden
// Option: { label, value, group? }
const FormBuilder = {
  render(fields, opts = {}) {
    const id = opts.id || 'form-' + Math.random().toString(36).slice(2, 8);
    const submitLabel = opts.submitLabel || 'Submit';
    const submitClass = opts.submitClass || 'btn btn-primary';
    const layout = opts.layout || 'stacked'; // stacked | inline
    let html = `<form id="${id}" class="uas-form" onsubmit="return false" novalidate>`;
    fields.forEach(f => {
      html += this._field(id, f);
    });
    html += `<div class="form-actions"><button type="submit" class="${submitClass}" id="${id}-submit">${submitLabel}</button></div>`;
    html += '</form>';
    return html;
  },

  _field(formId, f) {
    const fid = formId + '-' + f.name;
    const req = f.required ? ' <span class="form-required">*</span>' : '';
    const reqAttr = f.required ? ' required' : '';
    const ph = f.placeholder ? ` placeholder="${esc(f.placeholder)}"` : '';
    const help = f.help ? `<div class="form-help">${esc(f.help)}</div>` : '';
    let input = '';
    switch (f.type) {
      case 'select':
        input = `<select class="form-select" id="${fid}"${reqAttr}>`;
        if (f.placeholder) input += `<option value="">${esc(f.placeholder)}</option>`;
        (f.options || []).forEach(o => {
          if (o.group) return;
          input += `<option value="${esc(o.value)}">${esc(o.label)}</option>`;
        });
        input += '</select>';
        break;
      case 'multiselect':
        input = `<select class="form-select" id="${fid}" multiple size="${Math.min((f.options||[]).length, 6)}"${reqAttr}>`;
        (f.options || []).forEach(o => {
          input += `<option value="${esc(o.value)}">${esc(o.label)}</option>`;
        });
        input += '</select>';
        break;
      case 'textarea':
        input = `<textarea class="form-textarea" id="${fid}" rows="${f.rows || 4}"${reqAttr}${ph}>${esc(f.value || '')}</textarea>`;
        break;
      case 'rich-text':
        input = `<div class="rte-wrap" id="${fid}-wrap"><div class="rte-toolbar" id="${fid}-toolbar"></div><div class="rte-editor" id="${fid}" contenteditable="true"${reqAttr}>${f.value || ''}</div></div>`;
        break;
      case 'file':
        const accept = f.accept ? ` accept="${esc(f.accept)}"` : '';
        input = `<input type="file" class="form-input" id="${fid}"${accept}${reqAttr}>`;
        break;
      case 'hidden':
        input = `<input type="hidden" id="${fid}" value="${esc(f.value || '')}">`;
        break;
      default:
        const extra = f.min !== undefined ? ` min="${f.min}"` : '';
        const step = f.step ? ` step="${f.step}"` : '';
        input = `<input type="${f.type || 'text'}" class="form-input" id="${fid}"${reqAttr}${ph}${extra}${step} value="${esc(f.value || '')}">`;
    }
    return `<div class="form-group" data-field="${f.name}"><label class="form-label" for="${fid}">${esc(f.label)}${req}</label>${input}<div class="form-error" id="${fid}-error"></div>${help}</div>`;
  },

  init(formId, fields, onSubmit) {
    const form = document.getElementById(formId);
    if (!form) return;
    // Init rich text editors
    fields.filter(f => f.type === 'rich-text').forEach(f => {
      const fid = formId + '-' + f.name;
      RichTextEditor.init(fid + '-toolbar', fid);
    });
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const data = {};
      let valid = true;
      fields.forEach(f => {
        const el = document.getElementById(formId + '-' + f.name);
        const errEl = document.getElementById(formId + '-' + f.name + '-error');
        if (!el) return;
        if (f.type === 'rich-text') {
          data[f.name] = el.innerHTML.trim();
        } else if (f.type === 'multiselect') {
          data[f.name] = Array.from(el.selectedOptions).map(o => o.value);
        } else if (f.type === 'file') {
          data[f.name] = el.files[0] || null;
        } else if (f.type === 'number') {
          data[f.name] = el.value ? parseFloat(el.value) : null;
        } else {
          data[f.name] = el.value || null;
        }
        // Validate
        if (errEl) errEl.textContent = '';
        el.classList.remove('input-error');
        if (f.required && !data[f.name] && data[f.name] !== 0) {
          if (errEl) errEl.textContent = f.label + ' is required';
          el.classList.add('input-error');
          valid = false;
        }
        if (f.validate && data[f.name]) {
          const msg = f.validate(data[f.name]);
          if (msg) {
            if (errEl) errEl.textContent = msg;
            el.classList.add('input-error');
            valid = false;
          }
        }
      });
      if (!valid) return;
      const btn = document.getElementById(formId + '-submit');
      if (btn) { btn.disabled = true; btn.dataset.origText = btn.textContent; btn.textContent = 'Saving...'; }
      try {
        await onSubmit(data);
      } catch (err) {
        alert(err.error || err.message || 'Failed');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = btn.dataset.origText || 'Submit'; }
      }
    });
  },

  getValues(formId, fields) {
    const data = {};
    fields.forEach(f => {
      const el = document.getElementById(formId + '-' + f.name);
      if (!el) return;
      if (f.type === 'rich-text') data[f.name] = el.innerHTML.trim();
      else if (f.type === 'multiselect') data[f.name] = Array.from(el.selectedOptions).map(o => o.value);
      else if (f.type === 'file') data[f.name] = el.files[0] || null;
      else if (f.type === 'number') data[f.name] = el.value ? parseFloat(el.value) : null;
      else data[f.name] = el.value || null;
    });
    return data;
  },

  clearErrors(formId) {
    document.querySelectorAll(`#${formId} .form-error`).forEach(e => e.textContent = '');
    document.querySelectorAll(`#${formId} .input-error`).forEach(e => e.classList.remove('input-error'));
  },
};

// --- RichTextEditor: lightweight contenteditable toolbar ---
const RichTextEditor = {
  init(toolbarId, editorId) {
    const toolbar = document.getElementById(toolbarId);
    const editor = document.getElementById(editorId);
    if (!toolbar || !editor) return;
    const cmds = [
      { cmd: 'bold', icon: '<strong>B</strong>', title: 'Bold' },
      { cmd: 'italic', icon: '<em>I</em>', title: 'Italic' },
      { cmd: 'underline', icon: '<u>U</u>', title: 'Underline' },
      { cmd: 'insertUnorderedList', icon: '&#8226;', title: 'Bullet List' },
      { cmd: 'insertOrderedList', icon: '1.', title: 'Numbered List' },
      { cmd: 'formatBlock', val: 'h3', icon: 'H3', title: 'Heading' },
      { cmd: 'formatBlock', val: 'p', icon: 'P', title: 'Paragraph' },
      { cmd: 'createLink', icon: '<svg viewBox="0 0 24 24" width="14" height="14"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>', title: 'Link' },
    ];
    toolbar.innerHTML = cmds.map((c, i) =>
      `<button type="button" class="rte-btn" data-cmd="${c.cmd}" data-val="${c.val || ''}" title="${c.title}">${c.icon}</button>`
    ).join('');
    toolbar.addEventListener('click', e => {
      const btn = e.target.closest('.rte-btn');
      if (!btn) return;
      e.preventDefault();
      const cmd = btn.dataset.cmd;
      const val = btn.dataset.val || null;
      if (cmd === 'createLink') {
        const url = prompt('Enter URL:');
        if (url) document.execCommand(cmd, false, url);
      } else {
        document.execCommand(cmd, false, val);
      }
      editor.focus();
    });
  },

  getHtml(editorId) {
    const el = document.getElementById(editorId);
    return el ? el.innerHTML.trim() : '';
  },

  setHtml(editorId, html) {
    const el = document.getElementById(editorId);
    if (el) el.innerHTML = html;
  },
};

// --- Theme toggle (dark/light) ---
(function () {
  const saved = localStorage.getItem('uas-theme');
  if (saved) document.documentElement.setAttribute('data-theme', saved);
  window._isDark = function () {
    return document.documentElement.getAttribute('data-theme') !== 'light';
  };
  window.toggleTheme = function () {
    const next = window._isDark() ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('uas-theme', next);
    document.querySelectorAll('.theme-toggle').forEach(function (btn) {
      btn.innerHTML = window._isDark()
        ? '<span class="icon-inline icon-sm"><svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span>'
        : '<span class="icon-inline icon-sm"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg></span>';
    });
  };
})();

// --- Global nav helpers ---
window.updateNavUser = function () {
  const el = document.getElementById('navUser');
  if (!el) return;
  const themeIcon = window._isDark()
    ? '<span class="icon-inline icon-sm"><svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span>'
    : '<span class="icon-inline icon-sm"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg></span>';
  const themeBtn = '<button class="btn btn-sm btn-outline theme-toggle" onclick="toggleTheme()" title="Toggle theme" type="button">' + themeIcon + '</button>';
  if (api._user) {
    el.innerHTML = themeBtn + ' <a href="/dashboard" class="btn btn-outline btn-sm">' + esc(api._user.name) + '</a>';
  } else {
    el.innerHTML = themeBtn + ' <a href="/login" class="btn btn-primary btn-sm">Login</a>';
  }
};

window._esc = window._esc || function (s) { return s != null ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; };
var esc = esc || window._esc;

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
    wrap.innerHTML = '<button class="bell-btn" type="button" aria-label="Notifications"><span class="icon-inline"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>'
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
    if (attr) {
      const v = el.getAttribute(attr);
      if (v && v.indexOf('://') === -1 && !v.startsWith('//') && !v.startsWith('#')
        && !v.startsWith('mailto:') && !v.startsWith('tel:') && !v.startsWith('javascript:')
        && !v.startsWith('data:')) {
        if (v.startsWith('/')) {
          if (v === window.UAS_BASE || v.startsWith(window.UAS_BASE + '/')) return;
          el.setAttribute(attr, window.UAS_BASE + v);
        }
      }
    }
    // Inline styles may carry url(/...) or url(img/...) references set by scripts
    const st = el.getAttribute && el.getAttribute('style');
    if (st && st.indexOf('url(') !== -1) {
      const fixed = st.replace(/url\((['"]?)\/([^'")]+)\1\)/g, 'url(' + window.UAS_BASE + '/$2)');
      if (fixed !== st) el.setAttribute('style', fixed);
    }
  }

  function scan(root) {
    if (root.nodeType === 1) rewrite(root);
    (root.querySelectorAll ? root.querySelectorAll('a[href], form[action], img[src], link[href], script[src], [style]') : []).forEach(rewrite);
  }

  scan(document);
  if (window.MutationObserver) {
    const mo = new MutationObserver(muts => {
      muts.forEach(m => m.addedNodes.forEach(n => { if (n.nodeType === 1) scan(n); }));
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });
  }
})();
