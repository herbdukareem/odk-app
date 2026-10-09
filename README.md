# NiCare Quality Assurance ODK

Separate ODK-style quality assurance system for NiCare facility assessments.

## Phase 1 Stack

- Backend/API and web portal: Laravel + Vue monolith
- Mobile app: Expo + React Native

## Relationship To `new-web`

`new-web` remains the source of truth for NICARE master data such as active facilities,
LGAs, wards, insurance programmes, and optional enrollee lookups. This ODK system
will integrate through APIs and cache reference data for offline assessment capture.

Phase 2 reference sync details live in `docs/phase-2-new-web-interoperability.md`.
Phase 3 form schema and seeder details live in `docs/phase-3-form-schema.md`.

## Apps

- `backend/` - ODK Laravel API, integration layer, and Vue web portal.
- `mobile-app/` - Offline-first field assessment app.
- `docs/` - Planning notes and contracts.
