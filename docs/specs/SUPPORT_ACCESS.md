# Support Access Spec
_Last updated: 2026-03-19_

This spec defines the temporary tech-support access flow for CryptoZing.

Use this doc for support login behavior, owner-granted support access, expiration/revocation rules, and support-only read surfaces.
Use [`../PRODUCT_SPEC.md`](../PRODUCT_SPEC.md) for global ownership and trust boundaries.

## Purpose
CryptoZing needs a narrowly scoped way for tech support to inspect an owner's invoices and clients during troubleshooting without weakening the product's owner-scoped data model.

## Scope
- Dedicated tech-support login entrypoint.
- Owner-controlled support access grant from authenticated settings.
- Fixed automatic expiration on the support grant.
- Owner revocation at any time.
- Read-only support views for the granted owner's invoices and clients.

## Out of Scope
- Support impersonation of owner sessions.
- Support edits, deletes, sends, wallet changes, or payment adjustments.
- Per-agent grant selection or approval workflows.
- Public-facing support access flows.
- Email or notification automation around the grant.

## Product Rules
1. Support access is opt-in and owner-controlled.
- Support may view owner data only while the owner has an active support-access grant.
- No owner grant means no access, even for valid support accounts.

2. Support access is read-only.
- Support may view invoices and clients.
- Support may not create, edit, delete, deliver, void, restore, or adjust invoices or clients.
- Support may not view or change wallet keys, profile secrets, password flows, or notification settings.

3. Support access is temporary.
- Grant duration is fixed by product configuration and may not be chosen per owner.
- The MVP/RC default is `72 hours`.
- Expiration must be enforced server-side on every support request.

4. Owners may revoke access at any time.
- Revocation must take effect immediately.
- Revocation must not require support-side action.

5. The UI copy must make the permission explicit.
- The owner-facing setting must plainly state that enabling the setting authorizes CryptoZing tech support to view that owner's invoices and clients for troubleshooting.
- The copy must also plainly state that access is temporary, read-only, and revocable at any time.

6. Support access must not weaken the owner-scoped product model.
- Existing owner routes/policies remain owner-first.
- Support access should use dedicated support routes/surfaces instead of silently broadening owner CRUD behavior.

## Support Accounts
- Support accounts are normal authenticated users identified by a configured allowlist of support email addresses.
- The product should provide a dedicated `/support/login` entrypoint so support does not need to use the standard owner-oriented login framing.
- Support users should land on a support dashboard rather than the normal owner dashboard after login.

## Owner Setting Behavior
- The owner-facing support section should live under authenticated settings.
- When no grant is active, the UI should show:
  - the current fixed duration
  - the explicit permission copy
  - a primary action to grant support access
- When a grant is active, the UI should show:
  - that support access is active
  - when it expires
  - a revoke action
- Saving the grant should set:
  - `support_access_granted_at`
  - `support_access_expires_at`
  - `support_access_terms_version`
- Revoking the grant should immediately clear active access.

## Owner Copy Requirements
The owner setting copy should communicate all of these points in plain language:
- CryptoZing tech support may view this account's invoices and clients.
- The permission is for troubleshooting.
- The permission is read-only.
- The permission automatically expires after the fixed duration.
- The owner may revoke the permission at any time.

## Support Surface Requirements
- Support dashboard must list owners with currently active grants.
- Support must be able to open read-only invoice and client pages for a granted owner.
- Support surfaces should clearly indicate:
  - which owner is being viewed
  - that the view is read-only support access
  - when the access expires
- If the grant expires or is revoked while support is navigating, the next request must deny access cleanly.

## Data Model Requirements
The owner record must persist enough state to determine whether support access is currently valid.
Minimum required fields:
- `support_access_granted_at`
- `support_access_expires_at`
- `support_access_terms_version`

Optional audit fields may be added later if support usage grows, but they are not required for RC.

## Verification Targets
- Owner can grant support access and see the expiration timestamp.
- Owner can revoke support access immediately.
- Support login is separate and only succeeds for configured support users.
- Support dashboard lists only owners with active grants.
- Support can view granted owners' invoices and clients in read-only surfaces.
- Support loses access immediately after revocation or expiry.
- Owner CRUD routes remain owner-scoped.
