# NetHub — Internet Provider SaaS

Multi-tenant SaaS for Indonesian internet providers (RT/RW Net, ISP kecil, WISP, hotspot).
Each provider (tenant) self-registers and manages customers, routers, billing, and payments
through one platform. **Tenant data isolation is the #1 non-negotiable rule** — see below.

Full original product brief: [`docs/SPEC.md`](docs/SPEC.md) (frozen reference, rarely changes).
**This file (`CLAUDE.md`) is the living source of truth for build progress** — update it at the
end of every stage: what shipped, what decisions were made, what's next. Both files live in this
repo on purpose, so any session or tool that opens this folder — not just this one — has full
context without depending on anything outside the project.

## Hard constraints (do not violate)

- **Hosting target: shared hosting (Rumahweb).** No VPS, no Docker, no Node.js runtime. Everything
  must work on stock shared PHP hosting.
- **Every tenant-scoped query MUST filter by `tenant_id`.** Never hardcode a tenant_id. Every new
  feature must be sanity-checked against 2+ tenants before it's considered done.
- Backend: PHP 8.2+, CodeIgniter 4, MySQL. Web: Bootstrap 5. Mobile: Flutter (Material Design 3).
- **Flutter apps (Owner + Customer) live at `C:\Flutter-NEETHUB`**, a separate folder from this
  CI4 backend — not a subfolder of this repo.
- Work proceeds **one stage at a time** with a pause for review between stages — don't chain
  multiple stages unprompted. The user is Bahasa Indonesia-speaking; respond to them in Indonesian.

## Environment (local dev)

- CodeIgniter 4.7.4, PHP 8.2.12, MySQL/MariaDB via XAMPP.
- App timezone is `Asia/Jakarta` (`app/Config/App.php`'s `$appTimezone`), matching this machine's
  MySQL `time_zone=SYSTEM`. Don't change this without checking both sides still agree — a mismatch
  here silently breaks anything date-based (invoice due dates, H-3 reminders, reconcile cutoffs);
  see the Stage 5 addendum for how this was caught.
- Databases: `neethub` (main), `neethub_test` (tests). Both already created.
- `.env` is configured: `CI_ENVIRONMENT=development`, `app.baseURL=http://localhost:8080/`,
  encryption key set, `JWT_SECRET`/`JWT_ACCESS_EXPIRY`(900s)/`JWT_REFRESH_EXPIRY`(604800s) set.
- Run migrations: `php spark migrate --all`
- Run seeders: `php spark db:seed DatabaseSeeder`
- Dev server: `php spark serve --port 8080`
- Demo login (password for all: `password123`): `owner@melati.test`, `owner@sejahtera.test`
  (also `admin@`, `teknisi@`, `kasir@` @melati.test/@sejahtera.test) — two separate demo tenants
  for isolation testing.
- Cron-style commands (all idempotent, safe to run repeatedly): `php spark invoices:generate`
  (daily — generates due invoices, flags overdue ones), `php spark router:sync` (every ~30s —
  polls router online-user sessions into the `online_users` table), `php spark invoices:remind`
  (daily — queues H-3 due-date reminders), `php spark notifications:send` (every 1-2 min —
  processes the WhatsApp/Email/Push queue), `php spark payments:reconcile` (every ~10-15 min —
  re-checks pending gateway payments in case a Midtrans/Xendit webhook was missed).

## Build sequence & progress

1. **Database Design** — ✅ done
2. **REST API + Auth (JWT)** — ✅ done
3. **Core Features (Customer/Package/Invoice CRUD, Repository pattern)** — ✅ done
4. **Router Integration (Driver pattern, MikroTik)** — ✅ done (⚠️ not yet validated against real hardware — see Stage 4 notes)
5. **Payments (QRIS, manual transfer; gateway webhook deferred)** — ✅ done (done out of order,
   before Notifications — see Stage 5 notes for why)
6. **Notifications (WhatsApp/Email/Push queue)** — ✅ done (⚠️ WhatsApp/Email senders not validated
   against real gateway/SMTP credentials — see Stage 6 notes; Push is a stub, needs the Flutter
   stage's device-token registration first)
7. **Web Frontend (Bootstrap 5 dashboard)** — ✅ done — Owner side feature-complete; Customer side's
   *pages* are feature-complete but **⚠️ has no real account-provisioning path** — see the 2026-08-04
   correction note right after Stage 7's addendum below, and Stage 13.
8. **Flutter apps** (`C:\Flutter-NEETHUB`) — **status lives in that repo's own `CLAUDE.md`, not here**
   — don't hardcode ✅/⬜ for this item in this file, it will go stale. As of 2026-08-04: Owner app's
   screen coverage is done (verified `flutter analyze` only, no device testing yet); Customer app
   work is starting under Stage 13 below (merged into the Owner app, not a separate app — see Stage 13).
9. **Testing (unit/integration/E2E)** — ⬜ not started
10. **Optimization (caching, query tuning)** — ⬜ not started
11. **Deployment (shared hosting)** — ⬜ not started
12. **Multi-tenant SaaS platform (self-serve billing + customer self-service)** — 🟡 in progress
    (Stage 13 phase 1-2/5 done: customer account auto-provisioning + portal toggle, platform
    billing engine + Super Admin panel). See Stage 13.

### Stage 1 — Database Design (done)

17 migrations in `app/Database/Migrations/`: `tenants`, `roles`, `permissions`,
`role_permissions`, `users`, `refresh_tokens`, `password_resets`, `packages`, `routers`,
`customers`, `invoices`, `payments`, `online_users`, `activities` (audit log),
`support_tickets`, `support_ticket_replies`, `notifications` (queue).

Every tenant-scoped table has `tenant_id` + FK (`CASCADE`/`SET NULL` per relation) + index.
Soft delete (`deleted_at`) on tables needing an audit trail. RBAC is real tables
(`roles`/`permissions`/`role_permissions`), not a hardcoded enum — 6 roles: `super_admin`,
`owner`, `admin`, `teknisi`, `kasir`, `pelanggan`.

Seeders in `app/Database/Seeds/`: `RolePermissionSeeder` (permission matrix per role),
`DemoTenantSeeder` (2 isolated demo tenants: `melati-net`, `sejahtera-net`, each with
owner/admin/teknisi/kasir users, 3 packages, 1 router, 3 customers, 3 invoices — verified
isolated by tenant_id).

### Stage 2 — REST API Foundation + JWT Auth (done)

- `firebase/php-jwt` installed. `app/Config/JWT.php` reads secret/expiry from `.env`.
- `app/Helpers/AuthHelper.php` — JWT encode/decode (access 15min, refresh 7d), bcrypt (cost 12),
  and the **request-scoped auth context** (`AuthHelper::setContext()`/`id()`/`tenantId()`/
  `roleId()`/`role()`/`isSuperAdmin()`), populated by `JwtAuthFilter` and read by anything
  downstream (models, other filters) without threading it through every method signature.
- `app/Helpers/TenantHelper.php` — thin accessor over `AuthHelper`'s tenant context;
  `requireTenantId()` throws loudly if a tenant-scoped code path runs with no tenant in context.
- `app/Helpers/ResponseHelper.php` — the `{success, data, message}` envelope every endpoint uses.
- `app/Controllers/Api/BaseApiController.php` — extends plain `Controller` (NOT
  `ResourceController` — its `fail()` signature collides). Gives `ok()`/`created()`/`fail()`/
  `validationError()`/`unauthorized()`/`forbidden()`/`notFound()`.
- Filters registered in `app/Config/Filters.php` as aliases: `jwtauth` (verifies Bearer token,
  loads user fresh from DB so a deactivated account is blocked immediately even before the JWT
  expires; optional args restrict by role slug e.g. `jwtauth:owner,admin`), `tenant` (blocks if
  the authenticated user's tenant is suspended; super_admin bypasses), `permission` (checks
  `role_permissions` for a required slug e.g. `permission:customers.delete`; super_admin
  bypasses), `ratelimit` (fixed/rolling window via cache, args `label,max,windowSeconds`).
- `app/Controllers/Api/AuthController.php`: `register` (self-signup — creates a new tenant +
  its first `owner` user), `login`, `refresh` (rotates refresh tokens — old one is revoked the
  moment a new pair is issued, reuse is rejected), `logout` (revoke one refresh token or all of
  a user's sessions).
- Models: `TenantModel`, `RoleModel` (+ `permissionSlugs()`), `UserModel` (password hashed via
  `beforeInsert`/`beforeUpdate` callback — controllers never hash manually), `RefreshTokenModel`,
  `ActivityModel` (audit log — `login`/`register`/`logout` already wired in).
- Routes: `app/Config/Routes.php` — `/api/auth/{register,login,refresh,logout}` are live.
  `/api/owner/*` (filter: `jwtauth:owner,admin,teknisi,kasir` + `tenant`) and `/api/customer/*`
  (filter: `jwtauth:pelanggan` + `tenant`) route groups exist but are **empty** — next stage
  fills them in.
- **Verified end-to-end with curl**: register → login (both demo tenants, correct tenant_id in
  each JWT) → wrong password rejected → missing-token request rejected (401) → refresh rotation
  + old-token-reuse rejection → logout (specific token + revoke-all) → login rate limit (5/15min)
  self-triggered during testing, confirmed working → suspending a tenant correctly blocks its
  owner's login.
- One bug caught and fixed during testing: `register`'s response initially leaked the bcrypt
  password hash in the `user` object (login already stripped it, register didn't) — fixed.

### Stage 3 — Core Features: Customer/Package/Invoice CRUD (done)

- `app/Repositories/BaseRepository.php` — every tenant-scoped repository extends this. Owns
  `scoped()` (tenant_id filter), `paginateScoped()` (page/perPage, capped at 100), `find()`,
  `delete()`. **Every column it touches is qualified with the table name** (e.g.
  `customers.tenant_id`, not bare `tenant_id`) — subclasses `LEFT JOIN` other tenant-scoped
  tables that have their own `tenant_id`/`id` columns, so an unqualified column is ambiguous to
  MySQL the instant a join is added. Concrete repos pass their table name into the constructor:
  `CustomerRepository`, `PackageRepository`, `InvoiceRepository`.
  - `CustomerRepository`: list with status/package/search filters, joins package + router name
    (no N+1 on the list view), auto-generates `customer_code` (checked-in-a-loop uniqueness,
    same tradeoff already accepted for tenant slugs in Stage 2 — not fully concurrency-safe
    under simultaneous inserts, acceptable for this scale).
  - `InvoiceRepository`: list with status/customer filters, joins customer name/code,
    `nextInvoiceNumber()` (same uniqueness-loop pattern), `paginateForCustomer()` for the
    customer-facing endpoints.
- `app/Services/InvoiceService.php` — the actual billing logic. `generateForCustomer()`
  computes the next billing period from the package's `billing_cycle` (weekly/monthly/yearly/
  custom_cycle_days), refuses to generate if the current period hasn't ended yet (prevents
  double-billing), 3-day grace period before `due_date`. `generateDueForTenant()` loops every
  active customer in a tenant, collects `{generated, skipped}` — one bad customer record
  (no package assigned, etc.) doesn't stop the batch.
- `app/Commands/GenerateInvoices.php` — `php spark invoices:generate`, meant to run daily via
  cron (Rumahweb/cPanel cron calling spark). Idempotent — safe to run more than once a day.
  Loops every active tenant, manually sets `AuthHelper::setContext()` per tenant (no HTTP
  request/JWT in a CLI context, so the tenant context `JwtAuthFilter` would normally set has to
  be set by hand), also flags overdue invoices via `InvoiceModel::markOverdueForTenant()`.
- Owner controllers (`app/Controllers/Api/Owner/`): `DashboardController` (stats: customer
  counts by status, invoice counts by status, this-month revenue from paid invoices, active
  package count, online customer count — 5-minute cache per the spec's caching rules, TTL-only
  for now, no explicit invalidation yet — tracked below), `CustomerController` (full CRUD +
  `suspend`/`enable` — **these only flip the DB `status` column for now**; actually cutting a
  customer off at the router is Stage 4/Router Integration), `PackageController` (full CRUD),
  `InvoiceController` (list, show, `generate` — single customer or whole-tenant batch).
- Customer-facing controllers (`app/Controllers/Api/Customer/`): `DashboardController` (own
  package + next due invoice), `InvoiceController` (own invoices only — cross-checks
  `customer_id` even though the repository is already tenant-scoped, so one customer can never
  see another's invoice within the same tenant), `ProfileController` (view/update own
  user + customer record).
- `BaseApiController` gained `paginationParams()` (reads `page`/`per_page` query params, caps at
  100) and `paginated()` (wraps a repository's paginate result in the standard envelope).
- Routes wired in `app/Config/Routes.php` under `/api/owner/*` and `/api/customer/*`, each with
  a `permission:<slug>` filter alongside the group-level `jwtauth`/`tenant` filters (confirmed via
  `php spark routes` that CI4 merges group + route filters rather than one overriding the other).
- **Bug found and fixed during testing**: `BaseRepository::scoped()`/`paginateScoped()` originally
  used bare `tenant_id`/`id` column names. The moment `InvoiceRepository` joined `customers`
  (which also has `tenant_id`/`id`), MySQL threw "Column 'tenant_id' in where clause is
  ambiguous" — a real 500 caught by testing the kasir role against `/api/owner/invoices`. Fixed
  by qualifying every column with `{$this->tableName}.` throughout `BaseRepository`.
- **Verified end-to-end with curl across both demo tenants**: package CRUD, customer CRUD +
  suspend/enable + auto customer_code, invoice generation (+ correctly refuses to double-generate
  within the same period), permission enforcement (kasir blocked from `customers.delete` with
  403, kasir allowed on `invoices.view`), cross-tenant isolation (sejahtera's owner gets 404 on
  melati's customer ID, each tenant's list only shows its own rows), soft delete (deleted
  customer disappears from the list, audit log still records `customer_deleted`), validation
  errors (422 with field-level messages), dashboard stats (counts and revenue sum correct), and
  the `invoices:generate` cron command (looped all 3 tenants — including the Stage 2
  self-registered `testing-net` tenant with zero customers — with correct per-tenant isolation
  and skip reasons).

### Stage 4 — Router Integration (done, ⚠️ untested against real hardware)

- `app/Drivers/RouterDriverInterface.php` — the Strategy pattern contract from the spec
  (`testConnection`, `addUser`, `updateUser`, `removeUser`, `suspendUser`, `enableUser`,
  `getOnlineUsers`, `getTraffic`, `reboot`).
- `app/Drivers/Support/MikroTikApiClient.php` — **hand-written RouterOS API protocol client**,
  not a third-party package. MikroTik's API is a raw length-prefixed binary word protocol over a
  plain TCP socket (port 8728 by default) — not HTTP — so no existing HTTP client helps, and
  pulling in an unfamiliar composer package for something this protocol-specific felt riskier
  than implementing the documented spec directly (https://wiki.mikrotik.com/wiki/Manual:API).
  Assumes RouterOS ≥ 6.43's single-step plain-text login, not the older MD5 challenge-response —
  a deliberate scope cut, documented in the class itself.
- `app/Drivers/MikroTikDriver.php` — implements the interface via the client above. Customers
  are managed as PPP secrets (`/ppp/secret/*`), matching the existing `pppoe_username`/
  `pppoe_password` columns on `customers`. Bandwidth is enforced through the secret's own
  `rate-limit` field rather than a separate Simple Queue. `suspendUser` also kicks any currently
  active PPP session (`/ppp/active/remove`) since disabling a secret alone doesn't drop an
  already-connected customer.
- **⚠️ IMPORTANT CAVEAT**: `MikroTikApiClient`/`MikroTikDriver` were written and reviewed against
  the documented protocol spec, but there is no real or virtual MikroTik device in this
  environment to test against. Structurally sound, protocol-encoding logic double-checked by
  hand, but **not hardware-validated**. Before relying on this in production: test against a real
  RouterOS device or a MikroTik CHR VM. If bugs surface there, they're almost certainly in
  `MikroTikApiClient`'s length encoding/decoding or the command syntax in `MikroTikDriver`, not
  in the surrounding service/controller layer (that part IS tested, via curl, against the
  graceful-failure paths).
- `app/Drivers/RouterDriverFactory.php` — resolves a `routers` row (by `type`) to its driver.
  Only `mikrotik` is implemented; the other 6 brands from the spec throw a clear
  "belum diimplementasikan" error — structure is ready, adding one is: implement the interface,
  add one `match` arm here.
- **Fixed a real gap from Stage 3**: `routers.password` and `customers.pppoe_password` were
  marked "encrypted at rest" in the Stage 1 migrations but nothing actually encrypted them yet.
  Added `app/Helpers/CryptoHelper.php` (wraps CI4's Encryption service, keyed by `.env`'s
  `encryption.key`) and wired `beforeInsert`/`beforeUpdate` hooks into `RouterModel` and
  `CustomerModel` so both fields are transparently encrypted on write, decrypted only at the
  point `RouterDriverFactory` needs the real value to authenticate. `CryptoHelper::decrypt()`
  returns `null` (not a thrown exception) on a corrupt/legacy/unencrypted value, so a bad
  credential reads as a clean "can't use this" instead of crashing the request — this was
  actually exercised for real during testing (see below).
- `app/Repositories/RouterRepository.php` + `app/Services/RouterService.php` — the service wraps
  driver calls with try/catch so callers get a result, not an exception: `testConnection()`
  updates `routers.status`/`last_checked_at` either way; `suspendCustomer()`/`enableCustomer()`
  return `'success'|'failed'|'skipped'` rather than throwing, because **a customer with no
  router/PPPoE assignment yet, or a momentarily-unreachable router, must never block an owner
  from changing a customer's status in NetHub itself** — that's a deliberate product decision,
  not an oversight.
- `CustomerController::suspend/enable` now call `RouterService` and include `router_sync` in the
  response so a future UI can show a warning icon when the DB status changed but the router
  action didn't.
- `app/Commands/SyncRouters.php` — `php spark router:sync`, meant to run every ~30s (matches the
  spec's "router status: cache 30 detik" rule). Polls every router's `getOnlineUsers()` and
  mirrors the result into the `online_users` table, so `MonitoringController::usersOnline()` is a
  fast DB read instead of a live per-request call to every router. **If a router is unreachable,
  its `online_users` rows are deleted rather than left stale** — "can't confirm anyone's online"
  must never render as "these people are online".
- Owner endpoints: `RouterController` (full CRUD + `test` + `online-users`, password never
  appears in any response), `MonitoringController` (`users-online` from the synced table,
  `traffic` as a live single-customer call since that's one targeted router hit, not tenant-wide).
- **Verified end-to-end with curl**: router CRUD, permission enforcement (teknisi allowed to
  `test` a router per its seeded permissions but blocked with 403 from `create`), password never
  leaked in any router API response, encryption round-trip confirmed by reading the raw DB value
  (base64 ciphertext, not plaintext) after creating a router via the API. **Genuinely exercised
  the graceful-failure path for real** — none of the demo routers are reachable from this dev
  machine, so `test`/`online-users`/`router:sync` all hit real socket-connect timeouts (~5s) and
  came back as clean `offline`/422 responses, never a crash. Also caught and fixed a seeder bug
  this surfaced: `DemoTenantSeeder` wrote the demo router password via a raw query builder
  insert, bypassing `RouterModel`'s new encryption hook — fixed by encrypting it explicitly in
  the seeder and updating the two already-seeded rows directly.

### Stage 4 re-review (prompted by the user asking "is /routers really solid") — gap found & fixed

Re-read `MikroTikApiClient`/`MikroTikDriver` line by line again, specifically checking for bugs
beyond "untested against hardware":

- **Protocol length-encoding re-verified by hand at every boundary** (0x7F/0x80, 0x3FFF/0x4000,
  and the 3-/4-/5-byte cutoffs) — each one round-trips correctly through `encodeLength()` →
  `readLength()`. Confident this part is mathematically sound, independent of the "never tested
  against real hardware" caveat.
- **Command syntax re-checked** (`/ppp/secret/add|set|remove`, `/ppp/active/print|remove`,
  `/interface/monitor-traffic`, the `<pppoe-username>` dynamic interface naming) — matches
  documented RouterOS API syntax as far as can be verified without a device.
- Non-MikroTik brands (Ubiquiti/Omada/OpenWRT/Huawei/Cisco/Ruijie) confirmed to still cleanly
  throw "belum diimplementasikan" via `RouterDriverFactory` — no silent-failure risk there.
- **🔴 Real gap found — ✅ fixed same session**: `addUser()`/`updateUser()` were fully implemented
  in `MikroTikDriver` but nothing ever called them — creating a customer in NetHub never pushed a
  PPPoE secret to the router, only `suspend`/`enable` were wired up. Fixed:
  - `RouterDriverInterface::addUser()`/`updateUser()` signatures changed to take
    `$downloadMbps, $uploadMbps` separately (previously one combined `$speedLimitMbps`, which
    didn't match `packages.speed_download`/`speed_upload` being two distinct columns).
    `MikroTikDriver` builds RouterOS's `rate-limit` as `{upload}M/{download}M` (RouterOS's
    "rx/tx" is from the router's perspective — rx = client's upload, tx = client's download).
  - Added `RouterService::addCustomer()` and `updateCustomerSpeed()`, same best-effort
    try/catch-and-report-status contract as `suspendCustomer`/`enableCustomer` — decrypts the
    customer's `pppoe_password` via `CryptoHelper` before handing it to the driver.
  - Wired into `Api/Owner/CustomerController`: `create()` now calls `addCustomer()` whenever a
    package is assigned (looks up the package for its speed), `update()` calls
    `updateCustomerSpeed()` whenever `package_id` is part of the update payload (handles a
    customer upgrading/downgrading packages). Both include `router_sync` in the response, same
    pattern as suspend/enable.
  - **Verified for real**: created a customer with a router+package+PPPoE username assigned —
    confirmed the log shows a genuine `addUser` attempt failing on a real socket-connect timeout
    (not a decryption/credential error), proving the password decrypts correctly and the call
    reaches the driver with the right arguments; customer record still saved successfully
    (`router_sync: "failed"`, doesn't block anything) — same graceful-failure contract as every
    other router action.
  - **Deliberately narrower scope, not covered**: `update()` only pushes a speed change when
    `package_id` is explicitly part of that request — it does not detect "PPPoE username was just
    assigned for the first time via an edit" as a trigger to call `addCustomer()`. That specific
    edge case (assigning router/PPPoE to an existing customer after the fact, via edit rather than
    at creation) still needs the owner to trigger it some other way for now.

### Package-upgrade speed change didn't reach an already-connected session (found & fixed)

Prompted by the user asking a very concrete question: "I upgrade Muslimin from 10Mbps to 20Mbps —
does the hardware actually follow?" Answer required checking real RouterOS behavior, not just the
code: **RouterOS only reads a PPP secret's `rate-limit` at connection time** — changing the secret
while a session is already active does NOT retroactively speed up/throttle that live session; the
customer would stay on the old speed until their router happens to reconnect on its own (could be
hours). `suspendUser()` already forced a reconnect for exactly this reason; `updateUser()` didn't.

- Extracted the kick-the-active-session logic from `suspendUser()` into a shared
  `kickActiveSession()` private method, and now `updateUser()` calls it too whenever the
  rate-limit actually changes — so a package upgrade/downgrade takes effect within seconds
  (the customer's modem auto-redials) instead of "eventually, whenever they next reconnect".
- This could only be verified by re-reasoning through documented RouterOS behavior (no real
  device here) — flagged with the same hardware-untested caveat as the rest of `MikroTikDriver`,
  but the fix itself is a direct, low-risk extension of the already-established
  suspend/kick pattern, not new unproven territory.
- Re-ran the existing package-update flow after the change (customer #2, no real router reachable)
  to confirm no regression — still completes and reports `router_sync` cleanly.

### Overdue behavior clarified for the user + migration billing-anchor fix (done)

Two things prompted by the user walking through a real migration scenario (moving an existing
manually-billed customer, "Muslimin", subscribed since June 1st, into NetHub):

- **Confirmed and explained: nothing in this codebase auto-suspends an overdue customer.**
  `invoices:generate`/`markOverdueForTenant()` only flip the *invoice's* status to `overdue` — no
  cron or command anywhere calls `RouterService::suspendCustomer()` or touches
  `customers.status`. Suspension is 100% a manual owner/admin/kasir action (the `suspend` button).
  This was already true, just not obviously documented — worth stating plainly since it's exactly
  the kind of thing an owner needs to trust before migrating real customers.
- **Fixed a real gap this surfaced**: a customer's first-ever invoice always anchored its period
  to *today's date* (whenever `generate` was first run for them), not their actual subscription
  start date — meaning a customer who really pays on the 1st would drift to whatever day they
  got added to NetHub. Fixed: `InvoiceService::computePeriod()` now takes the customer's
  `installation_date` (a column that already existed on `customers`, just unused for billing math
  until now) as the anchor for the first invoice only — every invoice after that still anchors to
  the previous one's `period_end`, so the billing day stays locked in permanently once set.
  - Added the missing "Tanggal Mulai Berlangganan" date field to the Customers page's add/edit
    modal (`owner/customers.php`) — `installation_date` was already accepted by the API but had
    no way to actually set it from the web UI.
  - **Verified for real**: created a test customer with `installation_date=2026-06-01` (monthly
    package), generated three invoices in a row — got back period_start `2026-06-01`, `2026-07-01`,
    `2026-08-01` in sequence, confirming the billing day stays anchored to the 1st indefinitely
    rather than drifting to today's date.
  - **Known interaction model, not a bug**: catching up a customer who's several months behind
    (e.g. migrating someone whose real start date was many months ago) takes one "Generate
    Tagihan" click per missing month, not one click that backfills everything — each call only
    ever produces the *next* period after the last invoice (or after `installation_date` for the
    very first one).

### Stage 5 — Payments: QRIS + manual transfer (done, gateway webhook deferred)

Done before Notifications (reordered from the original numbering) because Notifications is more
useful once there's a real payment event to hook into — building it against a stub would mean
redoing the wiring later.

- **QRIS**: NetHub does not integrate with a payment gateway/PSP — it takes the tenant's own
  static EMV QRIS string (from whatever PSP/bank they already registered as a QRIS merchant
  with) and derives a per-invoice **dynamic** QR with the exact amount embedded. This is a
  standard, documented EMV QR Code technique, not a NetHub invention: parse the static payload's
  TLV (tag-length-value) fields, flip the point-of-initiation tag from `11` (static) to `12`
  (dynamic), inject tag `54` (transaction amount), and recompute the tag-`63` CRC16 checksum over
  everything before it.
  - `app/Services/QrisService.php` — the TLV parser/serializer + **hand-written CRC-16/CCITT-FALSE**
    (poly `0x1021`, init `0xFFFF`, no reflection/final XOR — the exact variant EMVCo specifies).
    Chose to implement this directly rather than reach for a package because it's a small, exact,
    well-documented algorithm (like the MikroTik protocol in Stage 4) — there isn't really a
    "the standard library for this" the way there is for actual QR image rendering (next point).
  - **Verified for real, not just by inspection**: registered a test tenant's QRIS static code via
    `PUT /owner/settings`, generated a dynamic QR for a real invoice via the API, then
    independently recomputed the CRC16 by hand in a separate one-off script and confirmed it
    matched the API's output byte-for-byte. Confirmed tag `54`'s injected amount and tag `01`'s
    `11→12` flip are both correct in the actual generated payload.
  - **⚠️ Same caveat as MikroTik in Stage 4**: correct per the documented spec and independently
    cross-checked, but never scanned by a real bank/e-wallet app in this environment. Unlike a
    router bug, this one fails safe — a wrong CRC just makes the code unscannable, it can't cause
    an incorrect charge (payment confirmation is always manual regardless, see below) — but
    validate against a real scanner before relying on it in production.
  - QR **image rendering** (turning the payload string into an actual PNG) uses `endroid/qr-code`
    — unlike the EMV math above, QR encoding involves Reed-Solomon error correction and mask
    pattern selection, genuinely complex enough that hand-rolling it would be reckless. Needs the
    `gd` PHP extension (confirmed present in this XAMPP install).
- **Manual transfer**: customer uploads a proof image (`POST /customer/invoices/{id}/pay`,
  multipart). MIME-type allowlist (jpeg/png/webp) + 5MB size cap enforced server-side, finally
  closing the "file upload security" gap flagged back in Stage 3's cross-cutting notes. Files land
  in `writable/uploads/payment_proofs/` with a randomized filename (never the original name).
- **Both payment methods create a `pending` `payments` row** — confirmation is always a manual
  owner/admin/kasir action (`POST /owner/payments/{id}/verify`, body `{approve, notes}`), never
  automatic. Approving flips the linked invoice to `paid` + stamps `paid_at`; rejecting leaves the
  invoice untouched with the rejection reason in `notes`. A payment can only be verified once
  (second attempt is rejected with a clear message).
- Every verify (approve or reject) queues a row in the `notifications` table (`channel: whatsapp,
  type: payment_confirmation`) — actually sending it is the Notifications stage's job; queuing now
  means that stage's worker has real data to process from day one instead of starting from empty.
- Added `app/Controllers/Api/Owner/SettingsController.php` (`GET`/`PUT /owner/settings`) — this
  had to exist for Payments to be testable at all, since the tenant needs somewhere to set
  `qris_static_code`/bank info. Covers the "Pengaturan" feature from the spec's Owner feature list
  (logo upload and backup/restore are not covered — narrower scope than the full Settings feature).
- **Payment gateway webhook is intentionally NOT built** — the spec marks it optional and doesn't
  name a specific provider (Midtrans/Xendit/DOKU/etc.), and building a generic "webhook handler"
  without knowing the real payload shape/signature scheme of an actual provider would mean
  guessing at an interface that'll likely need to be redone anyway. Revisit if/when a specific
  provider is chosen.
- **Bug found and fixed during testing** (two, both real): (1) `PaymentController::verify`'s
  validation rule `in_list[0,1,true,false]` rejected a genuine JSON boolean `true` — CI4's in_list
  rule string-compares, so it doesn't reliably match a JSON boolean vs JSON string vs form value;
  replaced with a plain existence check plus `filter_var(..., FILTER_VALIDATE_BOOLEAN)`. (2)
  `PaymentService::generateQrisForInvoice` had no check for an already-paid invoice — caught by
  testing it against invoice #3 right after verifying its payment, which should have been
  rejected but silently generated a QR anyway; added the same "already lunas" guard `submitPayment`
  already had.
- **Verified end-to-end with curl**: QRIS generation + CRC self-consistency (above), transfer
  submission with a real uploaded PNG, owner verification flipping the invoice to paid, notification
  row queued correctly, double-verify rejected, permission enforcement (teknisi has no
  `payments.*` permission at all and correctly gets 403 just from listing), QRIS correctly refused
  for an already-paid invoice and for a tenant with no `qris_static_code` configured (sejahtera-net).

### Stage 6 — Notifications: WhatsApp/Email/Push queue (done)

- `app/Notifications/` (new, parallel to `app/Drivers/`) — Strategy pattern per channel, same
  style as `RouterDriverInterface`: `WhatsAppSenderInterface` (`FonnteWhatsAppSender` +
  `LogWhatsAppSender` fallback), `EmailSenderInterface` (`SmtpEmailSender`, wraps CI4's own
  Email service — standard framework functionality, not a guessed integration — + `LogEmailSender`
  fallback), `PushSenderInterface` (`LogPushSender` only — see caveat below).
  `NotificationSenderFactory::forChannel()` picks the real sender if configured or the log
  fallback otherwise, so the whole pipeline is testable without real gateway credentials.
  (Originally WhatsApp was configured via a single global `FONNTE_TOKEN` in `.env` — **corrected
  to per-tenant in the Stage 7 addendum below**, each tenant now connects their own WhatsApp
  number via Settings, matching how routers/QRIS/payment-gateway credentials already worked.)
  - **Fonnte** (fonnte.com) was chosen as the concrete WhatsApp provider — not arbitrarily, it's
    one of the most commonly used unofficial WA gateways among small Indonesian SaaS products in
    exactly this niche (RT/RW Net billing tools). **⚠️ Same caveat as MikroTik/QRIS**: implemented
    against Fonnte's documented API shape from memory, not verified against a live token — there's
    no real WhatsApp gateway credential in this environment. If the response shape differs,
    only `FonnteWhatsAppSender` needs to change.
  - **Push is a stub only** (`LogPushSender`) — there's no Flutter app yet to register a device
    token against, so there's nothing real to send to. Swap in a real `FcmPushSender` behind
    `PushSenderInterface` once Stage 8/9 adds device-token registration; the queue/dispatch
    plumbing around it won't need to change.
- `app/Services/NotificationTemplates.php` — renders subject/message per `type`
  (`payment_confirmation`, `invoice_reminder`, `suspend`, `reactivation`, `broadcast`) in
  Indonesian, pulling in the tenant's name and the customer's name for a personal tone.
- `app/Services/NotificationDispatcher.php` — the actual queue worker. Pulls `pending` rows with
  `attempts < 3`, resolves the recipient's phone/email, renders the template, calls the right
  sender. On failure: retries (stays `pending`, `attempts` incremented) until the 3rd failure,
  then marks `failed` for good. A row with an unresolvable recipient (deleted customer, no
  phone/email on file) is marked `failed` immediately rather than retried, since retrying
  wouldn't change that outcome.
- `php spark notifications:send` (every 1-2 min via cron) runs the dispatcher.
  `php spark invoices:remind` (daily via cron) queues H-3 reminders for unpaid/overdue invoices
  due in exactly 3 days, deduped per customer per day so re-running the same day is harmless.
- Closed two gaps from earlier stages now that there's a real dispatcher to hang them off:
  `CustomerController::suspend/enable` (Stage 4) now also queues a `suspend`/`reactivation`
  notification, and `app/Controllers/Api/Owner/BroadcastController.php` implements the spec's
  `POST /broadcast/whatsapp` endpoint (targets: `all`/`active`/`overdue`/`custom`, queues one
  notification per matched customer).
- Added `app/Helpers/PhoneHelper.php` — Indonesian phone numbers get stored in every shape
  (`08xx`, `+628xx`, `628xx`, with spaces/dashes); WhatsApp gateways uniformly expect bare `62...`,
  so this is the one place that gets normalized.
- **Two real bugs found and fixed during testing**: (1) The `.env` had `logger.threshold = 4` left
  over from Stage 1 — CI4's own `app/Config/Logger.php` default is already environment-aware
  (`9` for dev, `4` for production), but the flat `.env` override was silently swallowing every
  `info`-level log message, which is exactly what the log-fallback senders use. Every "sent"
  notification during testing was actually being dropped into the void with the count updated in
  the DB but no evidence it "sent" anywhere — caught by grepping the log file and finding nothing,
  not by any error. Removed the override so CI4's environment-aware default applies. (2)
  `invoices:remind` didn't check whether an invoice's customer was soft-deleted — since deleting a
  customer is a soft delete (not a real `DELETE`), the FK `CASCADE` on `invoices.customer_id`
  never fires, so an invoice can outlive its customer. Caught by testing (a leftover invoice from
  the Stage 3 test customer "Rina Wijaya," deleted back then, got queued for a reminder and
  correctly failed at send-time since she has no active contact record) — fixed by joining
  `customers` and requiring `deleted_at IS NULL` in the invoice query, so it's no longer even
  considered rather than being queued and failing.
- **Verified end-to-end**: broadcast permission enforcement (teknisi has no `broadcast.send` and
  gets 403), broadcast targeting (`all` vs `overdue`, confirming a customer whose invoice got paid
  during Stage 5 testing correctly dropped out of the `overdue` target), suspend/enable queuing
  their notifications, `notifications:send` processing a mixed batch (payment confirmation +
  broadcasts + suspend/reactivation) and correctly logging real message content once the
  threshold bug was fixed, `invoices:remind`'s same-day dedup (second run same day: 0 queued),
  and the deleted-customer exclusion fix (re-run after the fix: 0 queued, 0 failed, no new row).

### Stage 5 addendum — Payment Gateway: Midtrans + Xendit (done)

Added right after Stage 6, at the owner's explicit request: **both Midtrans and Xendit are
supported**, not one fixed provider — a tenant picks whichever they already have an account with
via Settings. This fills in the "payment gateway webhook" piece of Stage 5 that was deliberately
deferred at the time for lack of a chosen provider.

- `app/PaymentGateways/` (new, same Strategy-pattern shape as `RouterDriverInterface`) —
  `PaymentGatewayInterface` (`createTransaction`, `handleWebhook`, `checkStatus`),
  `MidtransGateway`, `XenditGateway`, `PaymentGatewayFactory` (resolves from
  `tenants.payment_gateway`). Each tenant is the merchant of record on their own Midtrans/Xendit
  account — NetHub never holds a shared merchant credential, matching how router credentials and
  QRIS static codes already work per-tenant.
- New `tenants` columns: `payment_gateway` (`midtrans`|`xendit`|null) and
  `payment_gateway_config` (TEXT, **encrypted at rest** like router/PPPoE passwords) — a JSON
  blob whose shape depends on the gateway (`server_key`/`client_key` for Midtrans,
  `secret_key`/`callback_token` for Xendit). Chose a JSON blob over one column per possible field
  because the two gateways' credential shapes don't overlap cleanly and a 3rd provider later
  would mean more nullable columns; `SettingsController` never echoes the config back (returns
  `payment_gateway_configured: bool` instead), same treatment as never re-showing a hashed/
  encrypted secret elsewhere in this codebase.
- `PaymentService::initiateGatewayPayment()` — creates the `pending` payment row FIRST so its own
  ID can seed a unique order/external ID (`{invoice_number}-{paymentId}`), letting a customer
  retry a failed attempt on the same invoice without an ID collision at the gateway. If the
  gateway call itself fails, the payment is marked `rejected` with the error in `notes` rather
  than left as an orphaned `pending` row with no `gateway_reference` — **a real bug caught during
  testing**: the first attempt (against Midtrans's real sandbox, with a fake key) correctly failed
  with a proper structured Midtrans error, but left exactly such an orphan; fixed by wrapping the
  gateway call in try/catch inside the service.
- `PaymentService::confirmViaGateway()` — shares the same finalize-payment logic `verify()` uses
  (extracted into a private `finalizePayment()`), but with `verified_by = null` to distinguish
  "confirmed automatically by the gateway" from a human's manual verification in the activity log.
  Idempotent: a payment that's no longer `pending`, or a gateway status of `pending`, is a
  deliberate no-op — gateways redeliver webhooks, and that must never double-process a payment.
- `app/Controllers/Api/WebhookController.php` (`POST /api/webhooks/{midtrans,xendit}`) —
  intentionally **outside** the `jwtauth`/`tenant`/`ratelimit` filter groups (a gateway's server
  can't carry our Bearer tokens). Trust is established per-request: look up which payment/tenant
  the payload claims to be about via its `gateway_reference` FIRST, then verify the signature/
  token against THAT SPECIFIC tenant's own stored credentials — never a shared secret of NetHub's.
- `php spark payments:reconcile` (every ~10-15 min via cron) — the spec's "retry mechanism": polls
  any `gateway` payment still `pending` after a 10-minute grace period directly against the
  gateway's own status API, in case a webhook was delayed or never delivered.
- Extended `InvoiceController::pay` (customer-side, Stage 5) to accept `payment_method=gateway`
  alongside the existing `qris`/`transfer`.
- **⚠️ Same caveat category as MikroTik/Fonnte**: `createTransaction`/`checkStatus` (the
  OUTBOUND calls to Midtrans/Xendit) are implemented from each provider's documented API, not
  verified against a real merchant account. **However**, unlike those two, this was tested more
  thoroughly than "structurally sound" — the outbound calls were actually made against Midtrans's
  real sandbox endpoint with a fake key and came back with a proper structured rejection ("Access
  denied... check client or server key" / "Unknown Merchant server_key/id"), which is strong
  evidence the request shape (auth method, JSON body, URL) is correct, not just plausible-looking.
  The **inbound webhook verification** (the security-critical half) was fully verified: computed
  a real Midtrans SHA512 signature and a Xendit callback token by hand, confirmed both are
  accepted when correct and rejected with 401 when wrong, and confirmed cross-tenant correctness
  (each tenant's own stored credentials are what gets checked).
- **A second, unrelated but significant bug found via this testing**: `app/Config/App.php`'s
  `$appTimezone` was `'UTC'` (the CI4 default) while this machine's MySQL runs with
  `time_zone=SYSTEM` (Asia/Jakarta, UTC+7) — a 7-hour gap between what PHP's `date()` computes and
  what the DB server's own clock shows. Surfaced when a manually-backdated test row (using MySQL's
  `NOW()`) wasn't picked up by `payments:reconcile`'s PHP-computed cutoff. Every timestamp a real
  Indonesian ISP owner would see (activity log, `last_checked_at`, invoice dates) was being
  computed 7 hours off from local business time. Fixed by setting `$appTimezone = 'Asia/Jakarta'`.
  Note: this doesn't retroactively fix already-stored timestamps (fine — no real production data
  exists yet), and application code that always used PHP's `date()` consistently (never mixing in
  raw SQL `NOW()`/`CURDATE()`) was internally self-consistent even before this fix; the fix
  matters for correctness against wall-clock/business time and against anything that does query
  using the DB's own `NOW()`/`CURDATE()` in the future.

### Stage 7 — Web Frontend (IN PROGRESS — minimal skeleton, stopped early to save session budget)

Two sub-phases so far: a proof-of-architecture skeleton (plain Bootstrap CDN), then a real design
system swapped in at the user's request. Only login + owner dashboard exist — continue from here.

**Design system (user-provided template, not my own design)**: the user downloaded a free
Bootstrap 5 admin template ("InApp Inventory Dashboard" by CodesCandy/ThemeWagon) into
`public/templates/` — a Vite + SCSS source project, not usable as-is on shared hosting (no Node at
runtime). Built it once locally (`npm install && npm run build` inside `public/templates/`) and
copied the **compiled, static** output into the app proper, per the user's explicit instruction to
keep CSS/JS separated and under `public/`:
- `public/css/main.css` — compiled Bootstrap + template SCSS (one bundle)
- `public/js/main.js` — compiled Bootstrap JS + template's sidebar-toggle/chart JS (ES module,
  loaded with `type="module"`)
- `public/fonts/tabler-icons.{woff2,woff,ttf}` — the icon font the template's `<i class="ti ti-*">`
  icons use; `main.css`'s `@font-face` URLs were rewritten (`../tabler-icons.*` →
  `../fonts/tabler-icons.*`) to match this layout, since fonts moved out of the CSS's own folder
  - `public/images/` — logo SVGs + favicon set copied from the template
- **One real source fix, not just asset copying**: the template's `sidebar.js` detected the active
  nav item by comparing `location.pathname.split('/').pop()` against each link's `href` — written
  for static `.html` files, meaningless for our clean CI4 routes (`/dashboard`, not
  `dashboard.html`). Fixed in `public/templates/src/assets/js/sidebar.js` to compare full
  pathnames instead, then rebuilt. If more template JS gets pulled in later, expect similar
  static-site assumptions to need adjusting.
- `public/templates/` itself is source material, not shipped/served — `node_modules/`, `dist/`
  (the old build output, already extracted), and `.github/` were deleted since they're pure dead
  weight once built once; `src/*.html` (the other demo pages — inventory, reports, create-product,
  404, docs) were kept as reference markup for adapting the remaining Stage 7 pages later (e.g.
  `reports.html`'s KPI-card pattern is already reused in the dashboard; `inventory.html`'s table
  layout is the obvious starting point for the Customers list page next).
- `app/Views/layouts/auth.php` — bare layout (no sidebar) for login/register-style pages.
- `app/Views/layouts/dashboard.php` — the real shared shell: topbar (user dropdown + logout) +
  collapsible sidebar + main content area, matching the template's `#topbar`/`#sidebar`/`#content`
  IDs that its JS expects. Sidebar nav is hardcoded with the full intended menu (Dashboard,
  Pelanggan, Paket, Tagihan, Pembayaran, Router, Broadcast, Pengaturan) — **only Dashboard is a
  real link**, the rest render disabled with a "Segera" badge rather than linking to pages that
  don't exist yet (honest-UI choice over shipping dead links). Active-link highlighting is done
  server-side via `uri_string()`, not the template's client-side JS (kept as a harmless no-op
  fallback). Also exposes `nethubFetch()` (adds the Bearer token, redirects to `/login` on 401) so
  page-specific scripts don't repeat that boilerplate.
- `app/Views/auth/login.php` and `app/Views/owner/dashboard.php` rebuilt on top of the above,
  same behavior as before (login stores tokens in `localStorage`, dashboard renders 4 stat cards)
  but now using the template's actual `card`/icon/spinner markup instead of plain Bootstrap.
- **Verified**: dev server was restarted (dies between sessions, expected), all of
  `/login`, `/dashboard`, `/css/main.css`, `/js/main.js`, `/fonts/tabler-icons.woff2`,
  `/images/favicon_io/favicon-32x32.png` return 200, rendered HTML confirmed to reference the new
  asset paths correctly. **Still not exercised in an actual browser** (no real click-through) —
  do that first when resuming, before building more pages on top.

**Pelanggan (Customers) page — done**: `app/Views/owner/customers.php` +
`app/Controllers/Web/CustomerController.php` (`GET /customers`, sidebar item flipped to enabled).
Full CRUD against the existing API — searchable/filterable/paginated table, add/edit modal (shared
form, `#customerId` hidden field distinguishes create vs update), suspend/enable with the router
outcome surfaced via the API's `router_sync` field (not shown in the UI yet, just doesn't break
anything), delete — all with `confirm()` prompts per the spec's "confirmation dialog before
delete" rule, and inline success/error banners instead of `alert()`. Package dropdown populated
from `/api/owner/packages`. Verified the API response shape (`customer_code`, `package_name` from
the repository's join, etc.) matches what the page's JS expects.

**Paket (Packages) page — done**: `app/Views/owner/packages.php` +
`app/Controllers/Web/PackageController.php` (`GET /packages`, sidebar enabled). Same CRUD-table-
+-modal pattern as Customers — add/edit/delete, billing-cycle select that reveals a "custom days"
field only when `billing_cycle=custom`, active/inactive toggle.

**Real bug, caught by the user actually clicking Edit in a browser** (first real browser
click-through of this whole stage): Edit did nothing on both Customers and Packages. Root cause —
`public/templates/src/assets/js/main.js` does `import * as bootstrap from 'bootstrap'`, and Vite
bundles that as a local module binding, not a global `window.bootstrap` the way the old
CDN `<script src="bootstrap.bundle.min.js">` pattern worked. Our own page scripts call
`new bootstrap.Modal(...)` assuming the CDN-style global — silently threw `ReferenceError` on
every click. The declarative `data-bs-toggle="modal"` buttons (e.g. "Tambah Pelanggan") still
worked because those are wired up by Bootstrap's own internal listeners, independent of the
global — which is exactly why only Edit looked broken, not Create. **Fixed at the source**:
added `window.bootstrap = bootstrap;` in `main.js` right after the import, rebuilt, recopied to
`public/js/main.js`. Any future page with a JS-triggered modal/toast/dropdown depends on this
same global — if a fresh `npm run build` ever drops it, this bug comes back everywhere at once.
**If a page's modal doesn't open on click, check this first.**

**Tagihan (Invoices) page — done**: `app/Views/owner/invoices.php` +
`app/Controllers/Web/InvoiceController.php` (`GET /invoices`). Read-only list (status filter,
pagination, detail modal) plus a "Generate Tagihan" button hitting the existing tenant-wide
`POST /api/owner/invoices/generate`. No edit/delete — matches the API, which doesn't expose those
for invoices either (they're generated, not hand-edited).

**Pembayaran (Payments) page — done**: `app/Views/owner/payments.php` +
`app/Controllers/Web/PaymentController.php` (`GET /payments`). List with status/method filters;
clicking a row opens a modal with full detail and, for `pending` transfers, Approve/Reject buttons
wired to the existing verify endpoint. **New API endpoint added to support this**:
`GET /api/owner/payments/{id}/proof` (`PaymentController::proof` in
`Api/Owner/PaymentController.php`) — proof-of-transfer images live under `writable/uploads/`
(outside the public webroot, deliberately, so they can't be fetched by guessing a URL) and this
streams the file through the same tenant/permission filter stack as everything else. The web page
can't just point an `<img src>` at it, though — `<img>` requests don't carry the `Authorization`
header — so the page instead `fetch()`es the endpoint with the Bearer token, gets a blob, and
sets `img.src` to `URL.createObjectURL(blob)`. Verified: 200 + correct `image/png` content-type
with a valid token, 401 without one.

**Router page — done**: `app/Views/owner/routers.php` + `app/Controllers/Web/RouterController.php`
(`GET /routers`). CRUD table + modal, same pattern as Customers/Packages, plus a "Test Koneksi"
(`ti-plug`) action per row that calls the existing `POST /owner/routers/{id}/test` and updates
just that row's status badge in place (no full reload) — surfaces the connection error message
inline via the page's toast banner when a router is offline. Password field is `required` on
create, optional on edit (blank = keep existing, matching the API's own semantics) with a
"kosongkan jika tidak diubah" hint. Verified list response never includes the encrypted password
field.

**Broadcast page — done**: `app/Views/owner/broadcast.php` +
`app/Controllers/Web/BroadcastController.php` (`GET /broadcast`). Target selector
(all/active/overdue/custom) driving a single form; picking "Pilih Manual" lazy-loads the active
customer list as checkboxes (only fetched once per page load, not re-fetched on every target
switch). Confirms before sending, same as every other destructive/bulk action in this UI.

**Settings page — done**: `app/Views/owner/settings.php` +
`app/Controllers/Web/SettingsController.php` (`GET /settings`, sidebar fully enabled now — every
item is a real page). Three independent forms (Profil Usaha, Bank & QRIS, Payment Gateway), each
`PUT`s only its own fields to `/api/owner/settings` — a deliberate choice so submitting one section
never touches another, and so the gateway section's own careful handling stays isolated: **the
gateway `<select>` defaults to "- Tidak Diubah -" (empty value), and the form refuses to submit
unless the owner actively picks Midtrans or Xendit** — sending `payment_gateway: ""` would hit the
ENUM column with an invalid value and error under strict SQL mode, so the empty/unchanged case is
never sent at all rather than being sent-and-hoped-safe. Midtrans/Xendit credential fields swap
visibility based on the selection; a "Terkonfigurasi" badge reflects the API's
`payment_gateway_configured` flag (the actual secret is write-only, same as every other credential
field in this app — router passwords, PPPoE passwords).
**Verified all three forms directly against the API** (not just rendering) before trusting them:
profile-only update, bank/QRIS-only update, and a full gateway swap to Xendit and back to
Midtrans — confirmed each leaves the other sections' fields untouched, confirmed the gateway
config re-encrypts correctly in the DB after the swap, confirmed no request ever errors.

**Stage 7 core is now feature-complete for the Owner side**: every sidebar item is a working page
(Dashboard, Pelanggan, Paket, Tagihan, Pembayaran, Router, Broadcast, Pengaturan). **Still open**
(at the time): customer-facing web dashboard/login and a Monitoring page (API ready at
`GET /owner/monitoring/{traffic,users-online}`, no view built yet) — see below for the customer
side, Monitoring is the one remaining piece.

### Stage 7 addendum — WhatsApp gateway made per-tenant; theme_color removed (done)

Two corrections the user caught by actually using the Settings/Broadcast pages, both fixed same
session:

- **WhatsApp gateway was wrongly global.** Stage 6 read `FONNTE_TOKEN` from `.env` — one shared
  token for the entire platform, inconsistent with every other credential in this app (routers,
  QRIS, payment gateway) which are all per-tenant. Fixed to match: added `tenants.whatsapp_token`
  (TEXT, **encrypted at rest** via the same `TenantModel` hook as `payment_gateway_config`, renamed
  to `encryptSecrets()` to cover both). `FonnteWhatsAppSender` now takes the token via constructor
  instead of reading a global config; `NotificationSenderFactory::whatsapp()` takes an optional
  token and falls back to `LogWhatsAppSender` when null/empty; `NotificationDispatcher` resolves
  the *notification's own tenant's* token before picking a sender. `Config\WhatsApp.php` deleted
  (no longer has a reason to exist — nothing global left to hold).
  - Web: new "WhatsApp Gateway" card in `owner/settings.php` — explains the actual flow in plain
    Indonesian (register at Fonnte, connect your own WhatsApp via QR scan, paste the device token
    here), a "Terhubung"/"Belum terhubung" status badge, blank-means-unchanged semantics on the
    token field (matches the router password pattern).
  - API: `SettingsController::withoutSecrets()` now also strips `whatsapp_token` and adds
    `whatsapp_configured` (bool) — same write-only-secret treatment as
    `payment_gateway_configured`.
  - **Verified for real, not just by inspection**: set a (fake) token for melati-net, queued a
    broadcast, ran `notifications:send` — confirmed melati-net's send attempt hit the *real* Fonnte
    API and got back "invalid token" (proves the per-tenant token is actually wired through to the
    HTTP call, and that the request shape reaches Fonnte correctly enough to get a real API
    response rather than a malformed-request error), while sejahtera-net (no token configured) in
    the same dispatch run correctly fell back to the log sender. Confirmed the failed attempt
    stayed `pending` for retry rather than being marked `failed` outright (attempts=1 of 3).
- **`theme_color` removed entirely** — it was being saved and round-tripped through Settings but
  never actually applied anywhere in the UI (no CSS/branding ever read it), a dead setting that
  only added confusion. Dropped the column (migration
  `2026-08-03-090001_TenantWhatsappTokenDropThemeColor.php`, same migration that added
  `whatsapp_token`), removed it from `TenantModel::$allowedFields`, `SettingsController`'s
  accepted update fields, `DemoTenantSeeder`, and the Settings page's Profil Usaha form. If actual
  per-tenant theme branding is wanted later, it needs to be built as a real feature (e.g. a CSS
  custom property the layout reads and applies), not just a stored-but-unused field.

### Stage 7 addendum — Customer-facing web (done)

The `/login` page's own redirect logic already sent `pelanggan`-role users to
`/customer/dashboard` since the very first Stage 7 commit — but nothing lived there until now, so
a real customer trying to log in hit a 404. Prioritized over the Monitoring page (which is
Owner-only and doesn't block any user's actual workflow) for that reason.

- `app/Views/layouts/customer.php` — a separate, simpler shell from `layouts/dashboard.php`: just
  Dashboard/Tagihan/Profil (no Pelanggan/Paket/Router/etc — those are Owner-only), same
  topbar/sidebar chrome and `nethubFetch()` helper reused as-is.
- **Dashboard** (`app/Views/customer/dashboard.php`, `GET /customer/dashboard`) — own status,
  package, and next-due invoice via the existing `GET /api/customer/dashboard`. That endpoint
  needed a small real addition: it now also returns a `tenant` block (name, WhatsApp, bank
  name/account/holder) — **public-safe fields only, never `qris_static_code` or gateway secrets**
  — because the payment flow below needs somewhere to show the customer where to transfer to.
- **Tagihan** (`app/Views/customer/invoices.php`, `GET /customer/invoices`) — list + status filter,
  and the actual payment flow: clicking "Bayar" opens a modal offering QRIS or Transfer.
  - QRIS: calls the existing `GET /api/customer/invoices/{id}/qris`, renders the returned
    `image_data_uri` directly (no blob-fetch dance needed here, unlike the owner payment-proof
    viewer — this endpoint requires auth to *generate* the QR but the response itself is just a
    data URI already carrying the image), then "Saya Sudah Bayar" posts `payment_method=qris`.
  - Transfer: shows the tenant's bank info (from the dashboard endpoint above) + a file input,
    posts multipart `payment_method=transfer` + `proof` to the existing pay endpoint — same
    MIME/size validation as before, no changes needed there.
- **Profil** (`app/Views/customer/profile.php`, `GET /customer/profile`) — view/update name, phone,
  address, optional password change. Email shown read-only (not editable — matches the API, which
  has no path to change a login email).
- **Verified end-to-end against the API** (not just rendering): logged in as a real customer,
  confirmed the new `tenant` block appears with bank info and no secrets, generated a real QRIS for
  a test invoice and got back a valid payload + image, submitted an actual transfer with an
  uploaded PNG proof and got a `pending` payment row back, and confirmed a profile update persists.

Sidebar-wise this closes out Stage 7's customer side entirely.

**2026-08-04 correction — the pages above were verified using a seeded demo account, and that
masked a real gap.** `DemoTenantSeeder.php` inserts `pelanggan`-role `users` rows directly into the
DB for the three demo customers — that's how "logged in as a real customer" above was possible.
But **no controller anywhere creates a `pelanggan`-role account** at runtime: `AuthController::register`
only ever creates `owner` accounts (tenant self-signup), and `CustomerController::create` (Owner
adding a customer's billing/PPPoE profile) never touches `users` or populates `customers.user_id`
(that FK has existed since Stage 1's migration, `SET NULL` on delete, clearly intended for this,
just never wired up). **Net effect: today, zero real customers can ever get a login** — the
Customer web portal is fully built but unreachable outside the seeder. Caught 2026-08-04 while
scoping Stage 13 (below) from the Flutter side — flagging here so this file doesn't keep claiming
"Customer side feature-complete" without the caveat. Fix is Stage 13's provisioning work.

**Monitoring page — done**: `app/Views/owner/monitoring.php` +
`app/Controllers/Web/MonitoringController.php` (`GET /monitoring`, sidebar slot added — it wasn't
even in the menu array before this). Two independent sections: **Pelanggan Online** (reads the
`router:sync`-populated table via the existing `users-online` endpoint — a plain list, explicitly
labeled as ~30s-synced data rather than live, matching how the backend actually works) and **Cek
Traffic Pelanggan** (a customer picker + on-demand live call to `monitoring/traffic` — deliberately
NOT pre-loaded for every customer, since each call is a real router round-trip). The traffic
result is rendered as a generic key-value table rather than named fields, since the exact
RouterOS response shape for `/interface/monitor-traffic` is one of the not-hardware-verified parts
flagged back in Stage 4 — this way the page stays correct even if the real field names differ from
what was assumed. **Verified**: `users-online` returns a clean empty array (no synced data yet,
no crash), `traffic` returns the same graceful connection-timeout message already proven in Stage
4's router testing — both confirm the page handles the no-real-hardware case correctly.

**Stage 7 is now fully done** — every planned page exists on both the Owner and Customer side.

### Stage 7 addendum — frontend never used the refresh token, so sessions died every 15 min (done)

Real bug the user caught by actually staying logged in and working: got logged out constantly.
Root cause — `access_token` correctly expires after 15 min (`JWT_ACCESS_EXPIRY`, by design), but
every page's inline script called the API directly and treated any `401` as "log out", **never
calling the already-working `POST /api/auth/refresh` endpoint at all**. So the very first request
after 15 minutes booted the user, no matter what they were doing.

- Extracted the shared auth/fetch logic out of both layouts (`dashboard.php` and `customer.php`
  had it duplicated inline) into `public/js/nethub-auth.js` — one file, loaded by both, so this
  class of bug can't reappear in just one of the two layouts next time.
- `nethubFetch()` now: on a `401`, calls `nethubRefreshToken()` (hits `/api/auth/refresh` with the
  stored refresh token, rotates both tokens in `localStorage`) and **retries the original request
  once** before giving up. Only an actually-invalid/expired refresh token (~7 days per
  `JWT_REFRESH_EXPIRY`) now triggers a real logout.
- Two call sites that used raw `fetch()` with a manually-attached Bearer header (bypassing
  `nethubFetch()` entirely) got the same retry-once treatment by hand: the payment-proof image
  fetch in `owner/payments.php`, and — more importantly — the transfer-proof upload in
  `customer/invoices.php`'s `submitPayment()`, since filling out a payment form is exactly the
  kind of thing that can take longer than 15 minutes and losing an in-progress upload would be a
  real papercut.
- **Verified**: confirmed `POST /api/auth/refresh` still behaves exactly as tested back in Stage
  2 (valid refresh token → new access+refresh pair), confirmed both layouts load
  `nethub-auth.js` correctly, confirmed no page still has the old dead-end inline version.
- **Not yet done**: no proactive/background refresh (e.g. a timer that refreshes a minute before
  expiry) — this is purely reactive (refresh happens on the first 401 after expiry). That's a
  reasonable simplification, not a further bug: worst case is one extra request per 15-minute
  window silently retries instead of completing on the first try.

### Stage 7 addendum — 3 spec gaps closed: Laporan (Reports), Invoice PDF, Logo & Backup/Restore (done)

Found during a progress recap the user asked for ("apa yang belum dikerjakan di web"). Re-checked
`docs/SPEC.md`'s Owner feature list line by line against the actual code (not just against
CLAUDE.md's own notes) and found 3 real, previously-untracked gaps — user said "kerjakan 1 2 3
di atas bos step bye step" to close all three in one session.

**1. Laporan (Reports) page — new, didn't exist before at all.**
- `app/Services/ReportService.php` — tenant-scoped aggregate queries, same inline-query style as
  `DashboardController::stats()` (not a Repository, since these aren't CRUD resources):
  `revenue()` (paid invoices grouped by day or month via `DATE()`/`DATE_FORMAT()`), `customers()`
  (status counts + by-package breakdown + full customer list), `payments()` (date-range + method/
  status filtered list, with a settled-only revenue total — only `status=verified` counts toward
  the total shown, matching how a payment can be `pending`/`verified`/`rejected`).
- `app/Controllers/Api/Owner/ReportController.php` — `revenue()`, `customers()`, `payments()` (all
  under the pre-existing `reports.view` permission, which turns out was already seeded back in
  Stage 1 and already assigned to owner/admin/kasir — not teknisi — just never had an endpoint
  behind it until now) + one shared `export()` action (`?type=revenue|customers|payments&
  format=csv|pdf`, same filters as its matching read endpoint) that builds the same dataset and
  renders it either as CSV (`fputcsv` over an in-memory stream) or PDF.
- **PDF rendering needed a real library** — `composer require dompdf/dompdf` (pure PHP, no special
  extensions beyond what's already installed, works on shared hosting same as `endroid/qr-code`
  already does). `app/Services/PdfService.php` wraps it with `isRemoteEnabled=false` (nothing here
  should ever fetch an external URL while rendering) so every PDF in the app goes through one
  safely-configured instance. `app/Views/pdf/report.php` is a generic header+table template shared
  by all 3 export types.
- Web: `app/Views/owner/reports.php` + `app/Controllers/Web/ReportController.php` (`GET /reports`,
  sidebar item added between Broadcast and Pengaturan, matching the spec's own feature order).
  3 tabs (Pendapatan/Pelanggan/Pembayaran), each with its own filters + an Export CSV/PDF button
  pair. Export downloads use the same fetch-with-Bearer-header-then-blob pattern already
  established for the payment-proof image and the QRIS payload — `<a download>` triggered from
  the blob URL, filename read back from the response's `Content-Disposition` header.
- **Verified end-to-end with curl**: all 3 read endpoints against real seeded data (revenue
  correctly grouped, customer counts/by-package correct, payment summary correctly excluding
  pending/rejected from the revenue total), both CSV and PDF export (`%PDF-1.7` magic bytes
  confirmed, CSV content confirmed byte-for-byte), and permission enforcement (teknisi 403 on
  `reports.view`-gated routes since it was never in teknisi's permission set, kasir 200 since it
  is).

**2. Invoice PDF download — `invoices.pdf_path` column existed since Stage 1 but was never used.**
- `app/Services/InvoicePdfService.php` — looks up customer/package/tenant directly by ID (same
  trust boundary `CustomerController` already relies on: the invoice itself was already confirmed
  tenant-scoped by `InvoiceRepository::find()` before this runs), embeds the tenant's logo (see
  below) as a base64 data URI if set — same "generate on demand, don't cache a file" choice
  already made for QRIS in Stage 5, so `pdf_path` stays intentionally unused rather than adding a
  second, harder-to-invalidate caching layer for a rarely-hit endpoint.
- `app/Views/pdf/invoice.php` — tenant header (logo/name/address/contact), customer info, period/
  due date/status, amount breakdown (amount/discount/tax/total), paid-at if paid.
- New routes: `GET /api/owner/invoices/{id}/pdf` and `GET /api/customer/invoices/{id}/pdf` (the
  customer one reuses the existing `ownInvoiceOrFail()` guard, so a customer still can never
  download another customer's invoice). Web: a PDF icon added next to every invoice row (both
  `owner/invoices.php` and `customer/invoices.php`) plus the owner's detail-modal footer, all using
  the same authenticated-fetch-then-blob-download pattern as the report exports.
- **Verified for a real edge case, not just the happy path**: generated a PDF for an invoice whose
  customer had since been soft-deleted (`INV-1-00004`, a leftover from Stage 6's testing) —
  renders cleanly instead of crashing, because the template already used `?? '-'` fallbacks
  everywhere. Confirmed logo embedding actually changes the output (3013 bytes without a logo set
  vs 3638 bytes with one, same invoice).

**3. Pengaturan: Logo & Brand + Backup & Restore — both listed in the spec's Settings feature,
neither existed.**
- **Logo**: `tenants.logo` (VARCHAR, already existed since Stage 1, just never had an upload path
  or a place in `SettingsController`'s accepted fields). Added `uploadLogo()`/`deleteLogo()` to
  `Api/Owner/SettingsController.php` — JPG/PNG/WEBP/SVG, 2MB cap (smaller than the 5MB payment-
  proof cap since a logo is a small brand asset, not a scanned document), old file removed on
  replace/delete so changing branding doesn't accumulate orphaned uploads. **Stored under
  `public/uploads/logos/` (not `writable/uploads/`)** — deliberately different from payment proofs,
  because a logo needs to be publicly viewable (invoice PDF header, browser `<img>`) without an
  authenticated blob-fetch dance, unlike a payment proof which must stay gated. Web: new "Logo &
  Brand" card in `owner/settings.php` with a live preview, upload, and remove button.
- **Backup & Restore**: scoped deliberately narrow — `app/Services/BackupService.php` exports/
  restores **packages, customers, and invoices only**. Explicitly excludes: payments (real
  financial transactions — re-inserting them blindly on restore was judged too risky, a decision
  documented here rather than asked about, matching how the payment gateway webhook was deferred
  back in Stage 5 for a similar risk-vs-clarity reason), and every encrypted credential (PPPoE/
  router passwords, payment gateway secrets, WhatsApp token — must be re-entered by hand on a
  genuinely fresh install, same treatment as every other secret in this app).
  - Export denormalizes foreign keys into natural business keys (`customer_code`, `invoice_number`,
    package `name`) instead of raw IDs, since IDs won't mean anything after a restore into a
    different install.
  - **Restore is merge-only by construction, not just by convention**: existing rows (matched by
    `customer_code`/`invoice_number`/package `name`) are always skipped, never overwritten or
    deleted — there is no code path in `BackupService::restore()` that updates or deletes an
    existing row at all. It also always operates on `TenantHelper::requireTenantId()` — the
    currently authenticated tenant — regardless of what tenant the uploaded file was originally
    exported from, so a restore can never cross into another tenant's data. An invoice whose
    customer can't be resolved (deleted customer, or malformed file) is skipped rather than
    guessed, since `invoices.customer_id` is a required column.
  - `app/Controllers/Api/Owner/BackupController.php` — `GET /owner/backup/export` (JSON download),
    `POST /owner/backup/restore` (multipart upload, returns `{created, skipped}` counts per
    entity). Web: "Backup & Restore" card in `owner/settings.php` with a plain-language explanation
    of exactly what is/isn't included, a download button, and a restore file input with a
    confirmation dialog and a result summary.
  - **Verified for real, including the two scenarios that actually matter for a merge-only tool**:
    (1) restored melati-net's own backup back into melati-net itself — 0 created, everything
    correctly skipped as already-existing (proves it's genuinely idempotent, not just "insert
    always"). (2) restored melati-net's backup into the *different* sejahtera-net tenant — 3
    customers + 5 invoices actually created (proves real creation works, not just skip-everything),
    1 invoice correctly skipped (the soft-deleted-customer leftover, unresolvable by design),
    packages correctly matched existing sejahtera rows by name rather than duplicating (both demo
    tenants happen to share package names) — then confirmed via direct DB query that every new row
    landed with `tenant_id=2` (sejahtera's own), and that melati-net's own invoice count was
    completely unaffected (still 6, not doubled) — the tenant-isolation check this feature most
    needed to pass. Test data cleaned up afterward so the demo tenants are back to their original
    seeded state.

**All 3 gaps are now closed — nothing from `docs/SPEC.md`'s Owner feature list is missing from the
web build anymore.** What's left overall is unchanged from before this addendum: real-hardware/
credential validation (MikroTik, Fonnte, Midtrans/Xendit) and the formally separate later stages
(Testing, Optimization, Deployment, Flutter).

### Stage 7 addendum — full user guide PDF (done)

User asked for a complete, from-scratch usage tutorial as a PDF, kept inside the project.

- `app/Views/pdf/user_guide.php` — the source (a real CI4 view, same folder as the report/invoice
  PDF templates), covering all 15 chapters end to end: intro, the 4 staff roles' permission matrix,
  login, and every sidebar menu (Dashboard through Pengaturan including the 3 features from the
  addendum above), the customer-facing Portal Pelanggan, and an FAQ closing chapter answering the
  exact questions the user themselves asked earlier this session (auto-suspend on overdue, router
  connection troubleshooting, WhatsApp not sending, transfer vs. gateway payment, cross-tenant
  restore).
- `app/Commands/GenerateUserGuide.php` — `php spark docs:generate-guide`, a one-off (not scheduled)
  command that renders the view above through the same `PdfService` used everywhere else and
  writes it to `docs/PANDUAN_PENGGUNA_NETHUB.pdf`. Kept as a real command rather than a throwaway
  script specifically so the guide can be regenerated later after a feature change, without
  reconstructing the whole render pipeline from scratch.
- **One real rendering bug caught by actually extracting the PDF's text (`pdftotext`), not just
  eyeballing the HTML**: em-dashes, en-dashes, and the middle-dot character all rendered as a
  replacement/tofu glyph (`�`) — dompdf's base14 "Helvetica" (non-embedded, relies on the PDF
  viewer's own font) doesn't reliably cover those code points the way a real embedded font would.
  Fixed by replacing every instance with plain ASCII (` - ` for dashes, `-` for the middle dot) —
  safer for a business document meant to be read/printed by non-technical users anyway. Re-verified
  after the fix: zero replacement characters anywhere in the extracted text.
- **Verified**: 18-page PDF generated successfully, full text extracted via `pdftotext -layout` and
  read through end to end to confirm every chapter/table/callout box rendered as intended.

### Stage 13 — Multi-tenant SaaS platform: customer self-service + platform billing (planned 2026-08-04, not started)

**Written from a session in the Flutter repo** (`C:\Flutter-NEETHUB`), during a planning discussion
with the user about turning this from "one business's internal tool" into an actual multi-tenant
SaaS sold to WiFi/ISP businesses across Indonesia via Play Store. Recording the full decision here
— not just in the Flutter repo's `CLAUDE.md` — specifically so a backend-only session picking this
up later has complete context and doesn't duplicate or contradict any of it. If you're working
backend-side and this is your first time seeing this section, **read all of it before touching
anything Customer-portal or billing-related** — several silent assumptions elsewhere in this file
(e.g. Stage 7's "Customer side feature-complete") are now known-stale, corrected above.

**Decisions locked in by the user (2026-08-04), do not re-litigate without asking:**
1. Flutter side stays **one app** (`nethub_owner`, no separate `nethub_customer`) — role-based UI
   after login, same pattern as this backend's own `/login` redirect-by-role. Renaming the Flutter
   app away from "Owner-only" branding is a Flutter-repo concern, not tracked here.
2. Platform subscription billing (what a *tenant owner* pays *you*, the platform operator, to use
   NetHub) uses the **same payment rails already built for tenant→customer billing** — the existing
   Midtrans/Xendit gateway abstraction and/or manual transfer + admin verification. **Explicitly
   NOT Google Play Billing** — the user initially wanted Play Billing but agreed once told it's
   Android-only (can't run on the web side at all) and would break the "web and Android 100%
   identical" requirement they consider non-negotiable, plus Google's 15-30% cut.
3. Trial: `tenants.plan`/`tenants.trial_ends_at` already exist in the Stage 1 schema and already get
   set on signup — just never enforced or billed against. 1-month trial, then billing kicks in.
   Exact price (user's example: Rp100.000/bulan) and cycle length should be **configurable**, not
   hardcoded — someone selling this to many different WiFi businesses will want to change pricing
   without a redeploy.
4. Customer self-service (pelanggan login + self-pay) must be **flexible per tenant** — some ISP
   owners may not want it. Needs a per-tenant on/off setting (new column, e.g.
   `tenants.customer_portal_enabled`), defaulting to a sane value TBD (leaning ON, since "flexible
   and usable by anyone" was the user's explicit priority and an unused feature is lower-risk than
   a missing one — confirm before shipping).
5. Customer account provisioning: **auto-generate a password and send it via the existing WhatsApp
   notification pipeline** (`NotificationModel::queue('whatsapp', ...)` + `SendNotifications` +
   `NotificationDispatcher`, already fully wired since Stage 6 — reuse, don't rebuild) the moment an
   Owner/Admin/Kasir saves a new customer record. No self-registration flow for pelanggan — the
   Owner is always the one entering the customer's data first (matches how `CustomerController::create`
   already works today).

**Real gap found while scoping this (see the 2026-08-04 correction note above, in the Stage 7
addendum)**: there is currently **no path at all** for a `pelanggan`-role `users` row to be created
outside `DemoTenantSeeder.php`. `customers.user_id` has sat unpopulated since Stage 1. This is the
biggest single piece of net-new backend work here — everything else (portal toggle, platform
billing) is smaller in comparison.

**Scope breakdown (implement in this order, one sub-phase at a time, pause for review between each
— same one-stage-at-a-time rule as the rest of this file):**

1. **Backend — customer account auto-provisioning + portal toggle**
   - Migration: add `tenants.customer_portal_enabled` (boolean).
   - `CustomerController::create` (and only there — not `update`): when the tenant's toggle is on,
     create a `users` row (role `pelanggan`, random password, `is_active=1`), set the new
     customer's `user_id`, then queue a WhatsApp notification with the login (email) + generated
     password. When the toggle is off, skip account creation entirely — customer record still
     works exactly as it does today (no login, no self-pay, Owner does everything manually — the
     existing supported flow, must keep working unchanged).
   - `/api/customer/*` routes (login already shared via `/api/auth/login`) should reject a
     `pelanggan` login attempt with a clear message if their tenant has since turned the toggle
     off (a tenant could flip it off after accounts already exist — don't silently 500).
   - `SettingsController::show/update` (owner-facing): expose/accept `customer_portal_enabled`.

2. **Backend — platform subscription/billing**
   - New table (e.g. `platform_invoices` or similar — mirror `invoices`' shape/conventions, this is
     billing but for a different payer: the tenant itself, not one of the tenant's customers).
   - A `PlatformBillingService` (mirror `InvoiceService`'s patterns) + a cron command (mirror
     `GenerateInvoices`) that, once a tenant's trial ends, starts generating monthly platform
     invoices against the tenant.
   - Needs *some* place for you (the platform operator, `super_admin` role — already exists as a
     role slug, checked in `AuthController` line ~96, but has **zero panel/controllers built**) to
     see all tenants + their platform billing status and manually verify manual-transfer payments.
     This is realistically a small new Super Admin panel — scope it explicitly before starting,
     it's bigger than it sounds (this file has never built anything super_admin-facing before).
   - Gateway/manual payment reuses the existing `PaymentGateways/` abstraction and
     `PaymentService`-style verify flow — don't build a second parallel payment system.
   - Enforcement: what happens when a tenant's platform invoice goes unpaid past due date? (Read-only
     mode? Full lockout? Grace period?) **Not yet decided — ask the user before building enforcement,
     it's a real product/business call, not a technical one.**

**Phase 1 — done (2026-08-04, written from the Flutter-repo session).**
- Migration `2026-08-04-100001_AddCustomerPortalEnabledToTenants` — `tenants.customer_portal_enabled`
  TINYINT(1), default 1 (on). Added to `TenantModel::$allowedFields`.
- `SettingsController::update` now accepts `customer_portal_enabled` (any truthy/falsy JSON shape,
  normalized via `filter_var(..., FILTER_VALIDATE_BOOLEAN)` before the TINYINT write — didn't use an
  `in_list[0,1]` validation rule because Flutter/web will send a JSON bool, not literal `0`/`1`).
  `show()` needed no change — full tenant row already passes through.
- `CustomerController::create()` → new private `provisionPortalAccount()`, called after router sync,
  same best-effort contract (never blocks/fails the customer-creation request). Returns one of
  `created|skipped_disabled|skipped_no_email|skipped_email_taken` and rides along in the response as
  `portal_account` (same pattern as `router_sync`). Only wired into `create()`, deliberately **not**
  `update()` — matches the decision that the Owner always enters the customer first, no retroactive
  bulk-provisioning path was asked for.
  - Random 10-char password (`generatePassword()`, excludes `0/O/1/l/I` — read off a phone screen,
    not typed), `pelanggan` role via `RoleModel::findBySlug`, `UserModel::insert` (hashed by the
    model's existing `beforeInsert`), then `customers.user_id` backfilled via a second
    `CustomerRepository::update` call.
  - Queues `NotificationModel::queue('whatsapp', 'account_created', 'customer', $id, ['email',
    'password'], $tenantId)` — reuses the Stage 6 queue/dispatch pipeline as decided, no new sending
    infrastructure. New template added to `NotificationTemplates::render()` /
    `NotificationTemplates::accountCreated()`.
  - **Security fix applied during this same phase, not originally planned**: the queued `payload`
    carried the plaintext one-time password, and `notifications.payload` is unencrypted `TEXT` that
    was otherwise kept forever post-send. `NotificationDispatcher::dispatchOne()` now redacts
    `payload.password` to `'[redacted-after-send]'` at the moment `status` flips to `sent`, so the
    plaintext only exists transiently (DB row between creation and successful delivery, plus
    whatever WhatsApp itself retains on the recipient's end — inherent to the "send it over WA"
    decision, not fixable from this side).
- `AuthController::login()` — the existing tenant-status block (already fetched `$tenant` to check
  `status === 'active'`) now also checks: `role_slug === 'pelanggan' && empty($tenant['customer_portal_enabled'])`
  → `403` with a clear Indonesian message. Handles the case where accounts already exist and the
  owner flips the toggle off afterward — they don't silently keep working.
- **Verified end-to-end against the running dev server** (not just code review): logged in as
  `owner@melati.test`, created a real customer with an email → got `portal_account: created`,
  confirmed in MySQL directly that `customers.user_id` was backfilled, a `pelanggan`-role `users` row
  existed, and a `notifications` row was queued with the plaintext password. Toggled
  `customer_portal_enabled` off via `PUT /api/owner/settings` → pelanggan login correctly `403`s with
  the new message; toggled back on → same pelanggan login correctly returns a real access/refresh
  token pair with `role_slug: pelanggan`. Ran `php spark notifications:send` — got the same
  already-documented "Fonnte invalid token" rejection as Stage 6/7 (melati tenant has no real Fonnte
  credential configured, pre-existing/accepted gap, not something this phase introduced), confirming
  the queue → dispatch → real-API-call path is reached correctly; the redaction code path itself
  wasn't exercised by a real `sent` status in this test (needs a tenant with a working WhatsApp
  credential to observe), but was reviewed carefully since it's a straight extension of the existing
  proven success-path branch. Test customer/user/notification rows deleted after verification —
  demo tenant data left exactly as it was before this phase.
- **Not done in this phase, left for later**: no way yet to *manually* trigger/resend provisioning
  for a customer who was created before this shipped, or one that was skipped (no email at the time,
  say). Not asked for — flag if it comes up.

**Phase 2 — done (2026-08-04, same session).** User answered the two open questions from phase 1's
scoping: 3-day grace period ("berat ke shared hosting ga?" — no, it's one date comparison inside the
cron that already runs daily, negligible), and yes to a real Super Admin panel.

- **New tables** (separate from `invoices`/`payments` on purpose — tenant isolation is rule #1, a
  platform invoice must never be reachable through a tenant-scoped query):
  - `platform_settings` — single row (id=1, seeded by its own migration). `monthly_fee`,
    `trial_days`, `grace_days`, plus the platform's own bank transfer details. Super Admin-editable
    via `PUT /api/superadmin/settings` — **this is the "configurable pricing, no redeploy" piece
    from phase 1's scoping note**, don't hardcode a fee anywhere else.
  - `platform_invoices` — mirrors `invoices`' column shape deliberately, unique on
    `(tenant_id, period_start)`.
  - `platform_payments` — mirrors `payments`' shape. `payment_method` enum currently only allows
    `transfer`+`gateway`, but **gateway is schema-only, not implemented** — see below.
  - `tenants.suspended_reason` (nullable VARCHAR) — distinguishes an auto-suspension
    (`unpaid_platform_invoice`, set by `PlatformBillingService`) from a manual one (Super Admin's
    own `suspend` action, freeform reason) from `null` (never suspended). Nothing before this phase
    needed to tell those apart; `AuthController::login`'s existing tenant-status block already
    blocked ALL logins for a suspended tenant regardless of reason, so **zero changes were needed
    there** to make billing enforcement actually lock people out — it was already generic.
- **`PlatformBillingService`** (`app/Services/`) — the whole engine:
  - `generateForTenant()`: skips while `trial_ends_at` is still in the future; first invoice starts
    the day the trial ends (or today, for a tenant with no `trial_ends_at` at all — matters because
    melati/sejahtera, the two main demo tenants, are `plan=pro` with `trial_ends_at=NULL`, i.e.
    already-paying tenants from before this feature existed); each next invoice starts the day
    after the previous one's `period_end`, only once that date has actually arrived. Monthly
    periods, due 7 days after period start. Idempotent — re-running never double-generates.
  - `enforceOverdue()`: flips `unpaid`→`overdue` past `due_date`, then suspends any tenant whose
    oldest overdue invoice is past `due_date + grace_days`.
  - `verifyPayment()`: same shape as `PaymentService::verify` — approve marks the invoice paid and,
    **only if the tenant was suspended specifically for `unpaid_platform_invoice`**, reactivates it
    automatically. A manually-suspended tenant paying their bill does NOT auto-reactivate — that
    needs an explicit Super Admin action, on purpose (a manual suspension might be for abuse, not
    money).
- **`platform:billing` command** (`app/Commands/GeneratePlatformBilling.php`) — daily cron, mirrors
  `invoices:generate`'s structure exactly. Queries tenants where `status='active' OR
  suspended_reason='unpaid_platform_invoice'` (so a billing-suspended tenant's next invoice is still
  ready the instant they pay and reactivate — a manually-suspended tenant gets skipped entirely,
  correctly, since they shouldn't keep accruing bills while blocked for an unrelated reason).
- **`superadmin:create` command** — one-off, not exposed via any API (no self-registration for this
  role, deliberately — see Stage 13's decisions). `php spark superadmin:create --name "..." --email
  ... --password "..."`. **A real one was created this session as a working demo/dev login**:
  `superadmin@nethub.test` / `SuperAdmin123` — same tier as the existing `owner@melati.test` demo
  credentials, not a production secret (nothing is deployed yet).
- **API**: `/api/owner/platform-billing` (view own invoices + platform bank info, submit manual
  transfer proof — proof stored under `WRITEPATH.'uploads/platform_payment_proofs/'`, **outside the
  public webroot**, same reasoning as `Owner\PaymentController::proof`, streamed back through an
  authenticated endpoint rather than a guessable URL) gated by `permission:settings.manage` (reused,
  not a new slug — matches that permission already being owner-only, which is the right scope for
  paying the platform bill). New `/api/superadmin/*` route group, filter `jwtauth:super_admin` only
  (no `tenant` filter — `TenantFilter` already no-ops for super_admin, the group just omits it for
  clarity): tenant list/detail/suspend/reactivate, pending-payments/verify/proof, settings.
- **Web**: new `layouts/super_admin.php` (mirrors `layouts/customer.php`'s minimal-sidebar shell),
  3 pages under `app/Views/superadmin/` (tenant list, tenant detail with an inline verify-payment
  modal, settings form) + matching `Controllers/Web/SuperAdmin/*`. `auth/login.php`'s role-redirect
  ternary extended to a 3-way branch for `super_admin` → `/superadmin/tenants`. No server-side auth
  filter on these routes — matches this app's existing convention (`owner/*` and `customer/*` web
  routes have none either), the client-side `nethub-auth.js` guard (redirects to `/login` if no
  token) is the only gate, consistent everywhere.
- **Bug caught during testing, fixed before it shipped**: `PlatformPaymentController::verify`
  initially used a validation rule (`in_list[0,1]`) for the `approve` field — same mistake already
  documented and fixed once in `Owner\PaymentController::verify`'s own docblock (`in_list[]`
  compares against string literals, doesn't reliably match a real JSON boolean). Found it by
  actually calling the endpoint with `{"approve": true}` and getting a validation error, not by
  re-reading the other controller first — worth remembering to check for known-fixed patterns
  *before* writing similar code, not after it breaks the same way again.
- **Verified end-to-end against the running dev server, full real flow, not just unit-level**:
  generated real platform invoices for melati+sejahtera via `platform:billing` (confirmed
  `trial_ends_at IS NULL` correctly falls through to "bill starting today" rather than skipping
  forever), confirmed idempotency on re-run, manually backdated an invoice's `due_date` to simulate
  5 days overdue against a 3-day grace period → confirmed real auto-suspend, confirmed a real
  `owner@melati.test` login gets the existing "tenant suspended" 403 with zero code changes needed,
  logged in as the new real `superadmin@nethub.test`, listed tenants via the API, confirmed a
  non-super_admin gets a real 403 on `/api/superadmin/*`, uploaded a real multipart transfer-proof
  file as the owner, viewed it back as the proof-streaming endpoint (confirmed `image/png`
  content-type), verified it as Super Admin → confirmed invoice flipped to `paid` AND the tenant
  auto-reactivated in the same request, tested settings update and manual suspend/reactivate.
  Rendered all 3 new web pages (200 OK, no leaked PHP warnings in the HTML). All test-induced state
  (extra invoices/payments/uploaded proof file/settings values) reset back to clean afterward —
  melati/sejahtera now each have exactly one clean real `unpaid` platform invoice (due 2026-08-11),
  which is the actual correct end-state of this feature going live, not test pollution.
- **Explicitly NOT built this phase, by design**: platform-level Midtrans/Xendit gateway checkout.
  `payment_method` enum allows `gateway` at the schema level so it's not a breaking change to add
  later, but wiring it needs (a) the user's own platform-level merchant credentials (env-based —
  **must never** reuse `PaymentGatewayFactory::make($tenant)`, that resolves the *tenant's own*
  merchant config, would deposit the platform fee into the *tenant's* account instead of the
  platform operator's — see `PaymentGatewayInterface`'s own docblock), and (b) a webhook route that
  can tell a platform-invoice payment apart from a tenant-invoice one. Manual transfer + Super Admin
  verification is the only working payment path right now — matches the user's own confirmed
  decision (no Google Play Billing, reuse the same rails as tenant→customer billing) but gateway
  automation specifically wasn't part of that decision and needs a fresh ask before starting.
- **Not decided yet, flag before building**: nothing currently reminds a tenant their platform
  invoice is coming due or overdue (no WhatsApp/email notification wired for this, unlike
  `invoices:remind` on the customer-billing side) — not asked for, but likely wanted once this goes
  live for real.

3. **Web — registration page + platform billing page**
   - `GET /register` view + wiring to the already-existing `POST /api/auth/register` (that endpoint
     has always existed, un-fronted by any web page — Android's had a Register screen since its own
     Stage 1, web never did; this closes that asymmetry).
   - A tenant-facing page to see/pay their own platform subscription invoice.

4. **Flutter (`nethub_owner`, this repo's own progress tracked in `C:\Flutter-NEETHUB\CLAUDE.md`,
   not duplicated here)** — role-based post-login split, new customer-role screens, platform
   billing screen for the Owner role, portal-toggle field in the existing Settings screen.

5. **Web + Android — dynamic tenant branding.** Separately confirmed gap while scoping this: neither
   platform currently shows a logged-in tenant's own name/logo anywhere in its own chrome —
   `layouts/dashboard.php`'s sidebar and `Views/pdf/report.php`'s title both hardcode "NetHub"
   literally, and the Flutter app's app bar/splash hardcode "NetHub Owner"/"NetHub". Only the
   invoice PDF (`InvoicePdfService`) already pulls real tenant name/logo dynamically. Wire the rest
   to do the same. This is **in-app dynamic branding only** (tenant name/logo shown after login) —
   explicitly NOT a white-labeled/per-tenant Play Store listing, which the user considered and
   deliberately ruled out as much bigger scope than needed right now.

## Cross-cutting requirements to not lose track of

`docs/SPEC.md` has a Testing Strategy, Deployment Strategy, Performance Targets, Migration/Data
Integrity, Monitoring/Maintenance, and a pre-production Security Checklist — these apply across
many stages, not just one, so it's easy to silently drop them. Known gaps as of Stage 6, **by
design** (not bugs — just not due yet):

- **CORS is not configured.** CI4 ships a `cors` filter alias but it isn't wired up. Needs real
  config once a browser-based client (web frontend, Stage 7) calls this API cross-origin. (Almost
  got configured ad hoc mid-Stage-5 to test login from a browser page — deliberately held off so
  it lands properly with the actual web frontend stage instead of as a rushed side quest.)
- **CSRF tokens are intentionally NOT used on the JSON API.** The security checklist assumes
  CSRF protection on POST/PUT/DELETE, which is a cookie-session concern — this API is stateless
  Bearer-JWT only, which is inherently not CSRF-exploitable. If Stage 7's web frontend ends up
  using session/cookie auth for anything, CSRF will need to come back for that surface.
- **`CI_ENVIRONMENT=development`** — CI4 currently shows full stack traces/file paths on error
  (confirmed multiple times now during testing across Stages 2–3). This is correct for local dev
  but **must** become `production` before deployment (Security Checklist: "Error messages don't
  expose system internals").
- **Local DB password is empty** (XAMPP default root/no-password) — fine for local dev, but the
  checklist's "25+ char random DB password" applies to the real hosting environment (Stage 12).
- ~~File upload security~~ — **done in Stage 5** (MIME allowlist + 5MB cap on payment proof
  uploads). Virus scanning is not implemented — real AV scanning needs infrastructure (ClamAV or
  similar) that typical shared hosting doesn't provide; noting this as an accepted limitation
  rather than a silently dropped requirement.
- Dashboard stats cache (5 min TTL) has no explicit invalidation on customer/invoice writes yet —
  acceptable staleness window for now, revisit in Stage 11 (Optimization).
- ~~Payment gateway webhook not built~~ — **done** (Midtrans + Xendit, tenant-selectable, see the
  Stage 5 addendum above).
- WhatsApp (Fonnte) and Push (FCM) senders are unverified against a real *working* credential (see
  Stage 6, and the Stage 7 addendum) — the queue/dispatch/retry plumbing is fully tested, and
  Fonnte's outbound call was confirmed to reach the real API and get a real "invalid token"
  rejection (stronger evidence than pure code review, same tier as Midtrans/Xendit below), but
  nobody has connected an actual WhatsApp number via a real Fonnte account yet. Push has no real
  device to send to at all until the Flutter stage. Midtrans/Xendit's outbound calls got a stronger check
  (real sandbox rejection responses, not just code review) — see the Stage 5 addendum.
- Test coverage (70% critical path target, PHPUnit) hasn't started — Stage 10 in the sequence,
  but consider adding tests incrementally per stage rather than saving it all for the end.
- HTTPS enforcement, SSL, CDN, backup encryption/restore drills — all Stage 12 (deployment)
  concerns, correctly not addressed yet.
- **When touching `.env` `logger.threshold`**: leave it unset. CI4's own default is already
  environment-aware; a flat override silently swallowed info-level logs for a while during
  Stage 6 (see that stage's notes) before being caught and removed.
