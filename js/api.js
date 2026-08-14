// UAS Institutional Platform — API Client
// Thin wrapper around fetch with auth, JSON, and error handling

const API_BASE = window.location.origin + '/uas/api';

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

  // Calendar
  async getCalendar() { return this.request('/calendar'); },
  async createCalendarItem(data) { return this.request('/calendar', { method: 'POST', body: data }); },

  // Dashboard
  async getDashboard() { return this.request('/dashboard'); },

  // Pending
  async getPending() { return this.request('/pending'); },

  // Public
  async getPublicArticles() { return this.request('/public/articles'); },
  async getPublicEvents() { return this.request('/public/events'); },
  async getPublicProgrammes() { return this.request('/public/programmes'); },

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
