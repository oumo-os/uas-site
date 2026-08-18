# UAS Institutional Platform

**Uganda Astronomical Society** — [astronomy.ug](https://astronomy.ug)

A governance-aware institutional management system for UAS. Not just a website — a platform where board resolutions automatically enact system changes.

## Architecture

```
Governance Layer    →  Resolutions, votes, auto-apply on quorum
Authority Layer    →  Users → Roles → Capabilities → Permissions
Administration Layer → Backups, config, security, maintenance
```

**Core principle**: Titles do not equal authority. The Board determines which capabilities belong to which roles. Changing a person's role changes their effective authority without manual permission changes.

## Tech Stack

- **Frontend**: HTML5, vanilla CSS/JS, no frameworks
- **Backend**: PHP 7.4+ / MySQL 5.7+
- **Auth**: Session-based, capability-middleware
- **Governance engine**: Resolutions with auto-apply when last vote meets quorum

## Key Features

- **Governance Engine**: Board resolutions that automatically create roles, assign capabilities, appoint members
- **RBAC**: Granular capability-based access control (articles.approve, events.publish, finance.view, etc.) with per-resource scoping (programme / event / project)
- **Workflow Engine**: Every institutional object follows Draft → Submit → Review → Approve → Publish → Archive
- **Member Lifecycle**: Register → pending → board approval (or rejection) → baseline Member role granted automatically
- **Pending Items**: Centralized action queue — approve articles/events/documents or vote on resolutions inline
- **Resolution Discussions**: Comment threads on resolutions before and during voting
- **Operations Console**: Finance records, documents, assignments, calendar in one admin panel
- **Budget & Working Groups**: Budget items per programme/project/event, plus committees and task forces with memberships
- **Institutional Dashboard**: Members, programmes, projects, events, pending items, financials, budget, groups
- **Audit Trail**: Who did what, when, with governance context
- **Database Backups**: Admin-triggered gzipped SQL dumps (last 20 kept), download from the admin console
- **Public Website**: Database-driven pages for About, Programmes, Events, Knowledge, Library, Ecosystem, Member Directory, Membership page, Article/Event/Programme detail pages, custom 404
- **Event RSVPs**: Capacity-aware registration with attendee management for organizers
- **Event Waitlist**: Join when full, auto-promotion on cancellation, manual promote for organizers
- **Notifications**: In-app bell — approvals, rejections, publishing, assignments, waitlist promotion, voting and contact messages notify the right people
- **Assignment Workflow**: Assignee start → submit with evidence → assigner/manager completes
- **Member Profiles**: Editable profile, interests, profile visibility, password change
- **Public Contact Form**: Honeypot-protected contact page feeding an admin inbox (mark read)
- **CSV Exports & Import**: Members, finance, and audit log one-click downloads (RBAC-guarded); bulk member CSV import with temp passwords
- **Site-wide Search**: Cross-content search (articles, events, programmes, projects, documents, members) with public-safe member results
- **Password Reset (admin)**: Generate a one-time temporary password for any member, audit-logged
- **Hardening**: Login/registration rate limiting, SameSite session cookies, server-side password policy, cross-origin request rejection, MIME-whitelisted uploads
- **Meetings**: Board/general/committee meetings with agenda, scheduled time, attendance records (attended/absent/excused/apology), posted minutes, and decisions that generate assignments with notifications
- **Polls**: Governance or consultation polls with eligibility rules (directors / members / all), quorum, deadlines, anonymous voting, one-vote-per-user, tally bars, and close/resolve with tie and quorum handling; open polls surface in Pending Items
- **Programme Teams**: Programme members with roles, plus free-form outputs per programme (member-only visibility, managed by programme leads)
- **Financial Snapshot**: Dashboard and finance summary show income, expenses, committed funds and available balance
- **Photo Gallery**: Knowledge Base gallery fed from published articles with images (`/public/gallery`)
- **News & External Feeds**: News page combining official UAS announcements (published `announcement` articles) with curated external astronomy resources (`/public/news`)
- **Delegation / Proxy Voting**: Members delegate their resolution and/or poll votes to a trusted peer (scope: `all` | `resolutions` | `polls`); delegatee votes are cast on the delegator's behalf (`delegated_for`), one-vote-per-user enforced, delegations auto-revoked when either side loses the voting right

## Setup

1. Create MySQL database `uas_platform`
2. Run all migrations in order: `mysql -u root uas_platform < migrations/001_initial.sql` … `014_delegations.sql` (each file can be piped the same way)
3. Configure `api/config.php` with DB credentials (or environment variables `UAS_DB_HOST`, `UAS_DB_USER`, `UAS_DB_PASS`, `UAS_ENV=production`)
4. Seed dev data: `php api/seed.php` (admin + board + roles + capabilities)
5. Seed sample content: `php api/seed-content.php`, then `api/seed-richer.php`, `api/seed-users.php`, `api/seed-budget-groups.php` (all idempotent)
6. Point web root to project directory (e.g. `htdocs\uas` junction for XAMPP)
7. Local 404 page (XAMPP only): hardlink the custom page to htdocs root so `ErrorDocument 404 /404.html` resolves:
   `cmd /c mklink /H M:\Dev\xampp\htdocs\404.html M:\Dev\projects\New folder\uas\uas-site\404.html`

**XAMPP local run**: junction `M:\Dev\xampp\htdocs\uas` → project dir, start Apache + MySQL, visit `http://localhost/uas`. Login: `admin@astronomy.ug` / `admin123` (board: `cosmus@astronomy.ug` etc., all `/password123`; imported and seeded users also `/password123`).

**Backups**: admin console → Backups tab (or `POST /backups`). Dumps land in `data/backups/` and the last 20 are kept. For automated nightly backups add a cron job calling `POST /api/backups` with an admin session.

## API Endpoints

All requests go through `api/index.php?route=...` or rewrite to `/api/...`

### Auth
- `POST /auth/login` — Login
- `POST /auth/register` — Register (pending approval)
- `POST /auth/logout` — Logout
- `GET /auth/me` — Current user + capabilities

### Governance
- `GET /resolutions` — List resolutions
- `POST /resolutions` — Create resolution
- `GET /resolutions/:id` — Get resolution + votes + changes
- `POST /resolutions/:id/submit` — Submit for voting
- `POST /resolutions/:id/begin-voting` — Open voting
- `POST /resolutions/:id/vote` — Cast vote (auto-applies on quorum)

### Institutional Objects
- `GET/POST /members` — List members / approve members (POST flips user status + grants Member role)
- `GET/POST /programmes` — List/create programmes
- `GET/POST /projects` — List/create projects
- `GET/POST /events` — List/create events
- `GET/POST /articles` — List/create articles
- `GET/POST /documents` — List/upload documents
- `GET/POST /finance` — List/record financials
- `GET/POST /assignments` — List/create assignments
- `GET/POST /calendar` — List/create calendar items
- `POST /events/:id/approve` / `/publish` — Approve / publish event
- `POST /articles/:id/approve` / `/publish` — Approve / publish article (auto-stages through review)
- `POST /documents/:id/approve` / `/publish` — Approve / publish document
- `GET /authority/:userId` — Governance trace ("why does this user have this authority")
- `POST /governance/close-expired` — Deadline sweep for stale voting resolutions
- `GET /events/:id` — Public event detail (+ my RSVP state, attendee list for managers)
- `POST /events/:id/waitlist` / `DELETE` — Join / leave the waitlist when full
- `POST /events/:id/waitlist/:wlId/promote` — Manager-promote a waitlisted member
- `GET /notifications` — My notifications (unread first); `POST /notifications/read-all`, `POST /notifications/:id/read`
- `GET/POST /resolutions/:id/comments` — Resolution discussion threads
- `GET/POST /budget-items` — Budget items (finance.view); `GET /budget-items/:id`
- `GET/POST /working-groups`, `GET/POST /working-groups/:id/members` — Committees & task forces
- `GET/POST /backups` — List / create database backups; `GET /backups/:file/download`, `DELETE /backups/:file` (admin.system)
- `GET/POST /meetings`, `GET/PUT /meetings/:id` — Meetings list/create/update
- `POST /meetings/:id/status` — Set meeting status (meetings.manage)
- `POST /meetings/:id/attendance` — Record attendance (meetings.record)
- `POST /meetings/:id/minutes` — Post minutes; decisions become assignments + notify assignees (meetings.record)
- `GET/POST /polls`, `GET/DELETE /polls/:id` — Polls list/create/detail/delete (draft only)
- `POST /polls/:id/open` — Open voting (notifies eligible voters)
- `POST /polls/:id/vote` — Cast vote (eligibility + one-vote + quorum aware)
- `POST /polls/:id/close` — Close & tally (tie / quorum → no result); `POST /polls/:id/resolve` — same, record-level
- `GET/POST /programmes/:id/members`, `DELETE /programmes/:id/members/:userId` — Programme teams (programmes.manage)
- `PUT /programmes/:id` — Update programme incl. outputs
- `GET /public/gallery` — Published articles with images (public feed)
- `GET /public/news` — UAS announcements + active external links (public feed)
- `POST /upload` — Attachment upload (MIME-whitelisted, 10MB cap, random filenames)
- `GET /public/articles|events|programmes|documents` — Public content feeds (no login)
- `POST /admin/login-as` — Admin impersonation for support (audit-logged)
- `GET /search?q=` — Cross-content public search
- `POST /contact` — Public contact form (honeypot-filtered)
- `GET /contact-messages` / `POST /contact-messages/read-all` — Admin inbox
- `GET /export/members.csv` / `finance.csv` / `audit.csv` — CSV downloads
- `POST /members/:id/reset-password` — Admin temp-password reset

### Dashboard
- `GET /dashboard` — Institutional health metrics
- `GET /pending` — All pending items (delegated polls/resolutions excluded from `scope=mine` for delegators)
- `GET /delegations` — My outgoing + incoming delegations
- `POST /delegations` — Create/re-delegate (upsert per delegator+scope)
- `POST /delegations/:id/revoke` — Revoke an outgoing delegation
- `GET /audit` — Audit log

## Governance Engine

When a resolution passes (vote meets quorum), the system automatically applies all changes:

1. Board creates resolution with changes (create role, assign caps, appoint member)
2. Resolution enters voting
3. Directors vote
4. When last vote meets quorum → **auto-applies** (no admin gatekeeper)
5. Changes take effect immediately, audit trail recorded
6. "Why does John have this authority?" → Resolution UAS-BRD-2026-014

## Roles & Capabilities

Roles are configurable. Capabilities are granular:

- `articles.submit`, `articles.review`, `articles.approve`, `articles.publish`
- `events.create`, `events.approve`, `events.publish`
- `programmes.create`, `programmes.manage`, `programmes.approve`
- `resolutions.create`, `resolutions.vote`, `resolutions.manage`
- `meetings.create`, `meetings.manage`, `meetings.record`
- `governance.poll.create`, `governance.poll.manage`, `governance.poll.vote`, `governance.poll.resolve`
- `finance.view`, `finance.record`, `finance.approve`
- `members.view`, `members.approve`, `members.manage`
- `admin.system`

## Directory Structure

```
uas-site/
├── api/
│   ├── index.php        # API router
│   ├── config.php       # DB, session, rate limiting, helpers
│   ├── auth.php         # Authentication
│   ├── rbac.php         # Roles, capabilities, assignments
│   ├── governance.php   # Resolutions, voting, auto-apply
│   ├── workflow.php     # Lifecycle engine + pending items
│   ├── audit.php        # Audit trail
│   ├── backup.php       # Database dump/restore helpers
│   └── seed*.php        # Dev seed data (seed, seed-content, seed-richer, seed-users, seed-budget-groups)
├── migrations/
│   └── 001…014_*.sql    # Schema + incremental migrations
├── data/backups/        # Generated database dumps
├── js/
│   └── api.js           # Frontend API client
├── css/
│   └── base.css         # Design system
├── admin/
│   └── resolution-builder.php  # Governance console
├── member/
│   └── submit-article.php      # Article submission form
├── index.html           # Public landing page
├── about.html           # About the society
├── programmes.html      # Programmes (DB-driven)
├── programme.html       # Programme detail
├── events.html          # Events (DB-driven)
├── event.html           # Event detail + RSVP/waitlist
├── knowledge.html       # Articles/knowledge base (DB-driven)
├── article.html         # Article detail
├── ecosystem.html       # Partners & links
├── members.html         # Member directory
├── join.html            # Membership page
├── login.html           # Login / register
├── dashboard.html       # Member dashboard
├── admin.html           # Administration console
├── search.html          # Site-wide search
├── profile.html         # My profile
├── 404.html             # Self-contained error page
└── .htaccess            # Clean URLs + API rewrite
```
