# Implementation architecture

## Request and tenant boundary

Tenant-facing URLs carry a stable slug. `ResolveTenant` resolves it before application code and sets a request-scoped `TenantContext`; `EnsureTenantMember` then checks the active pivot role. Tenant models add a global scope and write-time tenant assignment, while controllers also constrain security-sensitive queries by `tenant_id`. Composite database constraints prevent common cross-tenant collisions.

```text
request /t/{slug}/...
  -> resolve tenant
  -> authenticate session
  -> verify active tenant membership and role
  -> tenant-scoped controller/service
  -> audit sensitive mutation
```

Platform administrators do not automatically receive member-level access. The platform dashboard contains tenant metadata and aggregate health only.

## Theme publication

Themes are normalized against a finite schema. Arbitrary CSS, HTML, URLs in colors, SVG, and tenant Tailwind class construction are not accepted. Drafts are isolated from the live pointer.

Publication validates WCAG pairs, archives the previous published row, marks the draft immutable, updates `tenants.published_theme_id`, adds an audit event, and invalidates the presentation cache in one database transaction. Rollback copies history into a new version and publishes that copy.

Generated CSS is versioned and immutable:

```text
/themes/{tenantSlug}/{version}.css
```

Tenant pages load it in the server-rendered document head before React hydration. The same published configuration drives logos, favicon, public copy, the tenant manifest, and member/staff navigation.

Uploaded images are checked by file size, detected MIME, actual decode, and pixel count; decoded pixels are re-encoded to WebP, which removes metadata and blocks polyglot/HTML payloads. Deleting a draft reference soft-deletes its database record; files remain recoverable for retention cleanup.

## Payment integrity

The browser creates a payment intent but is never proof of payment. The server sends STK Push through `StkPushGateway`. The universal callback:

1. hashes and durably stores the raw event;
2. correlates the checkout identifier under a row lock;
3. validates success, receipt, and expected amount;
4. creates one unique payment;
5. allocates only the outstanding invoice amount;
6. stores any excess as unapplied credit;
7. resolves the intent and event transactionally.

Duplicate payloads and receipt numbers are idempotent. Mismatches move to review, and the scheduled maintenance command flags attempts with no final callback after 15 minutes.

## Data model conventions

- ULIDs are public/domain identifiers; user authentication retains Laravel’s internal integer key.
- Money uses integer minor units.
- Timestamps are stored in UTC and rendered in `Africa/Nairobi`.
- Issued invoice items and membership plan snapshots preserve historical commercial terms.
- Member consents point to immutable, tenant-owned consent versions.
- Operational records include both tenant and location identifiers even though V1 exposes one active branch.

## Modules

The code is organized around Platform, Tenancy, Themes, Identity, Members, Memberships, Billing, Payments, Scheduling, Attendance, Notifications, Reporting, and Compliance. It remains one deployable application and one transactional database, which is appropriate for the initial ten-tenant shared-hosting target.

## Deliberate integration boundaries

Live Daraja and Africa’s Talking operations require external credentials and callback allow-listing. eTIMS output is a preparation CSV only. Door controllers, offline check-in, direct tax submission, card payments, native mobile apps, custom domains, and public third-party APIs remain outside V1.
