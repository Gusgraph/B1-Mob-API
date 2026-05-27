<?php
// أَعُوذُ بِٱللَّهِ مِنْ الْشَيْطَانٍ الْرَجِيمٍ ✧ بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ ✧ اعوز بالله من الشياطين و ان يحضرون ✧ بسم الله الرحمن الرحيم ✧ الله لا إله إلا هو الحي القيوم
// Bismillahi ar-Rahmani ar-Rahim Audhu billahi min ash-shayatin wa an yahdurun Bismillah ar-Rahman ar-Rahim Allah la ilaha illa huwa al-hayy al-qayyum. Tamsa Allahu ala ayunihim
// version: x
// ======================================================
// - App Name: bismel1.com
// - Gusgraph -
// - Author: Gus Kazem
// - https://Gusgraph.com
// - File Path: tests/Feature/Api/Mobile/MobileApiFoundationTest.php
// =====================================================

namespace Tests\Feature\Api\Mobile;

use App\Models\AffiliateCommission;
use App\Models\AlpacaAccount;
use App\Models\AlpacaOrder;
use App\Models\AlpacaPosition;
use App\Models\BrokerConnection;
use App\Models\InstrumentMaster;
use App\Models\MobileAccessToken;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesAccessContext;
use Tests\Concerns\CreatesBismel1Entitlements;
use Tests\TestCase;

class MobileApiFoundationTest extends TestCase
{
    use CreatesAccessContext;
    use CreatesBismel1Entitlements;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }

    public function test_unauthenticated_mobile_api_returns_401(): void
    {
        $this->getJson('/api/mobile/v1/dashboard')
            ->assertStatus(401)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error.code', 'mobile_unauthenticated');
    }

    public function test_unverified_user_is_blocked(): void
    {
        [$user] = $this->createAccessContext();
        $user->forceFill(['email_verified_at' => null])->save();

        $this->getJson('/api/mobile/v1/dashboard', $this->bearerHeaders($user))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'email_unverified');
    }

    public function test_trial_user_can_view_read_only_data_but_cannot_write_locked_actions(): void
    {
        [$user] = $this->createAccessContext();

        $this->getJson('/api/mobile/v1/dashboard', $this->bearerHeaders($user))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->postJson('/api/mobile/v1/brokers/alpaca/connect', [
            'environment' => 'paper',
            'access_key_id' => 'PKTRIAL123',
            'access_secret' => 'SECRETTRIAL',
        ], $this->bearerHeaders($user))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'trial_locked');
    }

    public function test_paid_user_can_list_dashboard_and_products(): void
    {
        [$user, $account] = $this->createAccessContext();
        $this->seedConfirmedBismel1Subscription($account, 'BISMILLAH1_BOT_PRIME');

        $this->getJson('/api/mobile/v1/dashboard', $this->bearerHeaders($user))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['data' => ['user', 'active_products', 'selected_account']]);

        $this->getJson('/api/mobile/v1/products', $this->bearerHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.products.0.product_code', 'prime_stocks');
    }

    public function test_paid_user_can_add_symbol(): void
    {
        [$user, $account] = $this->createAccessContext();
        $this->seedConfirmedBismel1Subscription($account, 'BISMILLAH1_BOT_PRIME');
        $this->seedInstrument('AAPL');

        $this->postJson('/api/mobile/v1/automation/prime_stocks/1/symbols', [
            'symbol' => 'AAPL',
        ], $this->bearerHeaders($user))
            ->assertCreated()
            ->assertJsonPath('data.symbol.symbol', 'AAPL');
    }

    public function test_duplicate_symbol_is_rejected_cleanly(): void
    {
        [$user, $account] = $this->createAccessContext();
        $this->seedConfirmedBismel1Subscription($account, 'BISMILLAH1_BOT_PRIME');
        $this->seedInstrument('AAPL');

        $this->postJson('/api/mobile/v1/automation/prime_stocks/1/symbols', ['symbol' => 'AAPL'], $this->bearerHeaders($user))->assertCreated();

        $this->postJson('/api/mobile/v1/automation/prime_stocks/1/symbols', ['symbol' => 'AAPL'], $this->bearerHeaders($user))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'duplicate_symbol');
    }

    public function test_remove_symbol_with_open_position_returns_warning_block_response(): void
    {
        [$user, $account] = $this->createAccessContext();
        $this->seedConfirmedBismel1Subscription($account, 'BISMILLAH1_BOT_PRIME');
        $this->seedInstrument('AAPL');
        $alpacaAccount = $this->seedConnectedBroker($account);
        AlpacaPosition::query()->create([
            'account_id' => $account->getKey(),
            'alpaca_account_id' => $alpacaAccount->getKey(),
            'broker_connection_id' => $alpacaAccount->broker_connection_id,
            'symbol' => 'AAPL',
            'qty' => 3,
        ]);

        $this->postJson('/api/mobile/v1/automation/prime_stocks/1/symbols', ['symbol' => 'AAPL'], $this->bearerHeaders($user))->assertCreated();

        $this->deleteJson('/api/mobile/v1/automation/prime_stocks/1/symbols/AAPL', [], $this->bearerHeaders($user))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'symbol_remove_warning_required')
            ->assertJsonPath('error.details.warning_required', true);
    }

    public function test_automation_toggle_requires_entitlement_and_broker_status(): void
    {
        [$trialUser] = $this->createAccessContext(['slug' => 'trial-'.Str::random(11)]);

        $this->postJson('/api/mobile/v1/automation/prime_stocks/1/toggle', ['enabled' => true], $this->bearerHeaders($trialUser))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'product_entitlement_required');

        [$paidUser, $account] = $this->createAccessContext(['slug' => 'paid-'.Str::random(11)]);
        $this->seedConfirmedBismel1Subscription($account, 'BISMILLAH1_BOT_PRIME');

        $this->postJson('/api/mobile/v1/automation/prime_stocks/1/toggle', ['enabled' => true], $this->bearerHeaders($paidUser))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'broker_not_connected');
    }

    public function test_broker_connect_never_returns_secret(): void
    {
        [$user, $account] = $this->createAccessContext();
        $this->seedConfirmedBismel1Subscription($account, 'BISMILLAH1_BOT_PRIME');

        $response = $this->postJson('/api/mobile/v1/brokers/alpaca/connect', [
            'environment' => 'paper',
            'access_key_id' => 'PKSECRET12345',
            'access_secret' => 'SUPER-SECRET-VALUE',
        ], $this->bearerHeaders($user))
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $this->assertStringNotContainsString('SUPER-SECRET-VALUE', $response->getContent());
        $this->assertStringNotContainsString('PKSECRET12345', $response->getContent());
    }

    public function test_broker_connect_accepts_mobile_key_aliases(): void
    {
        [$user, $account] = $this->createAccessContext();
        $this->seedConfirmedBismel1Subscription($account, 'BISMILLAH1_BOT_PRIME');

        $response = $this->postJson('/api/mobile/v1/brokers/alpaca/connect', [
            'api_key' => 'PKALIAS12345',
            'api_secret' => 'ALIAS-SECRET-VALUE',
        ], $this->bearerHeaders($user))
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'pending_validation');

        $this->assertStringNotContainsString('ALIAS-SECRET-VALUE', $response->getContent());
        $this->assertStringNotContainsString('PKALIAS12345', $response->getContent());
    }

    public function test_manual_close_requires_confirmation(): void
    {
        [$user, $account] = $this->createAccessContext();
        $alpacaAccount = $this->seedConnectedBroker($account);
        AlpacaPosition::query()->create([
            'account_id' => $account->getKey(),
            'alpaca_account_id' => $alpacaAccount->getKey(),
            'broker_connection_id' => $alpacaAccount->broker_connection_id,
            'symbol' => 'AAPL',
            'qty' => 3,
        ]);

        $this->postJson('/api/mobile/v1/positions/AAPL/manual-close', [], $this->bearerHeaders($user))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'manual_close_confirmation_required');
    }

    public function test_user_cannot_access_another_users_order_or_position(): void
    {
        [$user] = $this->createAccessContext(['slug' => 'current-'.Str::random(11)]);
        [$otherUser, $otherAccount] = $this->createAccessContext(['slug' => 'other-'.Str::random(11)]);
        $alpacaAccount = $this->seedConnectedBroker($otherAccount);
        $order = AlpacaOrder::query()->create([
            'account_id' => $otherAccount->getKey(),
            'alpaca_account_id' => $alpacaAccount->getKey(),
            'broker_connection_id' => $alpacaAccount->broker_connection_id,
            'alpaca_order_id' => 'order-other-1',
            'symbol' => 'AAPL',
            'status' => 'filled',
        ]);
        AlpacaPosition::query()->create([
            'account_id' => $otherAccount->getKey(),
            'alpaca_account_id' => $alpacaAccount->getKey(),
            'broker_connection_id' => $alpacaAccount->broker_connection_id,
            'symbol' => 'MSFT',
            'qty' => 3,
        ]);

        $this->getJson('/api/mobile/v1/orders/'.$order->getKey(), $this->bearerHeaders($user))
            ->assertStatus(404);
        $this->getJson('/api/mobile/v1/positions/MSFT', $this->bearerHeaders($user))
            ->assertStatus(404);

        $this->assertNotNull($otherUser);
    }

    public function test_affiliate_endpoints_only_for_approved_affiliate(): void
    {
        [$user, $account] = $this->createAccessContext();

        $this->getJson('/api/mobile/v1/affiliate/summary', $this->bearerHeaders($user))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'affiliate_not_approved');

        $subscription = $this->seedConfirmedBismel1Subscription($account, 'BISMILLAH1_BOT_PRIME');
        AffiliateCommission::query()->create([
            'subscription_id' => $subscription->getKey(),
            'affiliate_username' => strtoupper(strtok($user->email, '@')),
            'commission_status' => 'earned',
        ]);

        $this->getJson('/api/mobile/v1/affiliate/summary', $this->bearerHeaders($user))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_admin_routes_are_not_exposed_through_mobile_api(): void
    {
        [$user] = $this->createAccessContext();

        $this->getJson('/api/mobile/v1/admin/dashboard', $this->bearerHeaders($user))
            ->assertNotFound();
    }

    public function test_mobile_login_issues_revocable_bearer_token(): void
    {
        [$user] = $this->createAccessContext();
        $user->forceFill(['password' => Hash::make('mobile-pass')])->save();

        $response = $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $user->email,
            'password' => 'mobile-pass',
            'device_name' => 'Expo test device',
        ])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['access_token', 'expires_at', 'auth']]);

        $token = $response->json('data.access_token');

        $this->postJson('/api/mobile/v1/auth/logout', [], ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        $this->getJson('/api/mobile/v1/dashboard', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401);
    }

    protected function bearerHeaders(User $user): array
    {
        [, $token] = MobileAccessToken::issue($user, 'feature-test');

        return ['Authorization' => 'Bearer '.$token];
    }

    protected function seedInstrument(string $symbol): InstrumentMaster
    {
        return InstrumentMaster::query()->create([
            'symbol' => $symbol,
            'name' => $symbol.' Inc',
            'asset_type' => 'equity',
            'status' => 'active',
            'tradable' => true,
            'last_verified_at' => now(),
        ]);
    }

    protected function seedConnectedBroker($account): AlpacaAccount
    {
        $connection = BrokerConnection::query()->create([
            'account_id' => $account->getKey(),
            'managed_by_user_id' => $account->owner_user_id,
            'slot_number' => 1,
            'name' => 'Primary Alpaca',
            'broker' => 'alpaca',
            'status' => 'connected',
            'validation_status' => 'validated',
        ]);

        return AlpacaAccount::query()->create([
            'account_id' => $account->getKey(),
            'broker_connection_id' => $connection->getKey(),
            'name' => 'Primary Alpaca',
            'environment' => 'paper',
            'data_feed' => 'iex',
            'status' => 'active',
            'sync_status' => 'success',
            'trade_stream_status' => 'credentials_verified',
            'is_primary' => true,
            'is_active' => true,
            'buying_power' => 7300,
            'equity' => 11000,
            'last_synced_at' => now(),
        ]);
    }
}
