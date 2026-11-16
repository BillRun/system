# BillRun OpenAPI Specification

This directory holds the OpenAPI 3.1 specification for the BillRun REST API.
It is split across multiple files and stitched together via `$ref` from the
root [openapi.yaml](openapi.yaml).

## Layout

```
docs/api/
├── openapi.yaml              # Root entry point (info, servers, security, tags)
├── paths/
│   ├── auth.yaml             # /auth/login, /auth/logout, /auth/options
│   ├── oauth2.yaml           # /oauth2/token
│   ├── billapi.yaml          # /billapi/{collection}/{action} (CRUD + lifecycle)
│   ├── credit.yaml           # /api/credit, /api/bulkcredit
│   ├── payment.yaml          # /api/pay, /api/bill, /api/cards, /api/adjustpayments, /api/onetimeinvoice
│   ├── realtime.yaml         # /realtime (rating/charging events)
│   ├── reports.yaml          # /api/reports, /api/report
│   └── outgoing.yaml         # Outgoing calls BillRun makes to a CRM in external-subscribers mode
└── components/
    ├── parameters.yaml       # Reusable query/path parameters
    ├── responses.yaml        # Reusable response envelopes & error responses
    ├── securitySchemes.yaml  # Session cookie, OAuth2, secret/signature
    └── schemas/              # Entity schemas (Account, Subscriber, Plan, ...)
```

## Source of truth

Endpoint shapes were derived from the running codebase:

- `application/modules/Billapi/` - the `/billapi` module (collection + action routing)
- `conf/modules/billapi/*.ini` - per-collection parameter definitions
- `application/controllers/Api.php` + `application/controllers/Action/*` - legacy `/api/<action>` endpoints
- `application/controllers/Realtime.php` - realtime charging
- `application/controllers/Auth.php`, `Oauth2.php` - authentication

The public reference at https://docs.bill.run/en/api/ is the authoritative
description of behavior; this spec aims to mirror it.

## Conventions

- All `billapi` endpoints accept `POST` (the wrapper allows `GET` for reads but
  `POST` is the canonical form because parameters can be deep JSON).
- Date/time fields are ISO-8601 unless noted otherwise.
- Responses use a common envelope: `{ status, desc, details, ... }`. See
  [components/responses.yaml](components/responses.yaml).

## Rendering / validation

```bash
# Validate
npx @redocly/cli lint docs/api/openapi.yaml

# Bundle to a single file (useful for Swagger UI)
npx @redocly/cli bundle docs/api/openapi.yaml -o docs/api/openapi.bundled.yaml

# Preview with Redoc
npx @redocly/cli preview-docs docs/api/openapi.yaml
```

## Coverage status

This spec is an initial cut. It documents:

- the routing pattern, security schemes, common response envelope
- every billapi collection + supported action (parameter lists derived from the .ini configs)
- the most commonly used `/api/<action>` endpoints (credit, pay, bill, cards, reports)
- the realtime, auth, and oauth2 endpoints

Per-entity response schemas are intentionally permissive (`additionalProperties: true`)
because BillRun supports custom fields per tenant. Tighten them locally if you need
strict typing for a specific deployment.
