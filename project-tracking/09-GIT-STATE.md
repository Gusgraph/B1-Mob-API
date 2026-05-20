# Git state

## Mobile API foundation - 2026-05-20
- Laravel branch: `main`
- Current task scope:
  - mobile API routes under `/api/mobile/v1`.
  - mobile hashed bearer-token auth.
  - mobile response envelope.
  - mobile customer API controller.
  - mobile audit/support/token tables and models.
  - feature coverage under `tests/Feature/Api/Mobile`.
  - Terms/Privacy visible UI copy update from `Gusgraph LLC` to `Gusgraph`.
- Intended commit message: `api: add mobile customer foundation`
- No Python runtime, Cloud Run, strategy logic, broker execution logic, raw broker secrets, runtime tokens, or admin API exposure changed.
- Validation note: focused mobile suite and auth access-control suite pass after creating an ignored local `.env` for validation only.

## Repo
- Path: /var/www/html/bismel1.com
- Remote: git@github.com:Gusgraph/Bismel1.com.git
- Branch: main

## Verified
- SSH auth works
- Push works
- Test push succeeded with commit 30882b7

## Current working state - 2026-04-21
- Laravel repo `/var/www/html/bismel1.com` is dirty with many modified tracked files plus new support/view files.
- Python runtime repo `/home/gusgraphy/Bismel1-ex-py` is also dirty with modified runtime/deploy/tests plus new `app/runtime/execution/` files.
- This means the current branch state is not ready for a blind push.
- Next safe step:
  - review/stage the intended release scope
  - commit deliberately
  - then push/rebuild

## Runtime release notes - 2026-04-23
- Prime production logic is currently intended to preserve:
  - no preview overwrite of submitted execution
  - Prime-only allocation budgets
  - no generic Execution guardrail leakage
- Latest known live Prime runtime revision from the production-fix window:
  - `bismel1-prime-stocks-00052-jzn`
- The remaining release risk is not primarily missing logic; it is accidental scope bleed from dirty worktrees during future deploys.

## Never commit
- .env
- secrets
- private keys
- runtime logs
