---
paths:
  - 'app/Http/Controllers/Api/**'
---

# Api

## Web and API parity for the Next.js frontend
Reuse the Admin LemburQuery, PegawaiQuery, DashboardService, lock service and PDF exporter when replacing web data. Preserve JsonApiResource contracts: dropdowns at meta.pegawaiOptions, filters at meta.filters, pegawai detail history via its existing /lemburs endpoint (default 10). Next.js uses Sanctum stateful sessions and existing Fortify JSON endpoints for MFA/password confirmation; the legacy bearer login is not MFA-equivalent. See WEB_API_AUDIT.md and API parity tests before changing these contracts.
