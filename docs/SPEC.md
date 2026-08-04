# NetHub — Original Product/Technical Spec

This is the full brief the project owner gave for NetHub, kept verbatim (lightly reformatted) so
the requirements aren't lost if this conversation or any external notes disappear. For **current
build status and conventions actually implemented in code**, see [`../CLAUDE.md`](../CLAUDE.md) —
that file is the one to keep updated every stage; this one is the frozen reference brief and
should rarely need edits (only if the owner changes the requirements).

## Product

NetHub = SaaS Enterprise for Indonesian internet providers (RT/RW Net, ISP kecil, WISP, hotspot).
Multi-tenant: each provider's data must be 100% isolated. Providers self-register and manage
customers, routers, payments, and billing through one unified platform.

## Mandatory tech stack

- **Backend:** PHP 8.2+, CodeIgniter 4, REST API, MySQL
- **Mobile:** Flutter (Material Design 3) — Owner app + Customer app, both Android
- **Web:** Bootstrap 5, responsive, mobile-friendly (Owner web has the SAME feature set as Owner mobile)
- **Hosting:** Shared Hosting (Rumahweb) — explicitly NO VPS, NO Docker, NO Node.js. Everything must
  run on typical shared PHP hosting.
- **Database:** MySQL, normalized, foreign keys, indexes, soft delete

## Architecture

```
Backend CI4 + MySQL → REST API (JSON) →
  ├─ Flutter Owner (Android)
  ├─ Flutter Customer (Android)
  └─ Web Owner (Browser)
    → Router Driver Interface (Strategy pattern)
      → MikroTik, Ubiquiti, Omada, OpenWRT, Huawei, Cisco, Ruijie
```

All data realtime, synced, multi-tenant.

## Roles

Super Admin SaaS (manages tenants/global system), Owner, Admin, Teknisi, Kasir, Pelanggan
(end customer).

## Owner features (mobile + web must be feature-identical)

- Dashboard: real-time stats (active customers, revenue, traffic), quick actions
- Customer mgmt: add/edit/delete/suspend/enable, assign package, per-customer activity history
- Packages: CRUD, speed profile (bandwidth), price & billing cycle
- Invoices: auto-generate per billing cycle, PDF download, mark paid/unpaid/overdue
- Payments: QRIS (auto-generate QR per invoice), manual transfer (upload proof + admin verify),
  payment gateway webhook, payment history/reports
- Router management: add router (name/type/IP/port/user/pass), test connection, multi-router,
  online/offline monitoring
- Monitoring: per-user traffic, online user list, bandwidth usage, router status
- Broadcast: WhatsApp messages to customers, template messages
- Reports: revenue (daily/monthly), customers, payments, export PDF/CSV
- Settings: logo & brand, theme color, business profile (name/address/WA/email), QRIS & bank
  account, backup & restore

## Customer features

Self-signup (optional referral), dashboard (internet status/quota/due date), invoice list +
detail + status, pay via QRIS/transfer/upload proof, downloadable PDF invoices, payment/activity
history, complaint/ticket creation & tracking, profile edit, notifications (due-date reminders,
payment confirmation).

## Multi-tenant isolation — CRITICAL, non-negotiable

Each tenant = one internet provider with fully separate: logo/name/address/WA/email/theme,
QRIS & bank account, customers, packages, invoices/billing, routers & technicians,
settings/config, activity log.

**Rules:**
- Every query MUST filter `WHERE tenant_id = $tenant_id` at every layer (Controller, Model, Service)
- Auth middleware MUST set the tenant context from the JWT
- NEVER hardcode tenant_id
- Use repository pattern for data-access isolation
- Every feature must be tested against 2+ different tenants

## Router driver (Strategy pattern)

```php
interface RouterDriverInterface {
    public function testConnection(): bool;
    public function addUser($username, $password, $speed_limit);
    public function updateUser($username, $password, $speed_limit);
    public function removeUser($username);
    public function suspendUser($username);
    public function enableUser($username);
    public function getOnlineUsers();
    public function getTraffic($username);
    public function reboot();
}
```

MikroTik driver is priority (full implementation via MikroTik API library, multi-router support).
Future drivers (structure ready, not required day one): Ubiquiti, Omada, OpenWRT, Huawei, Cisco,
Ruijie. Each new driver: implement the interface, register in a factory, test independently.

## Payment system

- QRIS: generate + store QR per invoice, downloadable
- Manual transfer: upload proof, admin verifies & marks paid, notifies customer
- Payment gateway (optional): webhook handler, auto-update invoice status, retry mechanism.
  **Decision (2026-08-02): both Midtrans and Xendit are supported**, tenant picks which one to
  configure in Settings — the owner explicitly asked for both rather than one fixed provider, so
  each ISP can use whichever account they already have. See `CLAUDE.md` for implementation notes.
- Invoice: auto PDF, scheduled per billing cycle, email + WhatsApp notif, downloadable from
  customer dashboard
- Audit: log every transaction, reconciliation report, overdue/tunggakan report

## Notification system

- WhatsApp: due-date reminder (H-3), payment confirmation, suspend/reactivation notice, broadcast
- Email: invoice attachment, reminders, system notifications
- Push (Flutter): real-time dashboard alerts
- Must be queue-based with retry — never blocking the request

## Database rules

FK with ON DELETE CASCADE/SET NULL as appropriate; index tenant_id/user_id/customer_id;
soft delete for audit trail; created_at/updated_at timestamps; dedicated activity/audit log table.
Core tables named in the original spec: users, tenants, customers, packages, invoices, payments,
routers, online_users, activities, support_tickets. (The actual implemented schema adds supporting
tables — roles/permissions/role_permissions/refresh_tokens/etc. See the live migrations in
`app/Database/Migrations/` for ground truth; this document is the product intent, not a schema dump.)

## REST API conventions

Prefixes: `/api/auth/*` (register, login, refresh, logout), `/api/owner/*` (dashboard, customers,
packages, invoices, payments, routers, monitoring, broadcast, reports), `/api/customer/*`
(dashboard, invoices, pay, payments, tickets, profile).

Response envelope:
```json
{ "success": true, "data": {...}, "message": "..." }
```

## Security requirements

- JWT access (15 min) + refresh (7 days) tokens, secure storage
- RBAC via middleware/permission checker per route, tenant isolation on every query
- HTTPS enforced, CSRF tokens on forms, XSS sanitization/escaping, parameterized queries,
  bcrypt password hashing (cost/min 12)
- Rate limiting: login 5/15min, API general 100 req/min/user, uploads max 5MB
- Audit log for all create/update/delete and critical actions (login, payment, broadcast);
  admin dashboard to review logs

## Performance requirements

- Index tenant_id/user_id/created_at; JOIN not loop-query; pagination default 20 / max 100 rows;
  soft-delete-aware scopes
- Caching: dashboard stats 5 min, tenant user list 2 min, package list 1 hr, router status 30 sec
  (near-realtime), clear cache on create/update
- gzip API responses, CDN for static assets, lazy-load images, minimal payloads
- No N+1 queries (eager load), batch operations instead of loops, stream large CSV/PDF exports,
  async queue for email/WhatsApp

## UI/UX principles

Modern/minimalist/premium visual style; primary blue + accent orange + neutral gray; clean
sans-serif; Feather/Material icons. Max 3 clicks to any main feature. Full Bahasa Indonesia UI.
Loading indicators, clear actionable error messages, confirm-before-delete, undo for important
actions. Mobile-first, responsive, touch targets ≥44px, minimal horizontal scroll, dark mode
toggle. Target user is explicitly non-technical ("ibu rumah tangga bisa operate") — visual
feedback and tooltips matter more than power-user density.

## CI4 folder structure (as specified by the owner)

```
app/
├── Config/ (Routes.php, Database.php)
├── Controllers/ (Auth/, Owner/, Customer/, Api/...)
├── Models/
├── Services/ (RouterService, InvoiceService, PaymentService, NotificationService)
├── Repositories/ (CustomerRepository, InvoiceRepository, ...)
├── Drivers/ (RouterDriverInterface, MikroTikDriver, UbiquitiDriver...)
├── Helpers/ (AuthHelper, TenantHelper, ResponseHelper, ValidationHelper)
├── Filters/ (AuthFilter, TenantFilter, PermissionFilter)
├── Database/ (Migrations/, Seeds/)
└── Views/ (auth/, owner/, layouts/)
```

## Testing strategy

- **Unit:** every model method (create/update/delete); mock external services (router API,
  payment gateway); multi-tenant isolation (2+ tenant scenarios); min 70% coverage on critical path.
- **Integration:** API endpoints against a real test DB; multi-tenant flow (login tenant A, confirm
  no access to tenant B's data); invoice auto-generate workflow; payment webhook handler.
- **E2E:** Owner: add customer → assign package → generate invoice → payment. Customer: login →
  view invoice → pay → confirmation. Router: connect → add user → suspend → enable.
- Tools: PHPUnit (backend), Flutter integration test (mobile).

## Deployment strategy (shared hosting — Rumahweb)

**Requirements:** PHP 8.2+ with PDO MySQL/cURL/OpenSSL extensions, MySQL 5.7+, ≥5GB disk
(app + DB + uploads), no `exec()` permission needed (CI4 is fine without it).

**Setup:** upload via FTP/SFTP to `/public_html/`; document root → `/public/`; import migrations;
configure `.env` (DB, JWT secret, API keys); writable `/storage/` upload dir; cron for invoice
generation + old-file cleanup.

**CI/CD:** GitHub Actions auto-deploy on push, run tests before deploy, daily automated DB backup.

## Performance metrics & targets

- API response time: dashboard < 500ms, customer list < 1s (1000+ customers), invoice generate
  < 2s, monitoring data < 300ms.
- DB: avg query < 100ms, connection pool 10–20 concurrent, backup size < 500MB/month growth.
- Mobile: app launch < 2s, dashboard load < 1s, 60fps scroll.
- Scale target: ≥100 tenants on shared hosting, ≥10,000 customers per tenant, 500+ concurrent
  users without degradation.

## Migration & data integrity

- Immutable migration files (timestamp prefix), rollback capability for development, no data loss
  during migration, test migrations on staging before production.
- If transitioning from an existing system: data mapping plan (customer/invoice/payment),
  validation rules (no duplicates, referential integrity), parallel-run period, cutover +
  rollback contingency plan.

## Monitoring & maintenance

- Error tracking (Sentry or similar), API latency/status-code monitoring, DB query performance
  logging, email/WhatsApp delivery tracking.
- Health checks: DB connectivity (every 5 min), API endpoint health, router connection status,
  payment gateway connectivity.
- Maintenance: DB optimization (ANALYZE/OPTIMIZE TABLE) monthly, log cleanup monthly, temp/unused
  file cleanup weekly, SSL renewal auto via Certbot.

## Security checklist (before production)

- [ ] HTTPS enforced (SSL certificate)
- [ ] CORS configured (frontend domain only)
- [ ] JWT secret key strong (32+ random chars)
- [ ] Database password strong (25+ chars, random)
- [ ] All user input validated & sanitized
- [ ] SQL injection test (malicious query must fail safely)
- [ ] XSS test (injected script tag must be escaped)
- [ ] CSRF token verified on POST/PUT/DELETE
- [ ] File upload security (MIME type check, virus scan)
- [ ] Rate limiting active on auth endpoints
- [ ] Password hashing verified (bcrypt, not MD5)
- [ ] Audit log enabled & tested
- [ ] Error messages don't expose system internals
- [ ] Backup encrypted & restore tested

## Build stages (owner's own sequencing)

1. Database Design (schema/ERD/migration)
2. REST API (spec, routing, controller skeleton)
3. Backend Logic (Model/Service/Repository)
4. Authentication (JWT, filter, middleware)
5. Multi-Tenant Setup (tenant helper, filter, isolation test)
6. Core Features (Customer CRUD, Invoice, Payment)
7. Router Integration (driver pattern, MikroTik driver)
8. Notification (WhatsApp, Email, Push queue)
9. Frontend Web (dashboard, forms, responsive)
10. Flutter Owner (navigation, API integration, state mgmt)
11. Flutter Customer (dashboard, billing, payment screens)
12. Testing (unit, integration, E2E)
13. Optimization (query tuning, caching, CDN)
14. Deployment (shared hosting, domain setup)

## Quality bar expected on every deliverable

Deploy-ready code (not theory/incomplete), multi-tenant safe (no hardcoded tenant_id), clean/SOLID/
DRY, documented (API spec, setup, migration notes), security-hardened, performance-optimized
(no N+1, caching, indexes), minimal edge-case test coverage. Explicitly avoid: generic/tutorial-level
answers, magic numbers/hardcoded values, N+1 or inefficient logic, security bypasses or incomplete
code, anything not tenant-aware.

## Delivery preference

Work is done **one stage at a time**, with a pause for review before starting the next stage —
the owner is watching token/usage cost and wants checkpoints, not the whole SaaS dumped in one
pass. All implementation work for this project — backend and Flutter both — happens in the same
session/tool; Flutter work is not delegated elsewhere. Flutter apps are built directly at
`C:\Flutter-NEETHUB`.
