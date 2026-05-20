# Current status

## Session Update - 2026-05-20 - Mobile API foundation added for customer app v1

Completed in this session:
- added the first Laravel mobile API surface under `/api/mobile/v1`.
- chose a Laravel-native hashed bearer-token auth layer because Sanctum is not installed in the current app.
- kept mobile auth separate from web session cookies:
  - login issues a revocable mobile bearer token.
  - logout revokes the current token.
  - refresh rotates the current token.
  - token storage is documented for mobile secure storage only.
- added customer-only mobile endpoints for auth, plans, app config, me, dashboard, products, accounts, broker account status/connect/disconnect, automation, positions, orders, activity, performance, billing summary, support tickets, and affiliate reads.
- added a required mobile response envelope: success `ok/data/meta`, error `ok/error.code/error.message/error.details`.
- added mobile audit logging for broker connect/disconnect, automation symbol add/remove, automation toggle, manual close request, and support ticket create/reply.
- added mobile support ticket storage for the v1 API foundation.
- updated visible Terms/Privacy UI copy from `Gusgraph LLC` to `Gusgraph`.

Current validation result:
- focused mobile API feature suite passes.
- route list shows 39 `/api/mobile/v1` routes.
- an ignored local `.env` was created from `.env.example` for validation only; no secrets are committed.

## Session Update - 2026-04-23 - production behavior hardened and generalized beyond the original test accounts

Completed in this session:
- audited the current Prime + Execution fixes for hidden coupling to one specific user, account, slot, broker account, or credential
- confirmed the main remaining fragility is release hygiene and source-of-truth drift risk, not obvious hardcoded runtime logic
- locked down production invariants in both repos so future work has an explicit contract for:
  - slot/account source of truth
  - Prime vs Execution separation
  - preview vs submitted execution precedence
  - broker reconnect/history behavior
  - symbol removal persistence
- fixed one real generic persistence gap in the Laravel Automation controller:
  - Execution symbol removal now clears stale `symbol_assignments` along with `selected_symbols` and `symbol_states`
  - this prevents removed Execution symbols from lingering in config payloads and later behaving differently per user/account state
- added focused regression coverage for symbol removal persistence so both products now prove empty/removed state stays removed

Validated in this hardening pass:
- Laravel:
  - `tests/Feature/Customer/CustomerAutomationRuntimeTest.php`
- previously locked Python production invariants remain covered in:
  - `tests/test_prime_stocks_dry_run.py`
  - `tests/test_scheduler_invocation.py`
  - `tests/test_firestore_runtime_store.py`
  - `tests/test_execution_runtime_base.py`

Current production state:
- Prime runtime is live with:
  - preview overwrite fix
  - Prime-only allocation rules
  - ranked lower-candidate holdback once Prime entry budget is full
- Execution runtime is live with:
  - stock + ETF support
  - same-symbol rebuy protection
  - symbol-level runtime liveness writes
- the current app behavior is now documented as product-level invariants instead of depending on recent session memory
- the main remaining operational risk is still:
  - broad dirty worktrees
  - release/deploy hygiene
  - live credential/data quality issues such as broker-side `401` market-data failures

## Session Update - 2026-04-21 - Prime and Execution automation/chart/runtime surfaces were unified and Execution is now live end to end

Completed in this active build window:
- expanded the Automation surface beyond the older Prime-only focus and brought Execution into the same customer-facing module with its own symbol-first workflow
- added true Execution symbol-first runtime configuration and UI support:
  - per-symbol strategy selection
  - per-symbol settings
  - per-symbol protections
  - compact live Execution status lines
- added six new Execution strategies on top of the existing five so Execution now supports:
  - `ema`
  - `pullback`
  - `breakout`
  - `rsi_reversion`
  - `momentum`
  - `vwap`
  - `bollinger_reversion`
  - `adx_trend`
  - `donchian_breakout`
  - `relative_strength`
  - `opening_range_breakout`
- added real Execution protection controls and global guardrails with runtime enforcement:
  - enable trading
  - auto-disable protection
  - risk caps
  - order management
  - global guardrails for kill switch / daily loss / trade count / open positions / per-run entry limits
- fixed the Execution scheduler path end to end:
  - Cloud Run route now serves the real scheduled endpoint
  - target discovery now finds live Execution accounts/lanes through the Laravel bridge
  - slot/assignment processing now leaves symbol-level runtime health state so the UI can prove the scheduler is live
- hardened Prime runtime continuity without changing product behavior:
  - canonical Prime runtime path is now slot-scoped under `users/{uid}/accounts/{account_id}/prime_stocks/current/slots/slot_{n}/...`
  - old account-scoped Prime runtime docs are promoted into the canonical slot path only as a bridge/migration aid
- fixed shared chart preview behavior for both products:
  - popup works in Prime and Execution
  - shared chart payload now returns candles and runtime overlay data
  - shared chart lookback mapping now requests `227` bars for:
    - `1H -> 5Min`
    - `4H -> 15Min`
    - `1D -> 1Hour`
  - chart opens focused on newest candles by default
  - mobile chart popup layout is now chart-first with collapsible details
- expanded the Execution product UI substantially:
  - ETF search/add support through the existing US-equity execution path
  - market session status in symbol rows
  - responsive dual-layout symbol lists
  - production copy cleanup removing internal customer-facing `Slot` wording from the Execution page
- cleaned multiple customer dashboard/account surfaces:
  - shared connected-account selector uses account alias headings again
  - dashboard placeholder sections removed
  - execution dashboard wording tightened

Validated in focused checks during this window:
- Laravel:
  - `tests/Feature/Customer/CustomerAutomationRuntimeTest.php`
  - `tests/Feature/Customer/CustomerTradingPagesTest.php`
  - `tests/Feature/Runtime/PrimeStocksRuntimeAccountContextControllerTest.php`
- Python:
  - `tests/test_execution_runtime_base.py`
  - `tests/test_scheduler_invocation.py`
  - `tests/test_prime_stocks_dry_run.py`
  - `tests/test_firestore_runtime_store.py`

Current state:
- Prime Stocks and Execution now share one customer Automation shell, but remain separate products with separate runtime logic
- Execution scheduler discovery is live and processes the selected Execution account/lane instead of returning `runnable_slots=0`
- Prime chart popup and Execution chart popup now use the same shared candle/overlay/mobile path
- shared chart preview still needs continued browser QA on real mobile devices, but the current backend/frontend path is functionally in place
- both repos are currently dirty with many uncommitted changes; tracking files must reflect that before any push/deploy step

## Session Update - 2026-04-12 - Prime Stocks symbol system moved onto real instrument master + runtime symbol flow hardened end to end

Completed in this session:
- moved the Automation symbol picker off the tiny preset/test source and onto a real internal instrument master dataset
- kept the current customer Automation page locked to Prime Stocks stock scope while making the instrument schema future-ready for other asset classes later
- added admin-triggered instrument master sync/bootstrap on `Admin > System`
- validated live Alpaca-backed instrument master sync and populated the internal instrument dataset with real symbols
- upgraded the Automation symbol picker into a validated production-safe selector:
  - search by ticker
  - search by company name
  - autocomplete result list
  - no arbitrary free text save
  - duplicate rejection
  - unsupported symbol rejection
- hardened the real symbol flow so Automation symbol state is no longer UI-only:
  - Laravel saves `selected_symbols`
  - Laravel saves `symbol_states`
  - Laravel now also persists the primary runtime `symbol` from the same managed account symbol list
- blocked non-entitled accounts from activating symbol add/search flow for Prime Stocks automation
- fixed the runtime path so Python uses the same saved account-scoped symbol list for real execution dispatch:
  - active symbols are eligible
  - `paused` / `standby` symbols are excluded
  - removed symbols stop being dispatched
  - empty active set now cleanly returns `no_active_symbols_configured`
- extended scheduler fan-out so one account can dispatch across multiple active saved symbols instead of silently centering on one stale runtime symbol
- improved Automation symbol table behavior:
  - symbol click opens a chart modal on the same page
  - remove action now confirms and warns that positions stay open if the symbol is removed
  - rows show live or last-known market price/change when available, with visible fallback messaging when not
- fixed the backend market-data path that was breaking both the symbol table and modal:
  - Alpaca market data now uses the Alpaca data host
  - historical bars now include correct time windows
  - live provider-backed data now flows through the backend only
- upgraded the Automation chart modal from minimal line preview to a real candlestick preview:
  - OHLC backend response
  - candlestick rendering
  - right-side price scale
  - current price line
  - OHLC legend strip
  - range switching for `4H`, `1D`, `1M`

Validated with:
- `cd /var/www/html/bismel1.com && php artisan test tests/Feature/Customer/CustomerAutomationRuntimeTest.php -q`
- `cd /home/gusgraphy/Bismel1-ex-py && venv/bin/pytest tests/test_prime_stocks_dry_run.py tests/test_scheduler_invocation.py -q`

Current state:
- customer-managed Automation symbols are now wired into the real Prime Stocks runtime path instead of only being stored for UI use
- account-scoped symbol lists are respected by scheduler/runtime dispatch
- non-entitled accounts are blocked before symbol activation flow
- Automation symbol search now depends on the real internal instrument master dataset, not the old tiny preset list
- Automation market rows and chart modal now use live provider-backed data through backend service paths
- chart modal is materially improved, but it still uses TradingView `lightweight-charts`, not the full TradingView charting product
- latest symbol/runtime/chart changes are patched and validated locally; live deploy/browser verification is still the next operational step

## Session Update - 2026-04-10 - Prime Stocks hourly scheduler fan-out fixed and Automation page rebuilt into customer-first control flow

Completed in this session:
- fixed the remaining production scheduler gap by changing the main Prime Stocks scheduled path to fan out across entitled linked accounts with valid:
  - `uid`
  - `account_id`
  - `alpaca_account_id`
- confirmed scheduler-driven runs now write to account-scoped Firestore paths instead of the old broken global runtime context
- kept Firestore as the primary runtime/app state source and kept account-scoped storage intact:
  - `users/{uid}/accounts/{accountId}/prime_stocks/current/*`
- rebuilt the customer Automation page into a customer control surface instead of a runtime/debug wall
- removed the old numbered account button row and replaced it with the cleaner shared account selector
- reorganized the Automation page around customer workflow:
  - account selector
  - AI status
  - symbol management
  - automation activity report
- moved Prime Stocks symbol control directly onto the Automation page:
  - add symbol
  - remove symbol
  - active/pause symbol mode
- changed symbol-state persistence to use the scoped Firestore runtime config document for the selected account
- cleaned the customer wording and status model so the page reads as automation UX, not operator/debug UX
- added the latest customer-facing polish:
  - visible selector borders
  - entitlement name under the Automation header
  - AI customer wording:
    - blocked => `Looking for trade condition`
    - waiting => `IN PROGRESS`
  - AI pulse indicator with gray/off state when automation is off
  - stronger visual treatment for Prime Stocks / automation action controls
  - customer-facing symbol readiness labels:
    - `Ready for trigger`
    - `Looking for trade condition`
    - `Watching for setup`
    - `Paused`
- finalized the Prime Stocks exposure rules in runtime and surfaced them in the Automation page:
  - per symbol entry: `3%` of equity
  - all symbols traded cap for new positions: `20%` of equity
  - adds only cap: `70%` of equity

Validated with:
- `cd /home/gusgraphy/Bismel1-ex-py && venv/bin/pytest tests/test_scheduler_invocation.py tests/test_prime_stocks_dry_run.py -q`
- `cd /var/www/html/bismel1.com && php artisan test tests/Feature/Runtime/PrimeStocksRuntimeAccountContextControllerTest.php`
- `cd /var/www/html/bismel1.com && php artisan test tests/Feature/Customer/CustomerAutomationRuntimeTest.php`

Current state:
- the hourly Prime Stocks scheduler no longer depends on missing global runtime context and now dispatches valid account-scoped runs
- scheduler-driven runtime writes are isolated per user/account and no longer overwrite each other
- the customer Automation page now acts as a customer-facing Prime Stocks control page instead of a monitoring wall
- symbol management for Prime Stocks is now available directly on the Automation page for the selected account
- the latest UI/readiness changes are validated in focused Laravel tests
- the most recent exposure-rule/runtime patch is validated in focused Python tests and reflected in the customer Automation page copy
- the latest UI and exposure-rule changes still need live deploy/browser pass to confirm on the production shell

## Session Update - 2026-04-09 - Prime Stocks runtime monitoring surface added to Automation using live Firestore docs

Completed in this session:
- kept the phase read-only against the existing Prime Stocks Firestore runtime documents
- did not change Python strategy logic or runtime architecture
- extended the Laravel Firestore runtime read bundle to include:
  - `runtime_products/prime_stocks/heartbeat/current`
- expanded the existing Automation runtime view-data path to surface:
  - last run time
  - run id
  - trigger source
  - runtime status
  - candidate action
  - execution decision
  - blocked / skipped reason
  - latest signal time
  - last processed bar time
  - symbol
  - broker environment
  - account id / alpaca account id
  - position state
  - dollars used
  - add count / add tiers
  - trail stop
  - last action
  - `Ai_*` state
  - heartbeat status / last ping
  - order status / client order id / order id
- added a new Prime Stocks monitoring section to the existing customer Automation page using the current Bismel1 card/stat UI language
- kept the monitoring surface real-data only and inside the current customer Automation module

Validated with:
- `php -l app/Support/Firestore/FirestoreBridge.php`
- `php -l app/Support/ViewData/AutomationPageData.php`
- `php -l app/Http/Controllers/Customer/AutomationController.php`
- `php artisan test tests/Feature/Customer/CustomerAutomationRuntimeTest.php`

Current state:
- the customer Automation page now has a production-usable monitoring surface for Prime Stocks runtime health and current state
- the monitoring panel reads existing Firestore runtime docs only
- heartbeat is now included alongside state / signal / execution / action / snapshot visibility
- the live runtime operator view is available without creating a new product path or changing execution behavior

## Session Update - 2026-04-08 - Prime Stocks AI runtime validation coverage expanded; live Firestore proof still blocked by VM access

Completed in this session:
- kept the phase inside the Python runtime repo only and did not touch Laravel product logic
- validated the existing Prime Stocks AI runtime behavior through the current execution path in `tests/test_prime_stocks_dry_run.py`
- added focused runtime coverage for the remaining required AI-filter scenarios:
  - allow path with explicit `risk_on / bullish / safe` cache state
  - bearish block path for new basket entries
  - unsafe block path for buy-side entries
  - risk_off block path for adds
  - exit-allowed behavior while market AI is `risk_off`
  - exit-allowed behavior while symbol AI is `unsafe`
  - explicit `ai_cache_unavailable` blocking
  - explicit `ai_cache_stale` blocking
- verified Firestore-shaped runtime payload assertions in the test harness for:
  - `Ai_regime_label`
  - `Ai_sentiment_label`
  - `Ai_safety_label`
  - `Ai_execution_allowed`
  - `Ai_blocked_reason`
  - `execution_allowed`
  - `execution_decision`
  - `candidate_action`
- re-confirmed the hot runtime path still consumes cached AI only:
  - no direct Gemini call inside runtime execution
  - existing cached-AI-only test still passes

Validated with:
- `cd /home/gusgraphy/Bismel1-ex-py && venv/bin/pytest tests/test_prime_stocks_dry_run.py`

Current state:
- Python-side Prime Stocks AI runtime behavior is now covered for the required allow/block scenarios in the runtime execution test path
- exits are confirmed by test coverage to remain allowed under `risk_off` and `unsafe` AI cache states
- explicit AI blocked reasons are covered separately from market-data blocked reasons in the runtime payload assertions
- live Firestore/runtime proof from this VM is still blocked by environment access:
  - ADC is present on the VM
  - Firestore access to project `bismel1-1` returns `403 PermissionDenied`
  - repo defaults currently point to `servgraph`, where Firestore database `(default)` does not exist
- next operational requirement is external access/state, not runtime-code redesign:
  - provide valid Firestore project/database access from this VM
  - then run the same validation against live runtime documents and scheduled/manual execution

## Session Update - 2026-04-08 - Prime Stocks shared Gemini AI cache added outside hot path and wired into runtime safety/regime filtering

Completed in this session:
- kept the phase fully inside the Python runtime repo and did not touch Laravel product logic
- added a shared Gemini scoring service in the Python runtime path only:
  - reusable normalized `Ai_*` scoring logic
  - small prompt / headline-oriented classification
  - no direct Gemini call added to the Prime Stocks hot execution path
- added shared AI cache storage inside the existing Firestore runtime root:
  - `ai_market/current`
  - `ai_symbols/{SYMBOL}`
- added shared AI cache fields:
  - `Ai_regime_label`
  - `Ai_sentiment_label`
  - `Ai_safety_label`
  - `Ai_confidence`
  - `Ai_reason`
  - `Ai_updated_at`
  - `Ai_source`
  - `Ai_execution_allowed`
  - `Ai_block_new_entries`
  - `Ai_block_adds`
  - `Ai_blocked_reason`
- added a non-hot-path scoring script for manual/scheduled cache refresh:
  - `scripts/score_prime_stocks_ai.py`
- refactored the existing Gemini test script to use the shared provider/service instead of duplicate inline request code
- wired Prime Stocks runtime to consume cached AI records only:
  - market-wide AI cache record
  - symbol-level AI cache record
  - deterministic freshness/unavailable handling
- added built-in Prime Stocks AI blocking rules:
  - `Ai_safety_label=unsafe` blocks new entries and adds
  - `Ai_regime_label=risk_off` blocks new entries and adds
  - `Ai_sentiment_label=bearish` blocks new basket entries
  - exits remain unblocked by AI
- extended runtime payloads so AI data now flows through:
  - snapshot
  - signal
  - state
  - execution
  - action
  - log
- kept architecture intact:
  - no user/account toggle added
  - no new product added
  - no Gemini call placed inside the hot execution path
  - no Laravel runtime redesign introduced

Validated with:
- `python3 -m py_compile` on touched runtime/services/scripts/tests
- `pytest tests/test_gemini_ai_scoring.py tests/test_prime_stocks_dry_run.py tests/test_scheduler_invocation.py tests/test_firestore_runtime_store.py tests/test_firestore_runtime_bootstrap.py tests/test_alpaca_market_data.py tests/test_alpaca_account_resolver.py tests/test_bismel1_strategy_runner.py tests/test_pine_parity_map_smoke.py`

Current state:
- Prime Stocks now has a built-in cached AI safety/regime filter path
- Gemini scoring is isolated into a reusable provider service and manual/schedulable scoring script
- cached AI records are now part of Prime Stocks runtime output documents
- remaining operational step is external billing/state:
  - restore Gemini credits / billing
  - run real market-wide + symbol scoring writes
  - deploy the updated Python runtime revision and validate cached AI values in live runtime docs

## Session Update - 2026-04-07 - Product-aware Alpaca market data ingestion layer added for customer runtime and UI reads

Completed in this session:
- re-read the required tracking files before changes
- confirmed `05-ACTIVE-TASKS.md` was still pointing at the earlier broker verification follow-up while this task window explicitly assigned:
  - `MARKET DATA INGESTION (PRODUCT-AWARE, ALPACA FEED)`
- inspected the existing Laravel broker/runtime path in:
  - `app/Support/Broker/AlpacaClient.php`
  - `app/Support/Broker/AlpacaMarketDataService.php`
  - `app/Support/Broker/CustomerBrokerAccountSlotService.php`
  - `app/Http/Controllers/Customer/DashboardController.php`
  - `app/Http/Controllers/Customer/BrokerController.php`
  - `app/Http/Controllers/Customer/AutomationController.php`
  - `app/Http/Controllers/Customer/TradingPagesController.php`
  - `app/Support/ViewData/BrokerPageData.php`
  - `app/Support/ViewData/AutomationPageData.php`
  - `app/Support/ViewData/Bismel1CustomerTradingPageData.php`
  - `app/Support/Dashboard/CustomerStats.php`
  - `app/Support/Dashboard/CustomerDashboardSections.php`
- added centralized product scope configuration in `config/bismel1-products.php`
- added strict product scope enforcement:
  - new `AssetScope` enum
  - new `ProductAssetScopeResolver`
  - `filterSymbolsForProduct($product, array $symbols): array`
  - Prime Stocks / Overnight Equities filter to stocks only
  - Crypto product filters to crypto pairs only
  - Options scope is isolated for future-ready handling
  - Mixed products allow stocks + crypto
- kept the existing warmup bars path intact while enforcing product filtering before the old bars request path in `AlpacaMarketDataService`
- added the new market-data service layer:
  - `AlpacaMarketDataClient`
  - `MarketDataIngestionService`
  - `MarketDataNormalizer`
  - `MarketDataCacheRepository`
- added new short-term persistence for normalized latest snapshots:
  - `market_data_snapshots` table
  - `App\Models\MarketDataSnapshot`
- implemented normalized quote/bar shape with:
  - `symbol`
  - `asset_class`
  - `timeframe`
  - `timestamp`
  - `open`
  - `high`
  - `low`
  - `close`
  - `volume`
  - `bid`
  - `ask`
  - `last_price`
  - `change`
  - `percent_change`
  - `market_status`
  - `fetched_at`
- implemented supported timeframe handling for:
  - `1Min`
  - `5Min`
  - `15Min`
  - `1Hour`
  - `4Hour`
  - `1Day`
- added latest quote + latest bar + historical bar handling
- added stale detection and cached snapshot reuse
- added structured unavailability handling for:
  - `market_data_unavailable`
  - `stale_data`
  - `no_new_bar`
- updated customer pages so they can read and render cached market snapshots without creating a parallel UI path:
  - dashboard
  - broker
  - automation
  - orders
  - positions
- kept current architecture intact:
  - no Firestore redesign
  - no billing redesign
  - no shared model rename
  - no alternate module path created

Validated with:
- `php -l app/Support/Broker/ProductAssetScopeResolver.php`
- `php -l app/Support/Broker/MarketDataNormalizer.php`
- `php -l app/Support/Broker/MarketDataCacheRepository.php`
- `php -l app/Support/Broker/AlpacaMarketDataClient.php`
- `php -l app/Support/Broker/MarketDataIngestionService.php`
- `php -l app/Support/Broker/AlpacaClient.php`
- `php -l app/Support/Broker/AlpacaMarketDataService.php`
- `php -l app/Http/Controllers/Customer/DashboardController.php`
- `php -l app/Http/Controllers/Customer/BrokerController.php`
- `php -l app/Http/Controllers/Customer/AutomationController.php`
- `php -l app/Http/Controllers/Customer/TradingPagesController.php`
- `php -l app/Support/ViewData/BrokerPageData.php`
- `php -l app/Support/ViewData/AutomationPageData.php`
- `php -l app/Support/ViewData/Bismel1CustomerTradingPageData.php`
- `php -l app/Support/Dashboard/CustomerStats.php`
- `php -l app/Support/Dashboard/CustomerDashboardSections.php`
- `php -l app/Models/MarketDataSnapshot.php`
- `php -l database/migrations/2026_04_07_190000_create_market_data_snapshots_table.php`
- `php artisan test tests/Feature/Broker/MarketDataIngestionServiceTest.php`
- `php artisan test tests/Feature/Customer/CustomerTradingPagesTest.php --filter='cached_market_snapshot|positions_page_renders_account_scoped_position_visibility|orders_page_renders_safe_recent_order_visibility'`
- `php artisan test tests/Feature/Customer/CustomerAutomationRuntimeTest.php --filter='renders_real_runtime_state_and_safe_visibility'`
- `php artisan test tests/Feature/Customer/CustomerDashboardReportsTest.php --filter='dashboard_renders_real_db_backed_summary_reads'`

Current state:
- product-based asset filtering now exists as code, not convention
- latest quote / latest bar / historical bar ingestion now has a dedicated service path
- normalized latest snapshots now persist in relational cache storage for fast UI/runtime reuse
- dashboard, broker, automation, orders, and positions can now consume the cached market-data layer
- next operational step is running the new migration on the app database and visually verifying the customer pages against a real linked slot after cache refresh

## Session Update - 2026-04-07 - Manual Alpaca paper/live validation and customer multi-account slot flow wired into broker pages

Completed in this session:
- re-read the required tracking files before changes
- re-inspected the locked Laravel customer broker/runtime path in:
  - `routes/customer.php`
  - `app/Http/Controllers/Customer/BrokerController.php`
  - `app/Http/Controllers/Customer/TradingPagesController.php`
  - `app/Http/Controllers/Customer/AutomationController.php`
  - `app/Support/Broker/AlpacaAccountSyncService.php`
  - `app/Support/Broker/AlpacaClient.php`
  - `app/Support/Billing/Bismel1EntitlementService.php`
  - `app/Support/ViewData/BrokerPageData.php`
  - `resources/views/customer/broker/index.blade.php`
  - `resources/views/customer/broker/create.blade.php`
  - `resources/views/customer/trading/index.blade.php`
  - `resources/views/customer/automation/index.blade.php`
  - `tests/Feature/Customer/CustomerSecretFlowsTest.php`
  - `tests/Feature/Customer/Bismel1EntitlementEnforcementTest.php`
- added explicit broker slot state on `broker_connections`:
  - `slot_number`
  - `validation_status`
  - `last_validated_at`
  - `automation_enabled`
  - `automation_enabled_at`
  - `last_error`
- added `CustomerBrokerAccountSlotService` to provide:
  - default `Account 1` and `Account 2`
  - visible locked slots beyond the default allowance
  - session-backed selected-slot persistence
  - legacy fallback mapping for older connections that do not yet have `slot_number`
  - selected-slot connection, credential, and Alpaca-account resolution
- changed the linked-account entitlement rule from the old paper/live split to the new product rule:
  - 2 default included account slots
  - Account 3+ require the additional-account add-on
- kept manual API credential storage intact while making it slot-aware:
  - broker save now targets a specific account slot
  - masked key/secret handling remains unchanged
  - live slots stay automation-disabled by default until explicit enable
- added broker slot refresh/validation route:
  - `POST /customer/broker/sync`
- updated customer broker page behavior:
  - selected account slot drives the rendered broker summary
  - broker page now shows a shared slot selector
  - broker page now offers slot-scoped actions:
    - add/edit API credentials
    - validate connection / refresh broker data
    - enable/disable automation for the selected slot
- updated customer broker create page behavior:
  - selected slot is visible before save
  - locked slot message is shown for Account 3+ without add-on
  - manual credential save writes to the selected slot
- updated customer orders, positions, and automation pages:
  - shared slot selector is rendered
  - selected slot persists through session/query
  - orders and positions filter to the selected Alpaca account where available
  - automation uses the selected slot’s broker/account context
- kept the current architecture intact:
  - no OAuth redesign
  - no Cloud Run move
  - no Firestore structure rewrite
  - no fake UI states introduced

Validated with:
- `php -l app/Support/Broker/CustomerBrokerAccountSlotService.php`
- `php -l app/Http/Controllers/Customer/BrokerController.php`
- `php -l app/Http/Controllers/Customer/TradingPagesController.php`
- `php -l app/Http/Controllers/Customer/AutomationController.php`
- `php -l app/Support/Billing/Bismel1EntitlementService.php`
- `php -l app/Support/ViewData/BrokerPageData.php`
- `php -l app/Support/Broker/AlpacaAccountSyncService.php`
- `php -l app/Models/BrokerConnection.php`
- `php -l app/Http/Requests/Customer/StoreBrokerCredentialRequest.php`
- `php -l app/Http/Requests/Customer/UpdateAutomationSettingsRequest.php`
- `php -l database/migrations/2026_04_07_110000_add_slot_state_to_broker_connections_table.php`
- `php -l tests/Feature/Customer/Bismel1EntitlementEnforcementTest.php`
- `php artisan test tests/Feature/Broker/AlpacaAccountSyncServiceTest.php`
- `php artisan test tests/Feature/Customer/CustomerTradingPagesTest.php`
- `php artisan test tests/Feature/Customer/CustomerSecretFlowsTest.php`
- `php artisan test tests/Feature/Customer/Bismel1EntitlementEnforcementTest.php`

Current state:
- manual Alpaca credentials can now be saved and validated per account slot
- `Account 1` and `Account 2` are available by default in the customer UI
- locked `Account 3+` slots are rendered and routed to billing instead of dead behavior
- broker, orders, positions, and automation now understand selected account context
- the remaining operational step is applying the new migration in the app database and rechecking the customer pages in-browser after cache/build refresh

## Session Update - 2026-04-07 - Broker page now offers both OAuth and manual Alpaca connection paths

Completed in this session:
- re-read the required tracking files before changes
- re-inspected the Broker connection path in:
  - `routes/customer.php`
  - `app/Http/Controllers/Customer/BrokerController.php`
  - `app/Support/ViewData/BrokerPageData.php`
  - `resources/views/customer/broker/index.blade.php`
  - `resources/views/customer/broker/create.blade.php`
  - `resources/views/partials/ui/link-list.blade.php`
  - `tests/Feature/Customer/CustomerAccessPagesTest.php`
  - `tests/Feature/Customer/CustomerSecretFlowsTest.php`
- confirmed the current regression:
  - the existing manual API-key route/page still existed at `GET /customer/broker/create`
  - the Broker page action area had been reduced to only:
    - OAuth connect
    - Create Alpaca Account
  - the manual API-key connect option had been removed from the Broker page action list even though the manual flow still worked
- restored the manual connection option in the existing Broker page action area only
- kept all three actions on the same existing Broker page:
  - `Connect Alpaca Account (OAuth)` -> `GET /customer/broker/alpaca/authorize`
  - `Connect with API Keys` -> `GET /customer/broker/create`
  - `Create Alpaca Account` -> `https://app.alpaca.markets/signup`
- kept the OAuth authorize path intact
- kept the manual credential save/sync path intact
- did not create any new pages

Validated with:
- `php -l app/Support/ViewData/BrokerPageData.php`
- `php -l tests/Feature/Customer/CustomerAccessPagesTest.php`
- `php -l tests/Feature/Customer/CustomerSecretFlowsTest.php`
- `php artisan test tests/Feature/Customer/CustomerAccessPagesTest.php --filter='customer_broker_page_survives_missing_firestore_credentials|customer_broker_authorize_page_renders_required_disclosure_copy'`
- `php artisan test tests/Feature/Customer/CustomerSecretFlowsTest.php --filter='customer_broker_store_persists_credentials_and_only_renders_masked_metadata|customer_broker_page_shows_current_account_connection_and_credential_detail_without_raw_secrets'`

Current state:
- the existing Broker page now clearly supports both connection methods without confusion
- the manual API-key path works through the existing `/customer/broker/create` page
- the OAuth path remains present on the same Broker page
- the Create Alpaca Account outbound link remains present

## Session Update - 2026-04-07 - Live Alpaca token exchange failure isolated to invalid_client

Completed in this session:
- re-read the required tracking files before changes
- re-checked the live token-exchange logs after the latest real browser callback attempt
- confirmed the exact live Alpaca rejection now surfaced by the backend is:
  - `invalid_client`
  - HTTP `401`
  - response body: `{"code":40110000,"message":"invalid_client"}`
- confirmed this is not a paper/live environment-selection bug:
  - authorize redirect still omits `env`
  - token request still posts to `https://api.alpaca.markets/oauth/token`
  - token request still uses `application/x-www-form-urlencoded`
- verified the current local config still resolves:
  - `client_id` present
  - `client_secret` present
  - `redirect_uri=https://bismel1.com/customer/broker/alpaca/callback`
  - `authorize_url=https://app.alpaca.markets/oauth/authorize`
  - `token_url=https://api.alpaca.markets/oauth/token`
  - `default_scope=account:write trading data`
- updated the token exchange failure path so the app now surfaces the exact `invalid_client` rejection with an actionable message instead of the generic `was rejected`
- expanded safe token-exchange logging so the live failure record now includes:
  - endpoint
  - grant type
  - code present
  - client id present
  - redirect URI
  - content type
  - Alpaca response `code`
  - Alpaca response `message`
- added focused regression coverage for:
  - live-style `invalid_client` rejection messaging
  - existing generic rejection path
  - successful OAuth persistence path
  - authorize redirect still omitting `env`

Validated with:
- `rg -n "alpaca_oauth_token_exchange_(rejected|failed|connection_failed)" storage/logs/laravel.log`
- `php artisan tinker --execute='...'` to confirm current live OAuth config values
- `php -l app/Support/Broker/AlpacaClient.php`
- `php -l tests/Feature/Customer/CustomerSecretFlowsTest.php`
- `php artisan test tests/Feature/Customer/CustomerSecretFlowsTest.php --filter='customer_broker_oauth_callback_handles_token_exchange_failure|customer_broker_oauth_callback_surfaces_invalid_client_rejection_truthfully|customer_broker_oauth_callback_persists_linked_accounts_on_success|customer_broker_authorize_redirect_starts_with_client_id_without_client_secret'`

Current state:
- the system now truthfully reports the actual live token-exchange blocker as `invalid_client`
- the remaining blocker is outside the Laravel callback logic:
  - the published Alpaca OAuth app registration and the locally configured client credentials do not currently match from Alpaca’s point of view
- until that client registration mismatch is corrected, the system cannot complete token exchange or fetch the user’s Alpaca account information

## Session Update - 2026-04-07 - Alpaca token exchange rejection path now preserves exact safe failure cause

Completed in this session:
- re-read the required tracking files before changes
- re-inspected the live Alpaca OAuth path in:
  - `config/alpaca.php`
  - `app/Http/Controllers/Customer/BrokerController.php`
  - `app/Support/Broker/AlpacaClient.php`
  - `app/Support/Broker/AlpacaAccountSyncService.php`
  - `resources/views/customer/broker/authorize.blade.php`
  - `tests/Feature/Customer/CustomerSecretFlowsTest.php`
- confirmed the current authorize redirect still omits `env`
  - no code path currently injects `env=paper`
  - no code path currently injects `env=live`
  - this keeps the Alpaca authorize flow available for both account environments in one pass where Alpaca supports it
- confirmed Laravel currently resolves the live OAuth config as:
  - `client_id` present
  - `client_secret` present
  - `redirect_uri` present
  - `authorize_url=https://app.alpaca.markets/oauth/authorize`
  - `token_url=https://api.alpaca.markets/oauth/token`
  - `default_scope=account:write trading data`
- confirmed the token exchange request shape in `AlpacaClient::exchangeAuthorizationCode()` is now explicitly:
  - endpoint `https://api.alpaca.markets/oauth/token`
  - `grant_type=authorization_code`
  - `code`
  - `client_id`
  - `client_secret`
  - `redirect_uri`
  - `Content-Type: application/x-www-form-urlencoded`
- fixed the main backend mismatch in the rejection path:
  - previous code collapsed all `400/401/403` token exchange responses into the generic message `Alpaca OAuth token exchange was rejected.`
  - that hid the real Alpaca rejection cause and made live debugging impossible
- updated `AlpacaClient` so token exchange failures now:
  - keep the safe request shape intact
  - preserve the safe Alpaca response details (`error`, `error_description`) in the returned message
  - write safe log context without exposing the raw client secret
- added focused coverage that now asserts:
  - authorize redirect omits `env`
  - token exchange POST goes to the exact Alpaca token URL
  - token exchange is form-encoded
  - token exchange includes the expected payload fields
  - failure messaging includes the exact safe rejection cause when Alpaca returns one

Validated with:
- `php artisan tinker --execute='...'` to confirm live OAuth config values
- `php -l app/Support/Broker/AlpacaClient.php`
- `php -l tests/Feature/Customer/CustomerSecretFlowsTest.php`
- `php artisan test tests/Feature/Customer/CustomerSecretFlowsTest.php --filter='customer_broker_authorize_redirect_starts_with_client_id_without_client_secret|customer_broker_oauth_callback_handles_token_exchange_failure|customer_broker_oauth_callback_persists_linked_accounts_on_success'`

Current state:
- authorize redirect remains environment-agnostic and keeps paper/live authorization in one flow
- token exchange path now preserves the exact safe rejection cause instead of hiding it behind a generic failure string
- linked account persistence path remains intact after successful token exchange
- exact next live step is re-running one real browser OAuth round-trip to capture whether the remaining Alpaca rejection is:
  - invalid authorization code
  - redirect URI mismatch
  - client credential mismatch
  - or another Alpaca-side rejection detail now surfaced safely

## Session Update - 2026-04-07 - Broker authorize page active-state now reflects loaded OAuth config

Completed in this session:
- re-read the required tracking files before changes
- re-inspected the broker authorize page path in:
  - `resources/views/customer/broker/authorize.blade.php`
  - `app/Http/Controllers/Customer/BrokerController.php`
  - `app/Support/ViewData/BrokerPageData.php`
- compared the current Blade/controller logic to the reported browser behavior
- confirmed the actual controller readiness values now resolve correctly from live config:
  - `client_id` present
  - `client_secret_configured` true
  - `redirect_uri` present
  - `authorize_url` present
  - `token_url` present
  - `default_scope` present
  - `authorize_ready` true
  - `callback_ready` true
- confirmed the current authorize Blade already renders the active POST form branch when `authorize_ready` is true
- identified the remaining mismatch as served stale state rather than broken authorize logic:
  - external shell fetch of the live route still landed on `/login` because the saved CLI cookie is no longer authenticated
  - the PHP/controller path already reported the correct ready state
  - stale compiled config/view output was the remaining likely source of the browser mismatch
- cleared Laravel config and compiled views:
  - `php artisan config:clear`
  - `php artisan view:clear`
- tightened the authorize page feature test so it now verifies the page does not render the disabled button branch when authorize config is present

Validated with:
- `php artisan tinker --execute='...'` using reflection to inspect `BrokerController::alpacaOauthConfig()`
- `php artisan config:clear`
- `php artisan view:clear`
- `php artisan test tests/Feature/Customer/CustomerAccessPagesTest.php --filter=customer_broker_authorize_page_renders_required_disclosure_copy`

Current state:
- the authorize page logic is correct and now backed by a test that asserts the enabled branch
- loaded Alpaca OAuth config is sufficient to start the authorize redirect without requiring any additional code change
- `Continue to Alpaca` should now render from the active form branch after the cache clear
- no new pages were created

## Session Update - 2026-04-06 - OAuth-linked broker credentials now usable in shared read path

Completed in this session:
- re-read the required tracking files before changes
- re-inspected the current Alpaca OAuth persistence/read path in:
  - `config/alpaca.php`
  - `app/Models/BrokerCredential.php`
  - `app/Models/BrokerConnection.php`
  - `app/Models/AlpacaAccount.php`
  - `app/Support/Broker/AlpacaClient.php`
  - `app/Support/Broker/AlpacaAccountSyncService.php`
  - `app/Http/Controllers/Customer/BrokerController.php`
  - `app/Support/ViewData/BrokerPageData.php`
  - `tests/Feature/Broker/AlpacaAccountSyncServiceTest.php`
  - `tests/Feature/Customer/CustomerSecretFlowsTest.php`
- confirmed the real mismatch after successful OAuth callback:
  - OAuth-linked credentials were already being persisted into `broker_credentials`
  - OAuth-linked `alpaca_accounts` rows were already being created
  - but the shared `AlpacaClient` read path still assumed only manual API-key headers and manual key/secret credential context
- updated `AlpacaClient` so the shared broker/account read path now supports both credential types:
  - manual API-key credentials continue using `APCA-API-KEY-ID` / `APCA-API-SECRET-KEY`
  - OAuth-linked credentials now use `Authorization: Bearer ...`
  - credential context resolution now treats OAuth credentials as first-class usable broker credentials instead of only masked metadata
- kept the manual API-key flow intact while making OAuth and manual credentials coexist cleanly
- added focused regression coverage for:
  - syncing account readiness from an OAuth-linked credential
  - manual and OAuth credential coexistence without breaking masked summaries
  - Broker page truthfulness after OAuth callback persistence
- corrected the broker-page OAuth feature assertion to match the actual rendered access-mode label (`Trade disabled`)

Validated with:
- `php -l app/Support/Broker/AlpacaClient.php`
- `php -l tests/Feature/Broker/AlpacaAccountSyncServiceTest.php`
- `php -l tests/Feature/Customer/CustomerSecretFlowsTest.php`
- `php artisan test tests/Feature/Broker/AlpacaAccountSyncServiceTest.php`
- `php artisan test tests/Feature/Customer/CustomerSecretFlowsTest.php --filter='customer_broker_oauth_callback_persists_linked_accounts_on_success|customer_broker_page_shows_current_account_connection_and_credential_detail_without_raw_secrets'`

Current state:
- successful Alpaca OAuth callback persistence now results in a usable broker credential record in the existing source of truth
- the system no longer expects raw user API key/secret values from OAuth in order to read/sync broker account state
- the Broker page shows truthful OAuth-linked account state with masked token metadata and without exposing raw tokens
- exact remaining live blocker:
  - local `ALPACA_OAUTH_CLIENT_SECRET` is still required for full live callback completion outside the mocked test path
  - authorize-start remains active, but live browser completion still depends on the local secret being present
- no Python/runtime change was required for this Laravel-side broker credential read-path fix

## Session Update - 2026-04-06 - Alpaca authorize redirect activation with published client ID

Completed in this session:
- re-read the required tracking files before changes
- re-inspected the current OAuth broker path in:
  - `config/alpaca.php`
  - `app/Http/Controllers/Customer/BrokerController.php`
  - `resources/views/customer/broker/authorize.blade.php`
  - `tests/Feature/Customer/CustomerAccessPagesTest.php`
  - `tests/Feature/Customer/CustomerSecretFlowsTest.php`
- confirmed the exact blocker for the authorize-start step:
  - the authorize page button was already rendered active
  - but `BrokerController::redirectToAlpaca()` still blocked the redirect unless `ALPACA_OAUTH_CLIENT_SECRET` was present
  - that was too strict for the current compliance-video requirement because the secret is only needed later for callback token exchange
- split the OAuth readiness logic so the authorize-start path now requires only:
  - `ALPACA_OAUTH_CLIENT_ID`
  - `ALPACA_OAUTH_AUTHORIZE_URL`
  - `ALPACA_OAUTH_REDIRECT_URI` or the existing callback-route fallback
- kept callback/token-exchange readiness separate:
  - missing `ALPACA_OAUTH_CLIENT_SECRET` no longer blocks the Continue button
  - callback still fails truthfully later if the secret is missing
- updated the authorize page to respect the new authorize-start gate instead of the old callback/token-exchange gate
- updated local `.env` only with the published client configuration:
  - `ALPACA_OAUTH_CLIENT_ID=c0b5324175691dc1a1372bd375c5fa9c`
  - `ALPACA_OAUTH_REDIRECT_URI=https://bismel1.com/customer/broker/alpaca/callback`
  - `ALPACA_OAUTH_AUTHORIZE_URL=https://app.alpaca.markets/oauth/authorize`
  - `ALPACA_OAUTH_DEFAULT_SCOPE="account:write trading data"`
- fixed the local `.env` parse issue caused by the unquoted scope value with spaces
- cleared Laravel config cache and confirmed the live local config now resolves:
  - published client ID present
  - authorize URL present
  - redirect URI present
  - scope present
  - client secret still absent
- added focused coverage for:
  - authorize redirect starting without a client secret
  - callback failing truthfully when the client secret is still missing

Validated with:
- `php artisan config:clear`
- `php -l app/Http/Controllers/Customer/BrokerController.php`
- `php -l resources/views/customer/broker/authorize.blade.php`
- `php -l tests/Feature/Customer/CustomerAccessPagesTest.php`
- `php -l tests/Feature/Customer/CustomerSecretFlowsTest.php`
- `php artisan test tests/Feature/Customer/CustomerAccessPagesTest.php --filter=customer_broker_authorize_page_renders_required_disclosure_copy`
- `php artisan test tests/Feature/Customer/CustomerSecretFlowsTest.php --filter='customer_broker_authorize_redirect_starts_with_client_id_without_client_secret|customer_broker_oauth_callback'`
- `php artisan tinker --execute="..."` to confirm the active local OAuth config values

Current state:
- the existing Broker page connect flow now supports a real authorize-start step with the published Alpaca client ID
- the authorize page remains clean and customer-facing, with:
  - required disclosure text
  - `Continue to Alpaca`
  - `Back to Broker`
- the callback route and backend token-exchange path remain intact for later use
- exact remaining live blocker:
  - `ALPACA_OAUTH_CLIENT_SECRET` is still not set locally
  - authorize redirect can start, but callback completion and final token exchange will still fail truthfully until the secret is available
- browser-equivalent live fetch with the saved CLI cookie currently lands on `/login`, so direct authenticated live page fetch from this shell remains blocked by session state rather than route/config code

## Session Update - 2026-04-06 - Broker page cleanup and styling

Completed in this session:
- Updated 'Alpaca Connection Account' to 'Broker Connection Account' in `app/Support/Settings/AppSections.php`.
- Consolidated 'Connect Alpaca Account', 'Create Alpaca Account', and 'Remove Linked Account' actions into a single 'Connection Actions' ui-card structure in `resources/views/customer/broker/index.blade.php`.
- Removed technical config details from `/resources/views/customer/broker/authorize.blade.php`
- Replaced developer-facing "OAuth Redirect Not Active Yet" message with a customer-friendly warning card (later removed by user request)
- Removed the entire warning card for "Alpaca connection is not ready yet"
- Made the "Continue to Alpaca" button always enabled
- Changed the page-shell summary title to 'Connect Alpaca Account' (later removed by user request)
- Changed the icon next to "Authorize Bismel1" to a lock icon (`fa-solid fa-lock`)
- Redesigned the "Continue to Alpaca" button to be greenish using inline styles
- Centered the "Authorize Bismel1" title within its card using inline style
- Centered the `ui-card` itself using `max-width` and `margin: 0 auto`
- Centered the header content (icon and "Authorize Bismel1" title) using `text-align: center` on `customer-section__heading`
- Centered the action buttons using `text-align: center;` on `ui-form-actions__buttons` and applied `display: inline-block; margin: 0 0.5rem;` to the individual form and anchor elements.
- Centered the container of the buttons (`div.ui-form-actions`) using `max-width: 600px; margin: 0 auto;` for desktop view.
- Cleaned up messages in `/resources/views/customer/broker/callback.blade.php`
- Removed the "Account summary" section from the `partials.ui.page-shell` include.

## Current state:
- `/customer/broker` page has consolidated connection actions.
- `/customer/broker/alpaca/authorize` page is visually cleaned and centered for screenshots/video.
- No placeholder/dev copy remains.
- "Continue to Alpaca" button is visually distinct and always enabled.
- "Back to Broker" button is clear.
- Required Alpaca disclosure text is intact.
- The `callback` page has customer-friendly messages.

## Session Update - 2026-04-07 - Automation page cleaned and production-ready

Completed in this session:
- re-read the required tracking files before changes
- inspected `resources/views/customer/automation/index.blade.php`, `app/Http/Controllers/Customer/AutomationController.php`, and `app/Support/ViewData/AutomationPageData.php`
- removed all placeholder content, dev notes, fake states, and non-functional UI elements from the Automation page
- cleaned and rephrased all customer-facing text to be production-ready, removing technical jargon, internal process notes, and development-specific references
- removed the religious invocation `﷽` from the blade template
- ensured all remaining content reflects only real, connected system behavior

Validated with:
- Code review of modified files.

Current state:
- the Automation page now presents a clean, customer-friendly interface
- all content is production-ready, free of technical jargon, internal notes, or placeholders
- functional elements remain intact, and non-functional elements were removed or adjusted as specified

## Next
- Validate the live browser flow with a real authenticated session:
  - Broker page `Connect Alpaca Account` -> `/customer/broker/alpaca/authorize`
  - authorize page loads cleanly
  - `Continue to Alpaca` starts the real redirect to Alpaca authorize
- If `ALPACA_OAUTH_CLIENT_SECRET` becomes available locally, complete the callback/token-exchange/browser validation end-to-end.
- Re-run the live browser callback completion with the local secret configured and confirm the persisted OAuth-linked account remains usable on the Broker page and for downstream runtime selection.
- Re-run one real Alpaca OAuth callback now that the backend surfaces the exact safe token exchange rejection cause, then fix only the specific remaining exchange mismatch if Alpaca still rejects it.
- Correct the Alpaca app registration or local client secret so the live token exchange stops returning `invalid_client`, then re-run the callback and confirm account persistence completes.
