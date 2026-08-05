# Build spec: clients slice

Exemplar to replicate: admin-catalogs (muscle-group entity pattern).
Tenant app. Schema tables: users, client_profiles, client_equipment, invitations —
never modify them. Coach-facing except client-edit-own-profile.

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| clients-list | /clients/ | Roster per canonical table pattern (coach) |
| client-add | /clients/new | Invite form (coach) |
| client-view | /clients/{id} | Detail: profile, current program, latest measurements, adherence summary (coach) |
| client-edit | /clients/{id}/edit | Profile + equipment editor (coach, or client editing self at /settings/profile) |

## List screen

- Columns: name, email, status (dot+badge), coach (display_name), last activity (date,
  medium), current program (name or —). Search: name, email. Sort allowlist: display_name,
  status, created_at (default display_name). Page size 25. Row → client-view.

## Forms

- **Invite** (client-add): email (email, required, normalized) · display_name (required) ·
  role (select coach/client, default client) · assigned coach (select of coaches, shown
  when role=client, default current user). Submit → invitations row + MaluMail send.
  Ids: client-form-field-{name}.
- **Profile** (client-edit): display_name · status (select active/paused/archived) ·
  assigned_coach_id (select) · birthdate (date) · sex (select male/female/unspecified) ·
  height_cm (number 50–280, shown in preferred units) · activity_level (select of the 5
  levels with plain-English labels) · units_preference (select metric/imperial) ·
  equipment (checkbox grid over active equipment rows → client_equipment).

## Files (exactly these)

- public/clients/index.php · form.php · save.php · invite.php · view.php
- app/features/clients/queries.php
- app/views/clients/page.php · partials/table.php · row.php · form.php · view.php · saved.php

## Query functions

- find_clients(PDO, search='', sort='display_name', page=1): array
- find_client(PDO, int id): ?array   (joins profile + equipment ids)
- insert_invitation(PDO, string email, string name, string role, int coachId, int invitedBy, string tokenHash): array
- update_client_profile(PDO, int userId, …explicit profile fields): array
- replace_client_equipment(PDO, int userId, array equipmentIds): void
- accept_invitation(PDO, string tokenHash, string passwordHash): ?array  (used by auth flow)

## Action manifest entries

- Screens: the 4 above (already in action-manifest.md).
- Actions: client_invite (undo: revoke invitation) · client_archive (**confirm** — sets
  status archived; undo n/a).

## Activity log events

- screen_view per GET; client_invited, invitation_accepted, client_updated (before/after),
  client_archived.

## Status vocabulary

- invited → info · active → success · paused → warning · archived → secondary.

## Out of scope

- Self-signup, password-set pages (Phase 2 auth owns invitation acceptance UX), client
  deletion (archive only), coach CRUD beyond invite (owner invites coaches with the same
  form), tenant provisioning.

## Open Questions

- (none)
