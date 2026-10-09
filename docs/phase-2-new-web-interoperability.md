# Phase 2: `new-web` Interoperability

## Goal

ODK caches NICARE reference data from `new-web` so the mobile app can work offline
while `new-web` remains the source of truth.

## Current Source Endpoints

The default ODK integration client reads these `new-web` endpoints:

- Facilities: `/api/facilities`
- LGAs: `/api/lgas`
- Wards: `/api/wards`
- Insurance programmes: `/api/premium/metadata`

Configure them in `odk/backend/.env` if the main NICARE system exposes dedicated
ODK endpoints later.

## ODK Cache Tables

- `reference_lgas`
- `reference_wards`
- `reference_facilities`
- `reference_insurance_programmes`
- `integration_sync_logs`

## Commands

Run migrations:

```powershell
cd C:\Users\DELL\Documents\Projects\nicare-systems\odk\backend
php artisan migrate
```

Sync reference data:

```powershell
php artisan nicare:sync-reference-data
```

Check integration status:

```powershell
Invoke-WebRequest -Uri http://odk-web.test/api/integrations/nicare/status -UseBasicParsing
```

## Portal

The web portal Integrations screen calls:

- `GET /api/integrations/nicare/status`
- `POST /api/integrations/nicare/sync`

Use Herd for Laravel at `http://odk-web.test`, and run Vite separately:

```powershell
npm.cmd run dev
```

