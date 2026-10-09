# Phase 1 Foundation

## Goal

Create the separate ODK product foundation:

- Laravel backend skeleton
- Vue web portal shell inside the Laravel backend
- Expo mobile app shell
- NiCare QA design tokens
- Placeholder auth and navigation
- Integration settings placeholders for `new-web`

## Laravel Monolith Baseline

The ODK web portal and API live in one Laravel app under `odk/backend`.

Laravel owns:

- API routes
- Web route fallback
- Blade entry point
- Vite build pipeline
- Vue portal shell

Local Herd URL:

- `http://odk-web.test`

When using Herd, do not run `php artisan serve`; Herd serves the Laravel app.
Run `npm run dev` separately for Vite assets while developing the Vue portal.

Vue owns the interactive portal UI inside `resources/js`.

Initial backend endpoints:

- `GET /api/health`
- `GET /api/bootstrap`

Initial backend concepts:

- ODK-owned auth will be added in a later phase.
- `new-web` integration is represented by config placeholders.
- Form, submission, and sync tables start in later phases.

## Web Portal Baseline

Initial screens:

- Sign in
- Dashboard
- Forms
- Submissions
- Facilities
- Reports
- Integrations
- Team
- Settings

## Mobile Baseline

Initial screens:

- Sign in
- Home
- Forms & downloads
- Facilities
- Assessments
- Sync centre
- More
