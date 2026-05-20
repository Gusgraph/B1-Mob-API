# App structure

## Main paths
- app/
- bootstrap/
- config/
- database/
- public/
- project-tracking/
- resources/
- routes/
- storage/
- tests/

## Runtime repo
- Python runtime repo: `/home/gusgraphy/Bismel1-ex-py`
- Notes: `/home/gusgraphy/Bismel1-ex-py/project-notes`
- Runtime invariants: `/home/gusgraphy/Bismel1-ex-py/project-notes/PRODUCTION_INVARIANTS.md`
- Main runtime paths:
  - `app/runtime/prime_stocks_dry_run.py`
  - `app/runtime/execution/`
  - `app/services/firestore_runtime_store.py`
  - `app/services/alpaca_account_resolver.py`
  - `app/main.py`
  - `scripts/deploy_cloud_run.sh`

## Mobile API
- API route file: `routes/api.php`
- Mobile v1 prefix: `/api/mobile/v1`
- Mobile auth: hashed Laravel bearer tokens in `mobile_access_tokens`
- Mobile audit table: `mobile_audit_logs`
- Mobile support table: `mobile_support_tickets`
- Mobile controllers:
  - `app/Http/Controllers/Api/Mobile/MobileAuthController.php`
  - `app/Http/Controllers/Api/Mobile/MobileCustomerController.php`
- Mobile middleware:
  - `app/Http/Middleware/MobileApiAuthenticate.php`
- Mobile response envelope:
  - `app/Support/Mobile/MobileApiResponse.php`

## Views
- resources/views/home.blade.php
- resources/views/layouts/app.blade.php
- resources/views/layouts/guest.blade.php
- resources/views/partials/ui/
- resources/views/customer/automation/
- resources/views/customer/automation/partials/

## Public assets
- public/
- images/
- logo path currently used: images/logo.png

## Notes
- guest layout exists in live domain folder
- app layout also exists
- home page currently depends on live domain structure, not old removed repo path
- shared customer automation chart popup currently lives in `resources/views/customer/automation/index.blade.php`
- Execution workspace is rendered from `resources/views/customer/automation/partials/execution-workspace.blade.php`
- customer runtime/account invariants are tracked in `project-tracking/PRODUCTION_INVARIANTS.md`
- high-signal regression coverage currently centers on:
  - `tests/Feature/Customer/CustomerAutomationRuntimeTest.php`
  - `tests/Feature/Customer/CustomerTradingPagesTest.php`
  - `tests/Feature/Customer/Bismel1EntitlementEnforcementTest.php`
  - `tests/Feature/Customer/CustomerSecretFlowsTest.php`
  - `tests/Feature/Broker/AlpacaAccountSyncServiceTest.php`
