# Navigation, Theme, and Management Implementation Plan

## Objective

Close the authentication-cache, navigation, customization, and entity-management gaps while preserving tenant isolation and the existing Kenya-first product model.

## Phase 1 — Authentication and navigation integrity

1. Add explicit no-store/no-cache headers to authenticated and sensitive tenant responses.
2. Add client-side handling for browser back/forward restoration so a logged-out session cannot display a stale authenticated Inertia page.
3. Ensure logout invalidates the session and regenerates the CSRF token.
4. Add regression tests for logout followed by back navigation semantics and authenticated response headers.
5. Audit all navigation items for correct tenant-aware URLs, role visibility, active state, and mobile behavior.

## Phase 2 — Theme and content studio discoverability

1. Make Theme Studio clearly reachable for owners and managers.
2. Split controls into brand, light/dark colors, typography, shape/density, navigation, layouts, imagery, and public/client copy.
3. Add member-dashboard content controls and preview surfaces.
4. Improve logo, icon, hero, and kiosk asset upload status, focal controls, deletion, and error feedback.
5. Add preview modes for public page, login, staff dashboard, member dashboard, and kiosk.
6. Retain immutable publish/rollback semantics and WCAG publication gates.

## Phase 3 — Platform tenant management

1. Make tenant-directory rows actionable.
2. Add tenant detail with subscription/trial status, usage, locations, staff, theme, incidents, and scheduler health.
3. Add safe platform actions for status transitions and audited support-access grants.
4. Keep member-level data hidden unless an active support grant exists.

## Phase 4 — Gym entity management

### Members

- Add member detail with membership, invoices, payments, bookings, check-ins, consents, and emergency contact.
- Add edit, status, pause/cancel, and audited enrollment actions.

### Plans

- Add edit, enable/disable, archive, and detail/usage views.
- Prevent destructive deletion when issued records reference a plan.

### Payments

- Add invoice and payment detail, allocations, intent attempts, callback state, and receipt view.
- Add audited manual confirmation boundaries without allowing browser-generated payment proof.

### Schedule

- Add class detail, edit/cancel, roster, booking/waitlist, and attendance management.
- Protect capacity and waitlist promotion with transactions.

### Check-ins

- Redesign for a scanner-first workflow, clear accepted/denied states, searchable member results, recent activity, and check-in detail.
- Preserve rotating token, wrong-tenant, inactive membership, and five-minute duplicate rules.

## Phase 5 — Verification and publication

1. Add feature tests for every new authorization and mutation path.
2. Run Pint, PHPStan, PHPUnit, ESLint, Prettier, TypeScript, and production Vite build.
3. Handoff to the tester via `collaboration.md`.
4. Resolve and retest findings until `VERIFIED`.
5. Create a GitHub repository, commit the complete project, and push the verified default branch.

## Initial risk notes

- GitHub CLI is not currently installed; repository creation will use the connected GitHub app if it exposes repository creation, otherwise CLI installation/authentication will be required.
- Theme publication already uses immutable versions; new controls must extend the schema without mutating historical configurations.
- Service-worker caching must never cache authenticated HTML or sensitive Inertia responses.
- Platform support tooling must not bypass tenant-level member-data authorization.
