# Everything Immersive — Documentation

**[`../CLAUDE.md`](../CLAUDE.md)** (repo root) is the primary dev/ops guide — server access, deploy mechanics, architecture, domain model, conventions, gotchas. Start there. **[`../README.md`](../README.md)** has the quick-start + the domain status-code reference. These docs go deeper on specific areas.

## Open work & issues
- **[AUDIT.md](./AUDIT.md)** — the **single canonical** open-issues / open-work list (security · performance · correctness · frontend), plus the post-deploy verification checklist, cutover follow-ups, secret-rotation list, and test-coverage gaps. _Re-verify items against code — several were fixed in the 2026-05-31 test-suite branch._

## History (completed logs — kept for reference)
- **[test-suite-findings.md](./test-suite-findings.md)** — the 2026-05-31 test-suite build + bug-fix passes (H/M/L findings and the X1–X6 pre-deploy review). Mostly resolved.
- **[server-audit.md](./server-audit.md)** — the 2026-05-26 server-hardening punchlist. Fully completed; remaining cutover follow-ups live in AUDIT.md.

## Technical references
- **[TIMEZONE_STANDARDIZATION.md](./TIMEZONE_STANDARDIZATION.md)** — timezone handling: `dateUtils.js`, frontend/backend flow, edge cases.
- **[GEO_QUERY_DEBUGGING_GUIDE.md](./GEO_QUERY_DEBUGGING_GUIDE.md)** & **[TESTING_GEO_FIXES.md](./TESTING_GEO_FIXES.md)** — geo/location query debugging + verification.

---
_Consolidated 2026-05-31: `KNOWN_ISSUES.md` removed (its open `hiddenLocation` issue folded into AUDIT.md); root `server-audit.md` + `test-suite-findings.md` moved here; root `README.md` de-boilerplated._
