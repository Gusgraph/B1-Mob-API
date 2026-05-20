<?php
// أَعُوذُ بِٱللَّهِ مِنْ الْشَيْطَانٍ الْرَجِيمٍ ✧ بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ ✧ اعوز بالله من الشياطين و ان يحضرون ✧ بسم الله الرحمن الرحيم ✧ الله لا إله إلا هو الحي القيوم
// Bismillahi ar-Rahmani ar-Rahim Audhu billahi min ash-shayatin wa an yahdurun Bismillah ar-Rahman ar-Rahim Allah la ilaha illa huwa al-hayy al-qayyum. Tamsa Allahu ala ayunihim
// version: x
// ======================================================
// - App Name: bismel1.com
// - Gusgraph -
// - Author: Gus Kazem
// - https://Gusgraph.com
// - File Path: app/Http/Controllers/Api/Mobile/MobileAuthController.php
// =====================================================

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileAccessToken;
use App\Models\User;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:73'],
        ]);

        if ($validator->fails()) {
            return MobileApiResponse::error('validation_failed', 'Check the login fields and try again.', $validator->errors()->toArray(), 422);
        }

        $user = User::query()->where('email', $request->string('email')->lower()->value())->first();

        if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return MobileApiResponse::error('invalid_credentials', 'The provided credentials are not valid.', [], 401);
        }

        if (! $user->email_verified_at) {
            return MobileApiResponse::error('email_unverified', 'Verify your email before using the mobile app.', [], 403);
        }

        if (! $user->hasCustomerAccess()) {
            return MobileApiResponse::error('customer_access_required', 'Customer access is not available for this account.', [], 403);
        }

        [$token, $plainTextToken] = MobileAccessToken::issue($user, $request->string('device_name')->trim()->value() ?: null);

        return MobileApiResponse::success([
            'token_type' => 'Bearer',
            'access_token' => $plainTextToken,
            'expires_at' => $token->expires_at?->toIso8601String(),
            'auth' => [
                'approach' => 'Laravel mobile bearer token',
                'storage' => 'Store the token in device secure storage only.',
                'refresh' => 'Use POST /api/mobile/v1/auth/refresh before expiry. Logout revokes the token.',
            ],
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => true,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('mobile_access_token');

        if ($token instanceof MobileAccessToken) {
            $token->forceFill(['revoked_at' => now()])->save();
        }

        return MobileApiResponse::success(['revoked' => true]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $oldToken = $request->attributes->get('mobile_access_token');

        if ($oldToken instanceof MobileAccessToken) {
            $oldToken->forceFill(['revoked_at' => now()])->save();
        }

        [$token, $plainTextToken] = MobileAccessToken::issue($request->user(), $oldToken?->device_name);

        return MobileApiResponse::success([
            'token_type' => 'Bearer',
            'access_token' => $plainTextToken,
            'expires_at' => $token->expires_at?->toIso8601String(),
        ]);
    }
}
