# Everything Immersive — Documentation

**[`../CLAUDE.md`](../CLAUDE.md)** (repo root) is the primary dev/ops guide — deploy mechanics, architecture, domain model, conventions, gotchas. Start there. **[`../README.md`](../README.md)** has the quick-start + the domain status-code reference. These docs go deeper on specific areas.

## Open work & issues
- The current open-work list is kept outside the repo (it references infrastructure). Ask the maintainer.

## History (completed logs — kept for reference)
- **[test-suite-findings.md](./test-suite-findings.md)** — the 2026-05-31 test-suite build + bug-fix passes (H/M/L findings and the X1–X6 pre-deploy review). Mostly resolved.

## Technical references
- **[TIMEZONE_STANDARDIZATION.md](./TIMEZONE_STANDARDIZATION.md)** — timezone handling: `dateUtils.js`, frontend/backend flow, edge cases.
- **[GEO_QUERY_DEBUGGING_GUIDE.md](./GEO_QUERY_DEBUGGING_GUIDE.md)** & **[TESTING_GEO_FIXES.md](./TESTING_GEO_FIXES.md)** — geo/location query debugging + verification.

---
_Consolidated 2026-05-31; infrastructure-specific docs (`AUDIT.md`, `server-audit.md`) moved out of the repo 2026-09-13 ahead of open-sourcing._
