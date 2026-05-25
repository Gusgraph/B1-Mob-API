<?php
// أَعُوذُ بِٱللَّهِ مِنْ الْشَيْطَانٍ الْرَجِيمٍ ✧ بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ ✧ اعوز بالله من الشياطين و ان يحضرون ✧ بسم الله الرحمن الرحيم ✧ الله لا إله إلا هو الحي القيوم
// Bismillahi ar-Rahmani ar-Rahim Audhu billahi min ash-shayatin wa an yahdurun Bismillah ar-Rahman ar-Rahim Allah la ilaha illa huwa al-hayy al-qayyum. Tamsa Allahu ala ayunihim
// version: x
// ======================================================
// - App Name: bismel1.com
// - Gusgraph -
// - Author: Gus Kazem
// - https://Gusgraph.com
// - File Path: app/Http/Controllers/Api/Mobile/MobileCustomerController.php
// =====================================================

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Broker\Enums\BrokerConnectionStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AlpacaOrder;
use App\Models\AlpacaPosition;
use App\Models\AutomationSetting;
use App\Models\BrokerConnection;
use App\Models\BrokerCredential;
use App\Models\InstrumentMaster;
use App\Models\MobileAuditLog;
use App\Models\MobileSupportTicket;
use App\Models\ReferralAttribution;
use App\Models\SubscriptionPlan;
use App\Support\Billing\Bismel1EntitlementService;
use App\Support\Broker\CustomerBrokerAccountSlotService;
use App\Support\Broker\ProductAssetScopeResolver;
use App\Support\Customer\CurrentCustomerAccountResolver;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MobileCustomerController extends Controller
{
    public function appConfig(): JsonResponse
    {
        return MobileApiResponse::success([
            'app' => [
                'name' => 'Bismel1',
                'api_version' => 'mobile-v1',
                'auth' => 'bearer_token',
                'token_storage' => 'device_secure_storage',
            ],
            'public_screens' => ['front_page', 'login', 'plans'],
            'private_screens' => [
                'dashboard',
                'products',
                'prime',
                'execution',
                'broker_accounts',
                'automation_symbols',
                'positions',
                'orders',
                'activity',
                'performance',
                'billing',
                'support_tickets',
                'profile_settings',
                'affiliate',
            ],
            'blocked_mobile_surface' => [
                'admin_features',
                'broker_secrets',
                'runtime_internal_tokens',
                'direct_alpaca_calls',
                'direct_cloud_run_calls',
            ],
        ]);
    }

    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->limit(19)
            ->get()
            ->map(fn (SubscriptionPlan $plan): array => [
                'code' => $plan->code,
                'name' => $plan->name,
                'plan_type' => $plan->plan_type,
                'product_family' => $plan->product_family,
                'price' => $this->numberOrNull($plan->price),
                'currency' => $plan->currency,
                'interval' => $plan->interval,
            ])
            ->values()
            ->all();

        return MobileApiResponse::success(['plans' => $plans]);
    }

    public function me(Request $request): JsonResponse
    {
        $account = $this->currentAccount($request, 'dashboard');
        $entitlements = $this->entitlements($account);

        return MobileApiResponse::success([
            'user' => $this->userPayload($request->user()),
            'selected_account' => $this->accountPayload($account),
            'active_products' => $this->activeProducts($entitlements),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $account = $this->currentAccount($request, 'dashboard');
        $slot = $this->slotContext($request, $account, 1);
        $alpacaAccount = $slot['alpaca_account'];
        $positions = $this->positionsQuery($account, $alpacaAccount?->getKey())->get();
        $orders = $this->ordersQuery($account, $alpacaAccount?->getKey())->limit(27)->get();
        $activity = $this->systemActivityRows($account, 11);
        $entitlements = $this->entitlements($account);

        return MobileApiResponse::success([
            'user' => $this->userPayload($request->user()),
            'active_products' => $this->activeProducts($entitlements),
            'selected_account' => $this->accountPayload($account),
            'account_snapshot' => $this->accountSnapshotPayload($account, $slot),
            'open_positions_count' => $positions->count(),
            'orders_count' => $orders->count(),
            'latest_activity' => array_slice($activity, 0, 11),
            'alerts' => [
                'trial_locked' => ! (bool) data_get($entitlements, 'subscription_active', false),
                'broker_connected' => $slot['alpaca_account'] !== null,
                'admin_features_exposed' => false,
            ],
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $account = $this->currentAccount($request, 'dashboard');
        $entitlements = $this->entitlements($account);

        return MobileApiResponse::success([
            'products' => collect([
                $this->productPayload('prime_stocks', 'Prime Stocks', $account, $entitlements),
                $this->productPayload('execution', 'Execution', $account, $entitlements),
            ])->values()->all(),
        ]);
    }

    public function productOverview(Request $request, string $product): JsonResponse
    {
        $account = $this->currentAccount($request, 'automation');
        $entitlements = $this->entitlements($account);
        $product = $this->normalizeProduct($product);

        return MobileApiResponse::success([
            'product' => $this->productPayload($product, $this->productName($product), $account, $entitlements),
            'accounts' => $this->accountSlots($request, $account, $entitlements, $product),
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $accounts = $this->accessibleAccounts($request);

        return MobileApiResponse::success([
            'accounts' => $accounts->map(fn (Account $account): array => $this->accountPayload($account))->values()->all(),
        ]);
    }

    public function accountSnapshot(Request $request, string $account): JsonResponse
    {
        $currentAccount = $this->currentAccount($request, 'dashboard');
        $brokerConnection = $this->brokerConnectionForMobileAccount($currentAccount, $account);

        if ($brokerConnection) {
            $slotNumber = (int) ($brokerConnection->slot_number ?? 1);

            return MobileApiResponse::success([
                'account_snapshot' => $this->accountSnapshotPayload($currentAccount, $this->slotContext($request, $currentAccount, $slotNumber)),
                'broker_account' => $this->brokerAccountPayload($brokerConnection),
            ]);
        }

        $resolvedAccount = ctype_digit($account) ? $this->ownedAccount($request, (int) $account) : null;

        if (! $resolvedAccount) {
            return MobileApiResponse::error('account_not_found', 'Account was not found for this user.', [], 404);
        }

        return MobileApiResponse::success([
            'account_snapshot' => $this->accountSnapshotPayload($resolvedAccount, $this->slotContext($request, $resolvedAccount, 1)),
        ]);
    }

    public function brokers(Request $request): JsonResponse
    {
        $account = $this->currentAccount($request, 'broker');

        return MobileApiResponse::success([
            'brokers' => [
                [
                    'broker' => 'alpaca',
                    'label' => 'Alpaca',
                    'mobile_connect_supported' => true,
                    'secret_storage' => 'Laravel encrypted credential payload only',
                ],
            ],
            'accounts' => $this->brokerAccountRows($account),
        ]);
    }

    public function brokerAccounts(Request $request): JsonResponse
    {
        return MobileApiResponse::success([
            'accounts' => $this->brokerAccountRows($this->currentAccount($request, 'broker')),
        ]);
    }

    public function connectAlpaca(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_slot' => ['nullable', 'integer', 'min:1', 'max:19'],
            'account_label' => ['nullable', 'string', 'max:73'],
            'environment' => ['required', 'in:paper,live'],
            'access_mode' => ['nullable', 'in:trade,trade_disabled,read_only'],
            'market_data_feed' => ['nullable', 'in:iex,sip'],
            'access_key_id' => ['required', 'string', 'max:191'],
            'access_secret' => ['required', 'string', 'max:191'],
        ]);

        if ($validator->fails()) {
            return MobileApiResponse::error('validation_failed', 'Check the broker connection fields.', $validator->errors()->toArray(), 422);
        }

        $account = $this->currentAccount($request, 'broker');
        $entitlements = $this->entitlements($account);

        if (! (bool) data_get($entitlements, 'subscription_active', false)) {
            return MobileApiResponse::error('trial_locked', 'Broker connection is locked until an active product subscription is available.', [], 403);
        }

        $slotNumber = (int) ($request->integer('account_slot') ?: 1);
        $slotService = app(CustomerBrokerAccountSlotService::class);

        if (! $slotService->canUseAccountSlot($account, $slotNumber, $entitlements)) {
            return MobileApiResponse::error('account_slot_locked', 'This broker account slot is locked for the current plan.', ['account_slot' => $slotNumber], 403);
        }

        $connection = DB::transaction(function () use ($account, $request, $slotNumber, $slotService): BrokerConnection {
            $connection = $slotService->upsertSlotConnection(
                $account,
                $request->user(),
                $slotNumber,
                trim((string) $request->input('account_label', '')) ?: 'Account '.$slotNumber,
            );

            $credential = new BrokerCredential([
                'label' => 'Alpaca '.ucfirst((string) $request->input('environment')).' API Access',
                'provider' => 'alpaca',
                'status' => 'saved',
                'environment' => $request->input('environment'),
                'access_mode' => $request->input('access_mode', 'trade'),
                'credential_payload' => [
                    'provider' => 'alpaca',
                    'provider_label' => 'Alpaca',
                    'account_label' => trim((string) $request->input('account_label', '')) ?: 'Account '.$slotNumber,
                    'access_mode' => $request->input('access_mode', 'trade'),
                    'environment' => $request->input('environment'),
                    'market_data_feed' => $request->input('market_data_feed', 'iex'),
                    'access_key_id' => $request->input('access_key_id'),
                    'access_secret' => $request->input('access_secret'),
                    'saved_via' => 'mobile_api_v1',
                ],
                'key_last_four' => substr((string) $request->input('access_key_id'), -4),
                'secret_hint' => substr((string) $request->input('access_secret'), -2),
                'is_encrypted' => true,
            ]);

            $connection->brokerCredentials()->save($credential);
            $connection->forceFill([
                'broker' => 'alpaca',
                'slot_number' => $slotNumber,
                'status' => BrokerConnectionStatus::Pending->value,
                'validation_status' => 'pending',
                'last_error' => null,
                'automation_enabled' => false,
                'automation_enabled_at' => null,
            ])->save();

            return $connection->fresh(['brokerCredentials', 'alpacaAccounts']);
        });

        $this->audit($request, $account, 'mobile.broker.connect', $connection, [
            'broker' => 'alpaca',
            'slot_number' => $slotNumber,
            'environment' => $request->input('environment'),
        ]);

        return MobileApiResponse::success([
            'broker_account' => $this->brokerAccountPayload($connection),
            'connected' => false,
            'status' => 'pending_validation',
        ], [], 201);
    }

    public function disconnectBroker(Request $request, int $brokerAccount): JsonResponse
    {
        $account = $this->currentAccount($request, 'broker');
        $connection = $this->brokerConnectionForAccount($account, $brokerAccount);

        if (! $connection) {
            return MobileApiResponse::error('broker_account_not_found', 'Broker account was not found for this user.', [], 404);
        }

        $connection->load(['alpacaAccounts.positions', 'alpacaAccounts.orders', 'brokerCredentials']);
        $openPositions = $connection->alpacaAccounts->flatMap->positions->filter(fn (AlpacaPosition $position) => abs((float) $position->qty) > 0);
        $pendingOrders = $connection->alpacaAccounts->flatMap->orders->filter(fn (AlpacaOrder $order) => $this->orderPending($order));

        if ($openPositions->isNotEmpty() || $pendingOrders->isNotEmpty()) {
            return MobileApiResponse::error('disconnect_warning_required', 'Disconnect is blocked while open positions or pending orders are visible. Review warnings in the web flow before disconnecting.', [
                'open_positions_count' => $openPositions->count(),
                'pending_orders_count' => $pendingOrders->count(),
                'warning_required' => true,
            ], 409);
        }

        DB::transaction(function () use ($connection): void {
            foreach ($connection->alpacaAccounts as $alpacaAccount) {
                $alpacaAccount->positions()->delete();
                $alpacaAccount->orders()->delete();
                $alpacaAccount->bars()->delete();
                $alpacaAccount->botRuns()->delete();
            }

            $connection->alpacaAccounts()->delete();
            $connection->brokerCredentials()->delete();
            $connection->delete();
        });

        $this->audit($request, $account, 'mobile.broker.disconnect', null, ['broker_connection_id' => $brokerAccount]);

        return MobileApiResponse::success(['disconnected' => true]);
    }

    public function brokerStatus(Request $request, int $brokerAccount): JsonResponse
    {
        $connection = $this->brokerConnectionForAccount($this->currentAccount($request, 'broker'), $brokerAccount);

        if (! $connection) {
            return MobileApiResponse::error('broker_account_not_found', 'Broker account was not found for this user.', [], 404);
        }

        return MobileApiResponse::success(['broker_account' => $this->brokerAccountPayload($connection)]);
    }

    public function automation(Request $request, string $product, int $accountSlot): JsonResponse
    {
        $account = $this->currentAccount($request, 'automation');
        $product = $this->normalizeProduct($product);
        $slot = $this->slotContext($request, $account, $accountSlot, $product);
        $entitlements = $this->entitlements($account);

        return MobileApiResponse::success([
            'product' => $this->productPayload($product, $this->productName($product), $account, $entitlements),
            'account_slot' => $slot['slot'],
            'broker_account' => $slot['connection'] ? $this->brokerAccountPayload($slot['connection']) : null,
            'automation_enabled' => (bool) ($slot['connection']?->automation_enabled ?? false),
            'symbols' => $this->symbolRows($account, $accountSlot, $product),
        ]);
    }

    public function automationSymbols(Request $request, string $product, int $accountSlot): JsonResponse
    {
        return MobileApiResponse::success([
            'symbols' => $this->symbolRows($this->currentAccount($request, 'automation'), $accountSlot, $this->normalizeProduct($product)),
        ]);
    }

    public function addAutomationSymbol(Request $request, string $product, int $accountSlot): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'symbol' => ['required', 'string', 'max:15'],
            'mode' => ['nullable', 'in:active,paused,standby'],
        ]);

        if ($validator->fails()) {
            return MobileApiResponse::error('validation_failed', 'Check the symbol fields.', $validator->errors()->toArray(), 422);
        }

        $account = $this->currentAccount($request, 'automation');
        $product = $this->normalizeProduct($product);
        $entitlements = $this->entitlements($account);

        if (! $this->productEntitled($product, $entitlements)) {
            return MobileApiResponse::error('product_entitlement_required', 'This product is not active for the selected account.', [], 403);
        }

        if (! app(CustomerBrokerAccountSlotService::class)->canUseAccountSlot($account, $accountSlot, $entitlements)) {
            return MobileApiResponse::error('account_slot_locked', 'This account slot is locked for the current plan.', ['account_slot' => $accountSlot], 403);
        }

        $symbol = app(ProductAssetScopeResolver::class)->normalizeSymbol((string) $request->input('symbol'));

        if (! is_string($symbol) || ! preg_match('/^[A-Z][A-Z0-9.\/-]{0,14}$/', $symbol)) {
            return MobileApiResponse::error('symbol_invalid', 'Choose a valid supported symbol.', [], 422);
        }

        $instrument = InstrumentMaster::query()->where('symbol', $symbol)->first();

        if ($instrument && ($instrument->tradable ?? true) === false) {
            return MobileApiResponse::error('symbol_not_tradable', 'This symbol is not tradable for the selected product.', [], 422);
        }

        $symbols = collect($this->symbolStates($account, $accountSlot, $product));

        if ($symbols->contains(fn (array $item): bool => strtoupper((string) ($item['symbol'] ?? '')) === $symbol)) {
            return MobileApiResponse::error('duplicate_symbol', 'Symbol already added.', ['symbol' => $symbol], 409);
        }

        $row = [
            'symbol' => $symbol,
            'name' => $instrument?->name ?? $symbol,
            'asset_type' => $instrument?->asset_type ?? 'equity',
            'mode' => $request->input('mode', 'active'),
            'active' => $request->input('mode', 'active') === 'active',
            'status' => 'Watching for setup',
            'updated_at' => now()->toIso8601String(),
        ];

        $symbols->push($row);
        $this->saveSymbolStates($account, $request->user(), $accountSlot, $product, $symbols->values()->all());
        $this->audit($request, $account, 'mobile.automation.symbol.add', null, ['product' => $product, 'account_slot' => $accountSlot, 'symbol' => $symbol]);

        return MobileApiResponse::success(['symbol' => $this->symbolPayload($row, $account, $accountSlot, $product)], [], 201);
    }

    public function removeAutomationSymbol(Request $request, string $product, int $accountSlot, string $symbol): JsonResponse
    {
        $account = $this->currentAccount($request, 'automation');
        $product = $this->normalizeProduct($product);
        $entitlements = $this->entitlements($account);

        if (! $this->productEntitled($product, $entitlements)) {
            return MobileApiResponse::error('product_entitlement_required', 'This product is not active for the selected account.', [], 403);
        }

        $symbol = strtoupper(trim($symbol));
        $slot = $this->slotContext($request, $account, $accountSlot, $product);
        $alpacaAccountId = $slot['alpaca_account']?->getKey();
        $openPosition = $this->positionsQuery($account, $alpacaAccountId)->where('symbol', $symbol)->first();
        $pendingOrder = $this->ordersQuery($account, $alpacaAccountId)->where('symbol', $symbol)->get()->first(fn (AlpacaOrder $order) => $this->orderPending($order));

        if ($openPosition || $pendingOrder) {
            return MobileApiResponse::error('symbol_remove_warning_required', 'Removing a symbol is blocked while an open position or pending order is visible. Positions stay open if a symbol is removed.', [
                'symbol' => $symbol,
                'open_position' => (bool) $openPosition,
                'pending_order' => (bool) $pendingOrder,
                'warning_required' => true,
            ], 409);
        }

        $updated = collect($this->symbolStates($account, $accountSlot, $product))
            ->reject(fn (array $item): bool => strtoupper((string) ($item['symbol'] ?? '')) === $symbol)
            ->values()
            ->all();

        $this->saveSymbolStates($account, $request->user(), $accountSlot, $product, $updated);
        $this->audit($request, $account, 'mobile.automation.symbol.remove', null, ['product' => $product, 'account_slot' => $accountSlot, 'symbol' => $symbol]);

        return MobileApiResponse::success(['removed' => true, 'symbols' => $this->symbolRows($account, $accountSlot, $product)]);
    }

    public function toggleAutomation(Request $request, string $product, int $accountSlot): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'enabled' => ['required', 'boolean'],
            'confirm_live' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return MobileApiResponse::error('validation_failed', 'Check the automation toggle fields.', $validator->errors()->toArray(), 422);
        }

        $account = $this->currentAccount($request, 'automation');
        $product = $this->normalizeProduct($product);
        $entitlements = $this->entitlements($account);

        if (! $this->productEntitled($product, $entitlements)) {
            return MobileApiResponse::error('product_entitlement_required', 'Automation requires an active product entitlement.', [], 403);
        }

        $slot = $this->slotContext($request, $account, $accountSlot, $product);
        $connection = $slot['connection'];
        $alpacaAccount = $slot['alpaca_account'];

        if (! $connection || ! $alpacaAccount || ! in_array(strtolower((string) $alpacaAccount->sync_status), ['success', 'partial_success'], true)) {
            return MobileApiResponse::error('broker_not_connected', 'Connect and verify a broker account before toggling automation.', [], 409);
        }

        if ((bool) $request->boolean('enabled') && strtolower((string) $alpacaAccount->environment) === 'live' && ! $request->boolean('confirm_live')) {
            return MobileApiResponse::error('live_account_warning_required', 'Live account automation requires explicit confirmation.', ['warning_required' => true], 409);
        }

        $connection->forceFill([
            'automation_enabled' => (bool) $request->boolean('enabled'),
            'automation_enabled_at' => $request->boolean('enabled') ? now() : null,
        ])->save();

        app(CustomerBrokerAccountSlotService::class)->upsertSlotProfile($account, $request->user(), $accountSlot, [
            'automation_enabled' => (bool) $request->boolean('enabled'),
            'product' => $product,
        ]);
        $this->audit($request, $account, 'mobile.automation.toggle', $connection, ['product' => $product, 'account_slot' => $accountSlot, 'enabled' => (bool) $request->boolean('enabled')]);

        return MobileApiResponse::success([
            'automation_enabled' => (bool) $connection->automation_enabled,
            'broker_account' => $this->brokerAccountPayload($connection->fresh(['alpacaAccounts', 'brokerCredentials'])),
        ]);
    }

    public function positions(Request $request): JsonResponse
    {
        $account = $this->currentAccount($request, 'trading');

        return MobileApiResponse::success([
            'positions' => $this->positionsQuery($account)->get()->map(fn (AlpacaPosition $position): array => $this->positionPayload(
                $position,
                $this->brokerConnectionForAlpacaAccount($account, $position->alpaca_account_id),
            ))->values()->all(),
        ]);
    }

    public function position(Request $request, string $symbol): JsonResponse
    {
        $position = $this->positionsQuery($this->currentAccount($request, 'trading'))->where('symbol', strtoupper($symbol))->first();

        if (! $position) {
            return MobileApiResponse::error('position_not_found', 'Position was not found for this user.', [], 404);
        }

        return MobileApiResponse::success(['position' => $this->positionPayload($position)]);
    }

    public function manualClosePosition(Request $request, string $symbol): JsonResponse
    {
        $account = $this->currentAccount($request, 'trading');
        $position = $this->positionsQuery($account)->where('symbol', strtoupper($symbol))->first();

        if (! $position) {
            return MobileApiResponse::error('position_not_found', 'Position was not found for this user.', [], 404);
        }

        if (! $request->boolean('confirm')) {
            return MobileApiResponse::error('manual_close_confirmation_required', 'Manual close requires explicit confirmation after reviewing the warning.', [
                'symbol' => strtoupper($symbol),
                'warning_required' => true,
                'warning' => 'Manual close is separate from automated strategy/runtime exits and may realize gains or losses.',
            ], 409);
        }

        $this->audit($request, $account, 'mobile.position.manual_close.requested', $position, ['symbol' => strtoupper($symbol)]);

        return MobileApiResponse::error('manual_close_service_unavailable', 'Manual close submission is not enabled for mobile v1. Use the existing guarded web action.', [
            'symbol' => strtoupper($symbol),
            'order_submitted' => false,
        ], 409);
    }

    public function orders(Request $request): JsonResponse
    {
        $account = $this->currentAccount($request, 'trading');

        return MobileApiResponse::success([
            'orders' => $this->ordersQuery($account)->get()->map(fn (AlpacaOrder $order): array => $this->orderPayload(
                $order,
                $this->brokerConnectionForAlpacaAccount($account, $order->alpaca_account_id),
            ))->values()->all(),
        ]);
    }

    public function order(Request $request, int $order): JsonResponse
    {
        $resolvedOrder = $this->ordersQuery($this->currentAccount($request, 'trading'))->whereKey($order)->first();

        if (! $resolvedOrder) {
            return MobileApiResponse::error('order_not_found', 'Order was not found for this user.', [], 404);
        }

        return MobileApiResponse::success(['order' => $this->orderPayload($resolvedOrder)]);
    }

    public function tradeActivity(Request $request): JsonResponse
    {
        $account = $this->currentAccount($request, 'trading');
        $orders = $this->ordersQuery($account)->limit(27)->get()->map(function (AlpacaOrder $order) use ($account): array {
            $brokerConnection = $this->brokerConnectionForAlpacaAccount($account, $order->alpaca_account_id);

            return [
                'type' => 'trade',
                'symbol' => $order->symbol,
                'label' => Str::headline(trim(($order->side ?? 'order').' '.($order->status ?? 'recorded'))),
                'detail' => 'Trade activity recorded for '.$order->symbol.'.',
                'product' => $this->safeProductLabel($order->request_action),
                'account_label' => $this->brokerAccountLabel($brokerConnection) ?? ($this->accountPayload($account)['label'] ?? 'Account'),
                'broker_account_ref' => $brokerConnection?->getKey(),
                'slot_number' => $brokerConnection ? (int) ($brokerConnection->slot_number ?? 1) : null,
                'timestamp' => $order->submitted_at?->toIso8601String() ?? $order->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return MobileApiResponse::success(['activity' => $orders]);
    }

    public function systemActivity(Request $request): JsonResponse
    {
        return MobileApiResponse::success(['activity' => $this->systemActivityRows($this->currentAccount($request, 'trading'), 27)]);
    }

    public function performanceSummary(Request $request): JsonResponse
    {
        $positions = $this->positionsQuery($this->currentAccount($request, 'trading'))->get();

        return MobileApiResponse::success([
            'summary' => [
                'market_value' => round($positions->sum(fn (AlpacaPosition $position) => (float) $position->market_value), 2),
                'unrealized_pl' => round($positions->sum(fn (AlpacaPosition $position) => (float) $position->unrealized_pl), 2),
                'open_positions_count' => $positions->count(),
            ],
        ]);
    }

    public function performanceCurve(Request $request): JsonResponse
    {
        $orders = $this->ordersQuery($this->currentAccount($request, 'trading'))->oldest('submitted_at')->limit(73)->get();
        $running = 0.0;

        return MobileApiResponse::success([
            'curve' => $orders->map(function (AlpacaOrder $order) use (&$running): array {
                $running += (float) $order->notional;

                return [
                    'timestamp' => $order->submitted_at?->toIso8601String() ?? $order->created_at?->toIso8601String(),
                    'value' => round($running, 2),
                ];
            })->values()->all(),
        ]);
    }

    public function billingSummary(Request $request): JsonResponse
    {
        $account = $this->currentAccount($request, 'billing');

        return MobileApiResponse::success([
            'billing' => data_get($this->entitlements($account), 'billing_state', []),
        ]);
    }

    public function billingPortal(): JsonResponse
    {
        return MobileApiResponse::error('billing_portal_web_required', 'Billing portal creation is not enabled for mobile v1. Use the existing secure web billing path.', [], 409);
    }

    public function supportTickets(Request $request): JsonResponse
    {
        $account = $this->currentAccount($request, 'settings');

        return MobileApiResponse::success([
            'tickets' => MobileSupportTicket::query()
                ->where('account_id', $account?->getKey())
                ->latest()
                ->limit(27)
                ->get()
                ->map(fn (MobileSupportTicket $ticket): array => $this->supportTicketPayload($ticket, false))
                ->values()
                ->all(),
        ]);
    }

    public function createSupportTicket(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => ['required', 'string', 'max:73'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return MobileApiResponse::error('validation_failed', 'Check the support ticket fields.', $validator->errors()->toArray(), 422);
        }

        $account = $this->currentAccount($request, 'settings');
        $ticket = MobileSupportTicket::query()->create([
            'account_id' => $account->getKey(),
            'user_id' => $request->user()->getKey(),
            'subject' => $request->input('subject'),
            'status' => 'open',
            'priority' => 'normal',
            'messages' => [[
                'sender' => 'customer',
                'message' => $request->input('message'),
                'created_at' => now()->toIso8601String(),
            ]],
            'last_reply_at' => now(),
        ]);

        $this->audit($request, $account, 'mobile.support.ticket.create', $ticket);

        return MobileApiResponse::success(['ticket' => $this->supportTicketPayload($ticket)], [], 201);
    }

    public function supportTicket(Request $request, int $ticket): JsonResponse
    {
        $resolvedTicket = $this->supportTicketForAccount($request, $ticket);

        if (! $resolvedTicket) {
            return MobileApiResponse::error('ticket_not_found', 'Support ticket was not found for this user.', [], 404);
        }

        return MobileApiResponse::success(['ticket' => $this->supportTicketPayload($resolvedTicket)]);
    }

    public function replySupportTicket(Request $request, int $ticket): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return MobileApiResponse::error('validation_failed', 'Check the support reply field.', $validator->errors()->toArray(), 422);
        }

        $resolvedTicket = $this->supportTicketForAccount($request, $ticket);

        if (! $resolvedTicket) {
            return MobileApiResponse::error('ticket_not_found', 'Support ticket was not found for this user.', [], 404);
        }

        $messages = is_array($resolvedTicket->messages) ? $resolvedTicket->messages : [];
        $messages[] = [
            'sender' => 'customer',
            'message' => $request->input('message'),
            'created_at' => now()->toIso8601String(),
        ];
        $resolvedTicket->forceFill([
            'messages' => $messages,
            'last_reply_at' => now(),
        ])->save();
        $this->audit($request, $resolvedTicket->account, 'mobile.support.ticket.reply', $resolvedTicket);

        return MobileApiResponse::success(['ticket' => $this->supportTicketPayload($resolvedTicket->fresh())]);
    }

    public function affiliateSummary(Request $request): JsonResponse
    {
        $approved = $this->affiliateApproved($request);

        if (! $approved) {
            return MobileApiResponse::error('affiliate_not_approved', 'Affiliate access is available only for approved affiliates.', [], 403);
        }

        $handler = $this->affiliateHandler($request);

        return MobileApiResponse::success([
            'handler' => $handler,
            'referrals_count' => ReferralAttribution::query()->where('referral_code', $handler)->count(),
            'payouts_status' => 'manual_review',
        ]);
    }

    public function affiliateReferrals(Request $request): JsonResponse
    {
        if (! $this->affiliateApproved($request)) {
            return MobileApiResponse::error('affiliate_not_approved', 'Affiliate access is available only for approved affiliates.', [], 403);
        }

        return MobileApiResponse::success([
            'referrals' => ReferralAttribution::query()
                ->where('referral_code', $this->affiliateHandler($request))
                ->latest()
                ->limit(27)
                ->get()
                ->map(fn (ReferralAttribution $referral): array => [
                    'status' => $referral->status,
                    'created_at' => $referral->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function affiliatePayouts(Request $request): JsonResponse
    {
        if (! $this->affiliateApproved($request)) {
            return MobileApiResponse::error('affiliate_not_approved', 'Affiliate access is available only for approved affiliates.', [], 403);
        }

        return MobileApiResponse::success([
            'payouts' => [],
            'status' => 'manual_review',
        ]);
    }

    protected function currentAccount(Request $request, string $preset): ?Account
    {
        return app(CurrentCustomerAccountResolver::class)->resolveForPreset($request->user(), $preset)
            ?->fresh([
                'users',
                'subscriptions.subscriptionPlan',
                'subscriptions.items.subscriptionPlan',
                'brokerConnections.brokerCredentials',
                'brokerConnections.alpacaAccounts.positions',
                'brokerConnections.alpacaAccounts.orders',
                'alpacaAccounts',
                'alpacaPositions',
                'alpacaOrders',
                'activityLogs',
                'automationSettings',
            ]);
    }

    protected function accessibleAccounts(Request $request)
    {
        return Account::query()
            ->where(function ($query) use ($request): void {
                $query->where('owner_user_id', $request->user()->getKey())
                    ->orWhereHas('users', fn ($users) => $users
                        ->where('users.id', $request->user()->getKey())
                        ->where('account_user.status', 'active'));
            })
            ->with(['brokerConnections.brokerCredentials', 'brokerConnections.alpacaAccounts'])
            ->orderBy('name')
            ->get();
    }

    protected function ownedAccount(Request $request, int $accountId): ?Account
    {
        return $this->accessibleAccounts($request)->firstWhere('id', $accountId);
    }

    protected function entitlements(?Account $account): array
    {
        return app(Bismel1EntitlementService::class)->resolve($account);
    }

    protected function slotContext(Request $request, ?Account $account, int $slotNumber, string $product = 'prime_stocks'): array
    {
        $entitlements = $this->entitlements($account);

        return app(CustomerBrokerAccountSlotService::class)->selectedSlotContext($account, max(1, $slotNumber), $entitlements, $product);
    }

    protected function accountSlots(Request $request, ?Account $account, array $entitlements, string $product): array
    {
        return app(CustomerBrokerAccountSlotService::class)->getAvailableAccountSlotsForUser($request->user(), $account, $entitlements, 1, true, $product);
    }

    protected function brokerConnectionForAccount(?Account $account, int $brokerConnectionId): ?BrokerConnection
    {
        if (! $account) {
            return null;
        }

        return BrokerConnection::query()
            ->where('account_id', $account->getKey())
            ->whereKey($brokerConnectionId)
            ->with(['brokerCredentials', 'alpacaAccounts.positions', 'alpacaAccounts.orders'])
            ->first();
    }

    protected function brokerConnectionForMobileAccount(?Account $account, string $accountRef): ?BrokerConnection
    {
        if (! $account) {
            return null;
        }

        $accountRef = trim($accountRef);
        $connections = $account->brokerConnections;

        if (! $connections || ! $connections->count()) {
            $connections = BrokerConnection::query()
                ->where('account_id', $account->getKey())
                ->with(['brokerCredentials', 'alpacaAccounts'])
                ->get();
        }

        return $connections->first(function (BrokerConnection $connection) use ($accountRef): bool {
            $slotNumber = (string) ((int) ($connection->slot_number ?? 1));

            return (string) $connection->getKey() === $accountRef
                || $slotNumber === $accountRef
                || (string) ($connection->name ?? '') === $accountRef;
        });
    }

    protected function brokerConnectionForAlpacaAccount(?Account $account, mixed $alpacaAccountId): ?BrokerConnection
    {
        if (! $account || ! $alpacaAccountId) {
            return null;
        }

        $connections = $account->brokerConnections;
        $connection = $connections?->first(fn (BrokerConnection $item): bool => (bool) $item->alpacaAccounts?->contains(
            fn ($alpacaAccount): bool => (int) $alpacaAccount->getKey() === (int) $alpacaAccountId,
        ));

        if ($connection) {
            return $connection;
        }

        return BrokerConnection::query()
            ->where('account_id', $account->getKey())
            ->whereHas('alpacaAccounts', fn ($query) => $query->whereKey($alpacaAccountId))
            ->with(['brokerCredentials', 'alpacaAccounts'])
            ->first();
    }

    protected function positionsQuery(?Account $account, ?int $alpacaAccountId = null)
    {
        return AlpacaPosition::query()
            ->where('account_id', $account?->getKey() ?? 0)
            ->when($alpacaAccountId, fn ($query) => $query->where('alpaca_account_id', $alpacaAccountId))
            ->orderByDesc('updated_at');
    }

    protected function ordersQuery(?Account $account, ?int $alpacaAccountId = null)
    {
        return AlpacaOrder::query()
            ->where('account_id', $account?->getKey() ?? 0)
            ->when($alpacaAccountId, fn ($query) => $query->where('alpaca_account_id', $alpacaAccountId))
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at');
    }

    protected function productPayload(string $product, string $label, ?Account $account, array $entitlements): array
    {
        $entitled = $this->productEntitled($product, $entitlements);

        return [
            'product_code' => $product,
            'product_name' => $label,
            'entitlement' => $entitled ? 'active' : 'inactive',
            'trial_locked' => ! (bool) data_get($entitlements, 'subscription_active', false),
            'automation_allowed' => $entitled,
            'broker_required' => true,
            'accounts_count' => $account?->brokerConnections?->count() ?? 0,
        ];
    }

    protected function productEntitled(string $product, array $entitlements): bool
    {
        return match ($this->normalizeProduct($product)) {
            'execution' => (bool) data_get($entitlements, 'capabilities.can_use_execute', false),
            default => (bool) data_get($entitlements, 'capabilities.can_use_stocks_automation', false),
        };
    }

    protected function activeProducts(array $entitlements): array
    {
        return collect([
            (bool) data_get($entitlements, 'capabilities.can_use_stocks_automation', false) ? ['code' => 'prime_stocks', 'name' => 'Prime Stocks'] : null,
            (bool) data_get($entitlements, 'capabilities.can_use_execute', false) ? ['code' => 'execution', 'name' => 'Execution'] : null,
        ])->filter()->values()->all();
    }

    protected function productName(string $product): string
    {
        return $this->normalizeProduct($product) === 'execution' ? 'Execution' : 'Prime Stocks';
    }

    protected function normalizeProduct(string $product): string
    {
        return in_array(strtolower($product), ['execution', 'execute'], true) ? 'execution' : 'prime_stocks';
    }

    protected function userPayload($user): array
    {
        return [
            'name' => $user?->name,
            'email' => $user?->email,
            'email_verified' => (bool) $user?->email_verified_at,
        ];
    }

    protected function accountPayload(?Account $account): ?array
    {
        if (! $account) {
            return null;
        }

        return [
            'account_ref' => 'account-'.$account->getKey(),
            'label' => $account->name,
            'status' => (string) ($account->status?->value ?? $account->status ?? 'active'),
        ];
    }

    protected function accountSnapshotPayload(?Account $account, array $slot): array
    {
        $alpacaAccount = $slot['alpaca_account'] ?? null;
        $brokerConnection = $alpacaAccount ? $this->brokerConnectionForAlpacaAccount($account, $alpacaAccount->getKey()) : null;
        $buyingPower = $this->numberOrNull($alpacaAccount?->buying_power);
        $equity = $this->numberOrNull($alpacaAccount?->equity);
        $lastSync = $alpacaAccount?->last_synced_at?->toIso8601String();

        return [
            'account' => $this->accountPayload($account),
            'broker_account_ref' => $brokerConnection?->getKey(),
            'slot_number' => $brokerConnection ? (int) ($brokerConnection->slot_number ?? 1) : (int) ($slot['slot'] ?? 1),
            'buying_power' => $buyingPower,
            'equity' => $equity,
            'last_sync' => $lastSync,
            'broker' => $alpacaAccount ? [
                'broker' => 'alpaca',
                'environment' => $alpacaAccount->environment,
                'connected' => in_array(strtolower((string) $alpacaAccount->sync_status), ['success', 'partial_success'], true),
                'status' => $alpacaAccount->sync_status,
                'buying_power' => $buyingPower,
                'equity' => $equity,
                'last_sync' => $lastSync,
                'account_label' => $this->maskedAccountLabel($alpacaAccount),
            ] : null,
        ];
    }

    protected function brokerAccountRows(?Account $account): array
    {
        return BrokerConnection::query()
            ->where('account_id', $account?->getKey() ?? 0)
            ->with(['brokerCredentials', 'alpacaAccounts'])
            ->orderBy('slot_number')
            ->get()
            ->map(fn (BrokerConnection $connection): array => $this->brokerAccountPayload($connection))
            ->values()
            ->all();
    }

    protected function brokerAccountPayload(BrokerConnection $connection): array
    {
        $alpacaAccount = $connection->alpacaAccounts->first(fn ($item) => (bool) $item->is_active) ?? $connection->alpacaAccounts->first();
        $credential = $connection->brokerCredentials->sortByDesc('id')->first();
        $positions = $alpacaAccount?->positions ?? collect();
        $orders = $alpacaAccount?->orders ?? collect();
        $openPositions = $positions->filter(fn (AlpacaPosition $position) => abs((float) $position->qty) > 0);
        $pendingOrders = $orders->filter(fn (AlpacaOrder $order) => $this->orderPending($order));

        return [
            'broker_account_ref' => (int) $connection->getKey(),
            'slot_number' => (int) ($connection->slot_number ?? 1),
            'broker' => $connection->broker,
            'environment' => $alpacaAccount?->environment ?? $credential?->environment,
            'connected' => $alpacaAccount ? in_array(strtolower((string) $alpacaAccount->sync_status), ['success', 'partial_success'], true) : false,
            'status' => $alpacaAccount?->sync_status ?? $connection->validation_status ?? 'pending',
            'buying_power' => $this->numberOrNull($alpacaAccount?->buying_power),
            'equity' => $this->numberOrNull($alpacaAccount?->equity),
            'open_positions_count' => $openPositions->count(),
            'orders_count' => $orders->count(),
            'pending_orders_count' => $pendingOrders->count(),
            'last_sync' => $alpacaAccount?->last_synced_at?->toIso8601String() ?? $connection->last_synced_at?->toIso8601String(),
            'account_label' => $alpacaAccount ? $this->maskedAccountLabel($alpacaAccount) : ($connection->name ?? 'Account '.(int) ($connection->slot_number ?? 1)),
            'automation_enabled' => (bool) ($connection->automation_enabled ?? false),
        ];
    }

    protected function maskedAccountLabel($alpacaAccount): string
    {
        $suffix = trim((string) ($alpacaAccount?->account_number ?? ''));

        return ($alpacaAccount?->name ?: 'Alpaca account').($suffix !== '' ? ' ****'.substr($suffix, -4) : '');
    }

    protected function brokerAccountLabel(?BrokerConnection $connection): ?string
    {
        if (! $connection) {
            return null;
        }

        $alpacaAccount = $connection->alpacaAccounts?->first(fn ($item) => (bool) $item->is_active) ?? $connection->alpacaAccounts?->first();

        return $alpacaAccount ? $this->maskedAccountLabel($alpacaAccount) : ($connection->name ?? 'Account '.(int) ($connection->slot_number ?? 1));
    }

    protected function symbolRows(?Account $account, int $slotNumber, string $product): array
    {
        return collect($this->symbolStates($account, $slotNumber, $product))
            ->map(fn (array $row): array => $this->symbolPayload($row, $account, $slotNumber, $product))
            ->values()
            ->all();
    }

    protected function symbolStates(?Account $account, int $slotNumber, string $product): array
    {
        $automationSetting = AutomationSetting::query()->where('account_id', $account?->getKey())->latest('id')->first();
        $slotCode = app(CustomerBrokerAccountSlotService::class)->slotInternalCode($slotNumber);
        $slotProfile = data_get($automationSetting?->settings, 'account_slots.'.$slotCode, []);
        $productConfig = data_get($slotProfile, 'product_configs.'.$product, []);
        $states = data_get($productConfig, 'symbol_states', data_get($slotProfile, 'symbol_states', []));

        return is_array($states) ? $states : [];
    }

    protected function saveSymbolStates(?Account $account, $user, int $slotNumber, string $product, array $states): void
    {
        $selectedSymbols = collect($states)->pluck('symbol')->filter()->values()->all();
        $profile = $product === 'execution'
            ? ['product_configs' => [$product => ['selected_symbols' => $selectedSymbols, 'symbol_states' => $states, 'symbols_updated_at' => now()->toIso8601String()]]]
            : ['selected_symbols' => $selectedSymbols, 'symbol_states' => $states, 'symbols_updated_at' => now()->toIso8601String()];

        app(CustomerBrokerAccountSlotService::class)->upsertSlotProfile($account, $user, $slotNumber, $profile);
    }

    protected function symbolPayload(array $row, ?Account $account, int $slotNumber, string $product): array
    {
        $symbol = strtoupper((string) ($row['symbol'] ?? ''));

        return [
            'symbol' => $symbol,
            'name' => $row['name'] ?? $symbol,
            'asset_type' => $row['asset_type'] ?? 'equity',
            'active' => (bool) ($row['active'] ?? (($row['mode'] ?? 'active') === 'active')),
            'status' => $row['status'] ?? 'Watching for setup',
            'latest_price' => null,
            'change_percent' => null,
            'runtime_status' => 'not_loaded',
            'latest_decision' => null,
            'blocker_safe' => true,
            'strategy_label' => $product === 'execution' ? 'Execution managed strategy' : 'Prime Stocks',
            'actions_allowed' => [
                'remove' => true,
                'toggle' => true,
            ],
        ];
    }

    protected function positionPayload(AlpacaPosition $position, ?BrokerConnection $brokerConnection = null): array
    {
        return [
            'account_ref' => 'account-'.$position->account_id,
            'broker_account_ref' => $brokerConnection?->getKey(),
            'slot_number' => $brokerConnection ? (int) ($brokerConnection->slot_number ?? 1) : null,
            'account_label' => $this->brokerAccountLabel($brokerConnection),
            'symbol' => $position->symbol,
            'qty' => $this->numberOrNull($position->qty),
            'avg_entry' => $this->numberOrNull($position->avg_entry_price),
            'current_price' => $this->numberOrNull($position->current_price),
            'market_value' => $this->numberOrNull($position->market_value),
            'unrealized_pl' => $this->numberOrNull($position->unrealized_pl),
            'unrealized_pl_percent' => $this->numberOrNull($position->unrealized_plpc),
            'action_allowed_manual_close' => true,
            'warning_required' => true,
        ];
    }

    protected function orderPayload(AlpacaOrder $order, ?BrokerConnection $brokerConnection = null): array
    {
        return [
            'order_ref' => (int) $order->getKey(),
            'account_ref' => 'account-'.$order->account_id,
            'broker_account_ref' => $brokerConnection?->getKey(),
            'slot_number' => $brokerConnection ? (int) ($brokerConnection->slot_number ?? 1) : null,
            'account_label' => $this->brokerAccountLabel($brokerConnection),
            'symbol' => $order->symbol,
            'side' => $order->side,
            'status' => $order->status,
            'qty' => $this->numberOrNull($order->qty),
            'filled_qty' => $this->numberOrNull($order->filled_qty),
            'avg_fill' => $this->numberOrNull($order->filled_avg_price),
            'order_value' => $this->numberOrNull($order->notional),
            'submitted_at' => $order->submitted_at?->toIso8601String(),
            'filled_at' => $order->filled_at?->toIso8601String(),
            'safe_source_label' => $this->safeProductLabel($order->request_action),
        ];
    }

    protected function systemActivityRows(?Account $account, int $limit): array
    {
        return $account?->activityLogs()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($log): array => [
                'type' => 'system',
                'symbol' => data_get($log->context, 'symbol'),
                'label' => Str::headline((string) $log->type),
                'detail' => $log->message,
                'product' => data_get($log->context, 'product', 'Bismel1'),
                'account_label' => $account->name,
                'timestamp' => $log->created_at?->toIso8601String(),
            ])
            ->values()
            ->all() ?? [];
    }

    protected function supportTicketForAccount(Request $request, int $ticket): ?MobileSupportTicket
    {
        $account = $this->currentAccount($request, 'settings');

        return MobileSupportTicket::query()
            ->where('account_id', $account?->getKey())
            ->whereKey($ticket)
            ->first();
    }

    protected function supportTicketPayload(MobileSupportTicket $ticket, bool $withMessages = true): array
    {
        return [
            'ticket_ref' => (int) $ticket->getKey(),
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'last_reply_at' => $ticket->last_reply_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'messages' => $withMessages ? (is_array($ticket->messages) ? $ticket->messages : []) : null,
        ];
    }

    protected function orderPending(AlpacaOrder $order): bool
    {
        return in_array(strtolower((string) $order->status), ['new', 'accepted', 'pending_new', 'partially_filled', 'pending_replace', 'pending_cancel'], true);
    }

    protected function safeProductLabel(?string $value): string
    {
        $value = strtolower((string) $value);

        return str_contains($value, 'execution') ? 'Execution' : (str_contains($value, 'prime') ? 'Prime Stocks' : 'Bismel1');
    }

    protected function affiliateHandler(Request $request): string
    {
        return strtoupper(Str::before((string) $request->user()->email, '@'));
    }

    protected function affiliateApproved(Request $request): bool
    {
        $handler = $this->affiliateHandler($request);

        return ReferralAttribution::query()->where('referral_code', $handler)->exists()
            || DB::table('affiliate_commissions')->where('affiliate_username', $handler)->exists();
    }

    protected function audit(Request $request, ?Account $account, string $action, $target = null, array $context = []): void
    {
        MobileAuditLog::query()->create([
            'account_id' => $account?->getKey(),
            'user_id' => $request->user()?->getKey(),
            'action' => $action,
            'target_type' => is_object($target) ? $target::class : null,
            'target_id' => is_object($target) && method_exists($target, 'getKey') ? $target->getKey() : null,
            'ip_address' => (string) $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 191, ''),
            'context' => $context,
        ]);
    }

    protected function numberOrNull(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
