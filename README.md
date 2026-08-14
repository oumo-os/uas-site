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
- **RBAC**: Granular capability-based access control (articles.approve, events.publish, finance.view, etc.)
- **Workflow Engine**: Every institutional object follows Draft → Submit → Review → Approve → Publish → Archive
- **Member Lifecycle**: Register → pending → board approval → baseline Member role granted automatically
- **Pending Items**: Centralized action queue — approve articles/events/documents or vote on resolutions inline
- **Operations Console**: Finance records, documents, assignments, calendar in one admin panel
- **Institutional Dashboard**: Members, programmes, projects, events, pending items, financials
- **Audit Trail**: Who did what, when, with governance context
- **Public Website**: Database-driven pages for About, Programmes, Events, Knowledge, Library, Ecosystem, Member Directory, Article & Programme detail pages
- **Event RSVPs**: Capacity-aware registration with attendee management for organizers
- **Assignment Workflow**: Assignee start → submit with evidence → assigner/manager completes
- **Member Profiles**: Editable profile, interests, profile visibility, password change

## Setup

1. Create MySQL database `uas_platform`
2. Run schema: `mysql -u root uas_platform < migrations/001_initial.sql` then `migrations/002_event_rsvps.sql`
3. Configure `api/config.php` with DB credentials
4. Seed dev data: `php api/seed.php` (admin + board + roles + capabilities)
5. Seed sample content: `php api/seed-content.php` (idempotent — members, articles, events, programmes, projects, documents, finance, assignments, calendar, partners, links, one applied resolution)
6. Point web root to project directory (e.g. `htdocs\uas` junction for XAMPP)

**XAMPP local run**: junction `M:\Dev\xampp\htdocs\uas` → project dir, start Apache + MySQL, visit `http://localhost/uas`. Login: `admin@astronomy.ug` / `admin123` (board: `cosmus@astronomy.ug` etc., all `/password123`).

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

### Dashboard
- `GET /dashboard` — Institutional health metrics
- `GET /pending` — All pending items
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
- `finance.view`, `finance.record`, `finance.approve`
- `members.view`, `members.approve`, `members.manage`
- `admin.system`

## Directory Structure

```
uas-site/
├── api/
│   ├── index.php        # API router
│   ├── config.php       # DB, session, helpers
│   ├── auth.php         # Authentication
│   ├── rbac.php         # Roles, capabilities, assignments
│   ├── governance.php   # Resolutions, voting, auto-apply
│   ├── workflow.php     # Lifecycle engine + pending items
│   ├── audit.php        # Audit trail
│   └── seed.php         # Dev seed data
├── migrations/
│   └── 001_initial.sql  # Full database schema
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
├── events.html          # Events (DB-driven)
├── knowledge.html       # Articles/knowledge base (DB-driven)
├── ecosystem.html       # Partners & links
├── members.html         # Member directory
├── login.html           # Login / register
├── dashboard.html       # Member dashboard
├── admin.html           # Administration console
└── .htaccess            # Clean URLs + API rewrite
```
