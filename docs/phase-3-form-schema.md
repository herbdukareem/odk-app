# Phase 3: Form Schema And Seeder

## Goal

Add the configurable ODK form foundation and seed the first published form:

- `NiCare QA Checklist`
- Version `1`
- Offline enabled
- Uses NICARE reference facilities, LGAs and wards from Phase 2

## Tables

- `assessment_forms`
- `assessment_form_versions`

The first version stores:

- metadata schema
- sections
- fields
- validation rules
- visibility rules
- scoring rules
- evidence rules
- signature rules
- review flags for ambiguous checklist scoring

## Commands

```powershell
cd C:\Users\DELL\Documents\Projects\nicare-systems\odk\backend
php artisan migrate
php artisan db:seed --class=NicareQaChecklistSeeder
```

## APIs

- `GET /api/forms`
- `GET /api/forms/{slug}`
- `GET /api/mobile/forms/published`

