# Phase 0 Blueprint

Phase 0 is accepted and locked.

## Product Decision

The NiCare ODK system is separate from `new-web`:

- Separate web portal
- Separate mobile app
- Separate backend/API
- API interoperability with `new-web`

## Source Of Truth

`new-web` owns:

- Active facilities
- LGAs
- Wards
- Insurance programmes and types
- Optional enrollee lookup/details
- Optional receiving endpoint for reviewed assessment results

ODK owns:

- Form builder
- Versioned assessment forms
- Assessment submissions
- Answers
- GPS records
- Evidence files
- Signatures
- Reviews
- Reports
- Sync and delivery logs

## Initial Roles

- `admin`
- `form_manager`
- `assessor`
- `reviewer`
- `reports_viewer`
- `integration_admin`

## Initial Form

The first seeded form will be `NiCare QA Checklist - Version 1`, based on the accepted
QA checklist extraction and required additions:

- Facility ID, name, LGA, and ward
- Assessment ID, date, and assessor ID
- GPS coordinates
- Photos and supporting documents
- Assessor and facility representative signatures

## Design Source

The web portal and mobile app should adapt the supplied NiCare Quality Assurance UI
concepts: clean white surfaces, deep green actions, soft green states, compact cards,
offline-first mobile feedback, and review/reporting workflows.

